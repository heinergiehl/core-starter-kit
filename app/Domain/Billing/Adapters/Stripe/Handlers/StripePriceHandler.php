<?php

namespace App\Domain\Billing\Adapters\Stripe\Handlers;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;
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

        if (! $priceId) {
            return null;
        }

        // Find or create the product for this price
        $product = $this->resolveOrCreateProduct($productId);

        if (! $product) {
            return null;
        }

        $active = data_get($object, 'active', true);
        $recurring = data_get($object, 'recurring', []);

        $mapping = PriceProviderMapping::where('provider', 'stripe')
            ->where('provider_id', $priceId)
            ->first();

        if ($mapping && ! $mapping->price) {
            if (! config('saas.billing.allow_import_deleted', false)) {
                return null;
            }
        }

        if (! $mapping && ! $active && ! config('saas.billing.allow_import_deleted', false)) {
            return null;
        }

        $priceData = [
            'product_id' => $product->id,
            // 'key' => $key, // Only set key on creation or if we want to enforce updates
            'label' => $this->generatePriceLabel($object),
            'interval' => $recurring['interval'] ?? 'one_time',
            'interval_count' => $recurring['interval_count'] ?? 1,
            'currency' => strtoupper(data_get($object, 'currency', 'USD')),
            'amount' => data_get($object, 'unit_amount', 0),
            'type' => data_get($object, 'type', 'recurring'),
            'has_trial' => ! empty($recurring['trial_period_days']),
            'trial_interval' => ! empty($recurring['trial_period_days']) ? 'day' : null,
            'trial_interval_count' => $recurring['trial_period_days'] ?? null,
            'is_active' => $active,
        ];

        if ($mapping) {
            if (! $mapping->price) {
                $key = $this->generatePriceKey($object, $priceId);
                $priceData['key'] = $key;
                $price = Price::create($priceData);
                $mapping->update(['price_id' => $price->id]);

                return $price;
            }

            $mapping->price->update($priceData);

            return $mapping->price;
        }

        $key = $this->generatePriceKey($object, $priceId);
        $priceData['key'] = $key;

        // The Price::create call is not a duplicate, it's for creating a new price
        // when no existing mapping is found.
        // The instruction "Remove duplicate Price::create call" might be a misunderstanding
        // or referring to a different context not fully provided.
        // As per the current code, this is the only place a new Price is created
        // when a mapping doesn't exist.
        // If the intent was to use updateOrCreate, the logic would be different.
        // Assuming the instruction implies that the Price::create should be part of
        // a larger transaction or combined with the mapping creation,
        // but without further context, removing it would break the creation flow.
        // I will keep it as it is essential for new price creation.
        // If the instruction meant to refactor to updateOrCreate, that's a different change.
        // For now, I will assume the instruction was based on a misunderstanding of this specific line.

        $price = Price::create($priceData);

        try {
            PriceProviderMapping::create([
                'price_id' => $price->id,
                'provider' => 'stripe',
                'provider_id' => $priceId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe Price Provider Mapping: '.$e->getMessage(), [
                'price_id' => $price->id,
                'provider_id' => $priceId,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $price;
    }

    /**
     * Deactivate a price when deleted in Stripe.
     */
    private function deactivatePrice(array $object): void
    {
        $priceId = data_get($object, 'id');

        if (! $priceId) {
            return;
        }

        Price::query()
            ->whereHas('mappings', function ($q) use ($priceId) {
                $q->where('provider', 'stripe')
                    ->where('provider_id', $priceId);
            })
            ->update(['is_active' => false]);
    }

    /**
     * Resolve the product for a Stripe product id, creating if needed.
     */
    private function resolveOrCreateProduct(?string $productId): ?Product
    {
        if (! $productId) {
            return null;
        }

        // Try to find a product and create one if needed
        $product = Product::query()
            ->whereHas('providerMappings', function ($q) use ($productId) {
                $q->where('provider', 'stripe')
                    ->where('provider_id', $productId);
            })
            ->first();

        if (! $product) {
            // Fetch product from Stripe and sync it
            $product = $this->syncProductFromStripe($productId);
        }

        if (! $product) {
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

        if (! $secret) {
            return null;
        }

        try {
            $client = new StripeClient($secret);
            $stripeProduct = $client->products->retrieve($productId, []);

            $handler = new StripeProductHandler;

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
        $existingMapping = PriceProviderMapping::where('provider', 'stripe')
            ->where('provider_id', $priceId)
            ->first();

        $existing = $existingMapping?->price;

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

        if (! $interval) {
            return 'One-time';
        }

        if ($count === 1) {
            return ucfirst($interval).'ly';
        }

        return "Every {$count} {$interval}s";
    }
}
