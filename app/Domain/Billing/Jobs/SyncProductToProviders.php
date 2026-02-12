<?php

namespace App\Domain\Billing\Jobs;

use App\Domain\Billing\Contracts\BillingCatalogProvider;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\ProductProviderMapping;
use App\Domain\Billing\Services\BillingProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToProviders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Product $product,
        public bool $cascadePriceSync = true
    ) {}

    public function handle(BillingProviderManager $manager): void
    {
        $syncedAnyProvider = false;

        // Get configured providers
        $providers = \App\Domain\Billing\Models\PaymentProvider::where('is_active', true)
            ->pluck('slug')
            ->map(fn ($s) => strtolower($s))
            ->toArray();

        foreach ($providers as $provider) {
            try {
                $client = $manager->catalog($provider);
                $this->syncToProvider($client, $provider);
                $syncedAnyProvider = true;
            } catch (\Throwable $e) {
                Log::error("Failed to sync product {$this->product->id} to {$provider}: ".$e->getMessage());
                // We don't rethrow to avoid blocking other providers,
                // but usually you might want to retry.
            }
        }

        if (! $this->cascadePriceSync || ! $syncedAnyProvider) {
            return;
        }

        // Cascade sync to all prices to ensure complete state
        // This handles cases where a provider is newly enabled and needs both product + prices
        $this->product->load('prices');
        foreach ($this->product->prices as $price) {
            \App\Domain\Billing\Jobs\SyncPriceToProviders::dispatch($price);
        }
    }

    private function syncToProvider(BillingCatalogProvider $client, string $provider): void
    {
        // IMPORTANT: Query database directly to avoid race conditions with serialized models
        $mapping = ProductProviderMapping::where('product_id', $this->product->id)
            ->where('provider', $provider)
            ->first();

        if ($mapping) {
            // Mapping already exists - just update the provider product
            $client->updateProduct($this->product, $mapping->provider_id);
            Log::info("Updated product {$this->product->id} on {$provider}");
        } else {
            // No mapping - create product on provider
            $providerId = $client->createProduct($this->product);

            // Use product_id + provider as unique key (matches database constraint)
            ProductProviderMapping::updateOrCreate(
                [
                    'product_id' => $this->product->id,
                    'provider' => $provider,
                ],
                [
                    'provider_id' => $providerId,
                ]
            );

            Log::info("Created product {$this->product->id} on {$provider} (ID: {$providerId})");
        }
    }
}
