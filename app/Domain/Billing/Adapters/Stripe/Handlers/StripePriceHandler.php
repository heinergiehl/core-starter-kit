<?php

namespace App\Domain\Billing\Adapters\Stripe\Handlers;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\WebhookEvent;
use Illuminate\Support\Str;
use Stripe\StripeClient;

/**
 * Handles Stripe price lifecycle webhook events.
 *
 * Processes: price.created, price.updated, price.deleted
 *
 * Syncs Stripe prices to the local prices table, enabling
 * automatic pricing management from the Stripe dashboard.
 */
class StripePriceHandler implements StripeWebhookHandler
{
    use ResolvesStripeData;

    /**
     * {@inheritDoc}
     */
    public function eventTypes(): array
    {
        return [
            'price.created',
            'price.updated',
            'price.deleted',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function handle(WebhookEvent $event, array $object): void
    {
        $payload = $event->payload ?? [];
        $eventType = $payload['type'] ?? $event->type;

        if ($eventType === 'price.deleted') {
            $this->deactivatePrice($object);
            return;
        }

        $this->syncPrice($object);
    }

    /**
     * Sync a Stripe price to the local database.
     */
    public function syncPrice(array $object): ?Price
    {
        $priceId = data_get($object, 'id');
        $productId = data_get($object, 'product');

        if (!$priceId) {
            return null;
        }

        // Find or create the product for this price
        $product = $this->resolveOrCreateProduct($productId);

        if (!$product) {
            return null;
        }

        $active = data_get($object, 'active', true);
        $key = $this->generatePriceKey($object, $priceId);
        $recurring = data_get($object, 'recurring', []);

        return Price::updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => $priceId,
            ],
            [
                'product_id' => $product->id,
                'key' => $key,
                'label' => $this->generatePriceLabel($object),
                'interval' => $recurring['interval'] ?? 'one_time',
                'interval_count' => $recurring['interval_count'] ?? 1,
                'currency' => strtoupper(data_get($object, 'currency', 'USD')),
                'amount' => data_get($object, 'unit_amount', 0),
                'type' => data_get($object, 'type', 'recurring'),
                'has_trial' => !empty($recurring['trial_period_days']),
                'trial_interval' => !empty($recurring['trial_period_days']) ? 'day' : null,
                'trial_interval_count' => $recurring['trial_period_days'] ?? null,
                'is_active' => $active,
            ]
        );
    }

    /**
     * Deactivate a price when deleted in Stripe.
     */
    private function deactivatePrice(array $object): void
    {
        $priceId = data_get($object, 'id');

        if (!$priceId) {
            return;
        }

        Price::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $priceId)
            ->update(['is_active' => false]);
    }

    /**
     * Resolve the product for a Stripe product id, creating if needed.
     */
    private function resolveOrCreateProduct(?string $productId): ?Product
    {
        if (!$productId) {
            return null;
        }

        // Try to find a product and create one if needed
        $product = Product::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $productId)
            ->first();

        if (!$product) {
            // Fetch product from Stripe and sync it
            $product = $this->syncProductFromStripe($productId);
        }

        if (!$product) {
            return null;
        }

        return $product;
    }

    /**
     * Sync a product from Stripe API.
     */
    private function syncProductFromStripe(string $productId): ?Product
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            return null;
        }

        try {
            $client = new StripeClient($secret);
            $stripeProduct = $client->products->retrieve($productId, []);

            $handler = new StripeProductHandler();
            return $handler->syncProduct($stripeProduct->toArray());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Generate a unique, readable price key.
     */
    private function generatePriceKey(array $object, string $priceId): string
    {
        // Check if already exists with a key
        $existing = Price::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $priceId)
            ->first();

        if ($existing && $existing->key) {
            return $existing->key;
        }

        $recurring = data_get($object, 'recurring', []);
        $interval = $recurring['interval'] ?? 'one_time';
        $suffix = Str::substr($priceId, -6);

        return "{$interval}-{$suffix}";
    }

    /**
     * Generate a human-readable price label.
     */
    private function generatePriceLabel(array $object): string
    {
        $recurring = data_get($object, 'recurring', []);
        $interval = $recurring['interval'] ?? null;
        $count = $recurring['interval_count'] ?? 1;

        if (!$interval) {
            return 'One-time';
        }

        if ($count === 1) {
            return ucfirst($interval) . 'ly';
        }

        return "Every {$count} {$interval}s";
    }
}
