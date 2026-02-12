<?php

namespace App\Domain\Billing\Exports;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\StripeClient;

class StripeCatalogPublishAdapter implements CatalogPublishAdapter
{
    private StripeClient $client;

    /** @var array<string, object> */
    private array $productsByKey = [];

    /** @var array<string, object> */
    private array $pricesByLookupKey = [];

    /** @var array<string, object> */
    private array $pricesById = [];

    public function provider(): string
    {
        return 'stripe';
    }

    public function prepare(): void
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw new RuntimeException('Stripe secret is not configured.');
        }

        $this->client = new StripeClient($secret);
        $this->productsByKey = [];
        $this->pricesByLookupKey = [];
        $this->pricesById = [];

        foreach ($this->client->products->all(['limit' => 100])->autoPagingIterator() as $product) {
            $metadata = $this->toMetadataArray($product->metadata);
            // Check for product_key first, then fall back to plan_key for backward compatibility
            $productKey = $this->metadataValue($metadata, 'product_key')
                ?? $this->metadataValue($metadata, 'plan_key');

            if ($productKey) {
                $this->productsByKey[$productKey] = $product;
            }
        }

        foreach ($this->client->prices->all(['limit' => 100])->autoPagingIterator() as $price) {
            $this->pricesById[(string) $price->id] = $price;

            if (! empty($price->lookup_key)) {
                $this->pricesByLookupKey[(string) $price->lookup_key] = $price;
            }

            $metadata = $this->toMetadataArray($price->metadata);
            $productKey = $this->metadataValue($metadata, 'product_key')
                ?? $this->metadataValue($metadata, 'plan_key');
            $priceKey = $this->metadataValue($metadata, 'price_key');

            if ($productKey && $priceKey) {
                $this->pricesByLookupKey[$this->lookupKey($productKey, $priceKey)] = $price;
            }
        }
    }

    public function ensureProduct(Product $product, bool $apply, bool $updateExisting): array
    {
        $productKey = (string) $product->key;
        $existing = $this->productsByKey[$productKey] ?? null;
        $payload = $this->productPayload($product);

        if ($existing) {
            if ($updateExisting) {
                if ($apply) {
                    $existing = $this->client->products->update((string) $existing->id, $payload);
                    $this->productsByKey[$productKey] = $existing;
                }

                return ['action' => 'update', 'id' => (string) $existing->id];
            }

            return ['action' => 'skip', 'id' => (string) $existing->id];
        }

        if (! $apply) {
            return ['action' => 'create', 'id' => null];
        }

        $created = $this->client->products->create($payload);
        $this->productsByKey[$productKey] = $created;

        return ['action' => 'create', 'id' => (string) $created->id];
    }

    public function ensurePrice(Product $product, Price $price, string $providerProductId, bool $apply, bool $updateExisting): array
    {
        $priceKey = (string) ($price->key ?: $price->interval);

        if ($priceKey === '') {
            return ['action' => 'skip', 'id' => null];
        }

        $mappedPriceId = $this->providerPriceId($price);
        if ($mappedPriceId && ! $this->isValidMappedPriceId($mappedPriceId)) {
            $mappedPriceId = null;
        }

        if ($mappedPriceId) {
            return ['action' => 'skip', 'id' => $mappedPriceId];
        }

        $lookupKey = $this->lookupKey($product->key, $priceKey);
        $matched = $this->pricesByLookupKey[$lookupKey] ?? null;

        if ($matched) {
            return ['action' => 'link', 'id' => (string) $matched->id];
        }

        if (! $apply) {
            return ['action' => 'create', 'id' => null];
        }

        $payload = $this->pricePayload($product, $price, $providerProductId, $lookupKey);
        $created = $this->client->prices->create($payload);
        $this->pricesByLookupKey[$lookupKey] = $created;

        return ['action' => 'create', 'id' => (string) $created->id];
    }

    private function lookupKey(string $productKey, string $priceKey): string
    {
        return Str::slug("{$productKey}-{$priceKey}", '-');
    }

    private function productPayload(Product $product): array
    {
        $description = $product->summary ?: $product->description;

        return [
            'name' => (string) $product->name,
            'description' => $description ? (string) $description : null,
            'active' => (bool) $product->is_active,
            'metadata' => array_filter([
                'product_key' => (string) $product->key,
            ], fn ($value) => $value !== ''),
        ];
    }

    private function pricePayload(Product $product, Price $price, string $providerProductId, string $lookupKey): array
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
                'product_key' => (string) $product->key,
                'price_key' => (string) ($price->key ?: $price->interval),
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

    private function toMetadataArray(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            return $metadata->toArray();
        }

        if ($metadata instanceof \ArrayAccess || $metadata instanceof \Traversable) {
            return iterator_to_array($metadata);
        }

        return [];
    }

    private function metadataValue(array $metadata, string $key): ?string
    {
        $value = $metadata[$key] ?? $metadata[Str::camel($key)] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function providerPriceId(Price $price): ?string
    {
        if ($price->relationLoaded('mappings')) {
            $mapping = $price->mappings->firstWhere('provider', $this->provider());
        } else {
            $mapping = $price->mappings()->where('provider', $this->provider())->first();
        }

        if (! $mapping || ! $mapping->provider_id) {
            return null;
        }

        return (string) $mapping->provider_id;
    }

    private function isValidMappedPriceId(string $providerId): bool
    {
        if (! str_starts_with($providerId, 'price_')) {
            return false;
        }

        return isset($this->pricesById[$providerId]);
    }
}
