<?php

namespace App\Domain\Billing\Jobs;

use App\Domain\Billing\Contracts\BillingCatalogProvider;
use App\Domain\Billing\Models\PaymentProvider;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Services\BillingProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncPriceToProviders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Price $price
    ) {}

    public function handle(BillingProviderManager $manager): void
    {
        // Get configured providers
        $providers = PaymentProvider::where('is_active', true)
            ->pluck('slug')
            ->map(fn ($s) => strtolower($s))
            ->toArray();

        foreach ($providers as $provider) {
            try {
                $client = $manager->catalog($provider);
                $this->syncToProvider($client, $provider, $manager);
            } catch (\Throwable $e) {
                Log::error("Failed to sync price {$this->price->id} to {$provider}: ".$e->getMessage());
            }
        }
    }

    private function syncToProvider(BillingCatalogProvider $client, string $provider, BillingProviderManager $manager): void
    {
        // Ensure Product Exists First
        $productMapping = $this->price->product->providerMappings()->where('provider', $provider)->first();
        if (! $productMapping) {
            Log::warning("Product {$this->price->product_id} not synced to {$provider}. Triggering product sync first.");

            // Synchronously sync product without cascading into price sync jobs.
            (new SyncProductToProviders($this->price->product, false))->handle($manager);

            $this->price->product->refresh();
            $productMapping = $this->price->product->providerMappings()->where('provider', $provider)->first();
        }

        if (! $productMapping) {
            Log::error("Product {$this->price->product_id} is still not mapped for {$provider}. Skipping price {$this->price->id} sync.");

            return;
        }

        $mapping = $this->price->mappings()->where('provider', $provider)->first();

        if ($mapping) {
            // Update
            $client->updatePrice($this->price, $mapping->provider_id);
            Log::info("Updated price {$this->price->id} on {$provider}");
        } else {
            // Create
            $providerId = $client->createPrice($this->price);

            PriceProviderMapping::create([
                'price_id' => $this->price->id,
                'provider' => $provider,
                'provider_id' => $providerId,
            ]);

            Log::info("Created price {$this->price->id} on {$provider} (ID: {$providerId})");
        }
    }
}
