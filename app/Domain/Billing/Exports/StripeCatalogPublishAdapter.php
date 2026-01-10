<?php

namespace App\Domain\Billing\Exports;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\StripeClient;

class StripeCatalogPublishAdapter implements CatalogPublishAdapter
{
    private StripeClient $client;
    /** @var array<string, object> */
    private array $productsByPlanKey = [];
    /** @var array<string, object> */
    private array $pricesByLookupKey = [];

    public function provider(): string
    {
        return 'stripe';
    }

    public function prepare(): void
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            throw new RuntimeException('Stripe secret is not configured.');
        }

        $this->client = new StripeClient($secret);
        $this->productsByPlanKey = [];
        $this->pricesByLookupKey = [];

        foreach ($this->client->products->all(['limit' => 100])->autoPagingIterator() as $product) {
            $planKey = $this->metadataValue($product->metadata ?? [], 'plan_key');

            if ($planKey) {
                $this->productsByPlanKey[$planKey] = $product;
            }
        }

        foreach ($this->client->prices->all(['limit' => 100])->autoPagingIterator() as $price) {
            if (!empty($price->lookup_key)) {
                $this->pricesByLookupKey[(string) $price->lookup_key] = $price;
            }

            $planKey = $this->metadataValue($price->metadata ?? [], 'plan_key');
            $priceKey = $this->metadataValue($price->metadata ?? [], 'price_key');

            if ($planKey && $priceKey) {
                $this->pricesByLookupKey[$this->lookupKey($planKey, $priceKey)] = $price;
            }
        }
    }

    public function ensureProduct(Product $product, Plan $plan, bool $apply, bool $updateExisting): array
    {
        $planKey = (string) $plan->key;
        $existing = $this->productsByPlanKey[$planKey] ?? null;
        $payload = $this->productPayload($product, $plan);

        if ($existing) {
            if ($updateExisting) {
                if ($apply) {
                    $existing = $this->client->products->update((string) $existing->id, $payload);
                    $this->productsByPlanKey[$planKey] = $existing;
                }

                return ['action' => 'update', 'id' => (string) $existing->id];
            }

            return ['action' => 'skip', 'id' => (string) $existing->id];
        }

        if (!$apply) {
            return ['action' => 'create', 'id' => null];
        }

        $created = $this->client->products->create($payload);
        $this->productsByPlanKey[$planKey] = $created;

        return ['action' => 'create', 'id' => (string) $created->id];
    }

    public function ensurePrice(Plan $plan, Price $price, string $providerProductId, bool $apply, bool $updateExisting): array
    {
        $priceKey = (string) ($price->key ?: $price->interval);

        if ($priceKey === '') {
            return ['action' => 'skip', 'id' => null];
        }

        if (!empty($price->provider_id)) {
            return ['action' => 'skip', 'id' => (string) $price->provider_id];
        }

        $lookupKey = $this->lookupKey($plan->key, $priceKey);
        $matched = $this->pricesByLookupKey[$lookupKey] ?? null;

        if ($matched) {
            return ['action' => 'link', 'id' => (string) $matched->id];
        }

        if (!$apply) {
            return ['action' => 'create', 'id' => null];
        }

        $payload = $this->pricePayload($plan, $price, $providerProductId, $lookupKey);
        $created = $this->client->prices->create($payload);
        $this->pricesByLookupKey[$lookupKey] = $created;

        return ['action' => 'create', 'id' => (string) $created->id];
    }

    private function lookupKey(string $planKey, string $priceKey): string
    {
        return Str::slug("{$planKey}-{$priceKey}", '-');
    }

    private function productPayload(Product $product, Plan $plan): array
    {
        $description = $plan->summary ?: $plan->description ?: $product->description;

        return [
            'name' => (string) $plan->name,
            'description' => $description ? (string) $description : null,
            'active' => (bool) $plan->is_active,
            'metadata' => array_filter([
                'plan_key' => (string) $plan->key,
                'product_key' => (string) $product->key,
            ], fn ($value) => $value !== ''),
        ];
    }

    private function pricePayload(Plan $plan, Price $price, string $providerProductId, string $lookupKey): array
    {
        $interval = strtolower((string) $price->interval);
        $intervalCount = (int) ($price->interval_count ?: 1);
        $payload = [
            'product' => $providerProductId,
            'currency' => strtolower((string) $price->currency),
            'unit_amount' => (int) $price->amount,
            'active' => (bool) $price->is_active,
            'lookup_key' => $lookupKey,
            'nickname' => $price->label ?: ucfirst($price->key ?: $price->interval ?: 'price'),
            'metadata' => array_filter([
                'plan_key' => (string) $plan->key,
                'price_key' => (string) ($price->key ?: $price->interval),
                'product_key' => (string) ($plan->product?->key ?? ''),
            ], fn ($value) => $value !== ''),
        ];

        if ($this->isRecurringInterval($interval)) {
            $payload['recurring'] = [
                'interval' => $interval,
                'interval_count' => max($intervalCount, 1),
            ];
        }

        return $payload;
    }

    private function isRecurringInterval(string $interval): bool
    {
        return in_array($interval, ['day', 'week', 'month', 'year'], true);
    }

    private function metadataValue(array $metadata, string $key): ?string
    {
        $value = $metadata[$key] ?? $metadata[Str::camel($key)] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
