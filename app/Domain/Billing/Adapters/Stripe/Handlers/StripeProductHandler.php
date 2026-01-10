<?php

namespace App\Domain\Billing\Adapters\Stripe\Handlers;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\WebhookEvent;
use Illuminate\Support\Str;

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
        $key = $this->generateProductKey($name, $productId);

        return Product::updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => $productId,
            ],
            [
                'key' => $key,
                'name' => $name,
                'description' => data_get($object, 'description'),
                'is_active' => $active,
                'synced_at' => now(),
            ]
        );
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
            ->where('provider', $this->provider())
            ->where('provider_id', $productId)
            ->update([
                'is_active' => false,
                'synced_at' => now(),
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
        $existing = Product::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $productId)
            ->first();

        if ($existing && $existing->key) {
            return $existing->key;
        }

        return "{$slug}-{$suffix}";
    }
}
