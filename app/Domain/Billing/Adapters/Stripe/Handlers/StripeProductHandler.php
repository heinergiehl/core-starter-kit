<?php

namespace App\Domain\Billing\Adapters\Stripe\Handlers;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\ProductProviderMapping;
use App\Domain\Billing\Models\WebhookEvent;
use Illuminate\Support\Str;
use Stripe\StripeClient;

/**
 * Handles Stripe product lifecycle webhook events.
 *
 * Processes: product.created, product.updated, product.deleted
 *
 * Syncs Stripe products to the local products table, enabling
 * automatic catalog management from the Stripe dashboard.
 */
class StripeProductHandler implements StripeWebhookHandler
{
    use ResolvesStripeData;

    /**
     * {@inheritDoc}
     */
    public function eventTypes(): array
    {
        return [
            'product.created',
            'product.updated',
            'product.deleted',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function handle(WebhookEvent $event, array $object): void
    {
        $payload = $event->payload ?? [];
        $eventType = $payload['type'] ?? $event->type;

        if ($eventType === 'product.deleted') {
            $this->deactivateProduct($object);
            return;
        }

        $this->syncProduct($object);
    }

    /**
     * Sync a Stripe product to the local database.
     */
    public function syncProduct(array $object): ?Product
    {
        $productId = data_get($object, 'id');
        $name = data_get($object, 'name');

        if (!$productId || !$name) {
            return null;
        }

        $active = data_get($object, 'active', true);
        
        $mapping = ProductProviderMapping::where('provider', 'stripe')
            ->where('provider_id', $productId)
            ->first();

        if ($mapping && !$mapping->product) {
            if (!config('saas.billing.allow_import_deleted', false)) {
                return null;
            }
        }

        if (!$mapping && !$active && !config('saas.billing.allow_import_deleted', false)) {
            return null;
        }

        // Determine product type from Stripe prices
        $type = $this->resolveProductType($productId);

        $productData = [
            'name' => $name,
            'description' => data_get($object, 'description'),
            'type' => $type,
            'is_active' => $active,
            // 'synced_at' => now(),
        ];

        if ($mapping) {
             if (!$mapping->product) {
                 $key = $this->generateProductKey($name, $productId);
                 $productData['key'] = $key;
                 $product = Product::create($productData);
                 $mapping->update(['product_id' => $product->id]);

                 return $product;
             }

             $mapping->product->update($productData);
             return $mapping->product;
        }

        $key = $this->generateProductKey($name, $productId);
        $productData['key'] = $key;
        
        $product = Product::create($productData);
        
        ProductProviderMapping::create([
            'product_id' => $product->id,
            'provider' => 'stripe',
            'provider_id' => $productId,
        ]);
        
        return $product;
    }

    /**
     * Determine the product type based on its Stripe prices.
     * 
     * If all prices are one-time (no recurring), returns 'one_time'.
     * If any price has recurring billing, returns 'subscription'.
     */
    private function resolveProductType(string $productId): string
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            return 'subscription'; // Default to subscription if we can't check
        }

        try {
            $client = new StripeClient($secret);
            $prices = $client->prices->all([
                'product' => $productId,
                'limit' => 10,
            ]);

            if (empty($prices->data)) {
                return 'subscription'; // No prices yet, default to subscription
            }

            // Check if ANY price has a recurring component
            foreach ($prices->data as $price) {
                if (!empty($price->recurring)) {
                    return 'subscription';
                }
            }

            // All prices are one-time
            return 'one_time';
        } catch (\Throwable) {
            return 'subscription'; // On error, default to subscription
        }
    }

    /**
     * Deactivate a product when deleted in Stripe.
     *
     * We don't delete the product to preserve local data,
     * we just mark it as inactive.
     */
    private function deactivateProduct(array $object): void
    {
        $productId = data_get($object, 'id');

        if (!$productId) {
            return;
        }

        Product::query()
            ->whereHas('providerMappings', function ($q) use ($productId) {
                $q->where('provider', 'stripe')
                  ->where('provider_id', $productId);
            })
            ->update([
                'is_active' => false,
                // 'synced_at' => now(),
            ]);
    }

    /**
     * Generate a unique, readable product key.
     */
    private function generateProductKey(string $name, string $productId): string
    {
        $slug = Str::slug($name);
        $suffix = Str::substr($productId, -6);

        // Check if a product with this provider_id already exists and has a key
        // Check if a product with this provider_id already exists and has a key
        $existingMapping = ProductProviderMapping::where('provider', 'stripe')
            ->where('provider_id', $productId)
            ->first();
            
        $existing = $existingMapping?->product;

        if ($existing && $existing->key) {
            return $existing->key;
        }

        return "{$slug}-{$suffix}";
    }
}
