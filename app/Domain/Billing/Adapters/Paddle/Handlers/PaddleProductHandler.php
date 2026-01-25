<?php

namespace App\Domain\Billing\Adapters\Paddle\Handlers;

use App\Domain\Billing\Adapters\Paddle\Concerns\ResolvesPaddleData;
use App\Domain\Billing\Contracts\PaddleWebhookHandler;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\ProductProviderMapping;
use App\Domain\Billing\Models\WebhookEvent;
use Illuminate\Support\Str;

/**
 * Handles Paddle product lifecycle webhook events.
 *
 * Processes: product.created, product.updated
 */
class PaddleProductHandler implements PaddleWebhookHandler
{
    use ResolvesPaddleData;

    public function eventTypes(): array
    {
        return [
            'product.created',
            'product.updated',
        ];
    }

    public function handle(WebhookEvent $event, array $data): void
    {
        if (! config('saas.billing.sync_catalog_via_webhooks', true)) {
            return;
        }

        $this->syncProduct($data);
    }

    /**
     * Sync a Paddle product to the local database.
     */
    public function syncProduct(array $data): ?Product
    {
        $productId = data_get($data, 'id');
        $name = data_get($data, 'name');

        if (! $productId) {
            return null;
        }

        $customData = data_get($data, 'custom_data', []);
        $key = $customData['plan_key'] ?? $customData['product_key'] ?? Str::slug("paddle-{$productId}");

        $mapping = ProductProviderMapping::where('provider', 'paddle')
            ->where('provider_id', (string) $productId)
            ->first();

        if ($mapping && ! $mapping->product && ! config('saas.billing.allow_import_deleted', false)) {
            return null;
        }

        $status = data_get($data, 'status', 'active');
        if (! $mapping && $status !== 'active') {
            return null;
        }

        $productData = [
            'name' => $name ?? 'Paddle Product',
            'description' => data_get($data, 'description'),
            'is_active' => $status === 'active',
            'synced_at' => now(),
        ];

        if ($mapping) {
            $product = $mapping->product;
            $product->update($productData);

            return $product;
        }

        // Ensure unique key
        $existingProduct = Product::where('key', $key)->first();
        if ($existingProduct) {
            $key = $key.'-'.Str::random(4);
        }

        $productData['key'] = $key;
        $product = Product::create($productData);

        ProductProviderMapping::create([
            'product_id' => $product->id,
            'provider' => 'paddle',
            'provider_id' => (string) $productId,
        ]);

        return $product;
    }
}
