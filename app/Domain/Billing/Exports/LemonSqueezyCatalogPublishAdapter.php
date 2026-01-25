<?php

namespace App\Domain\Billing\Exports;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class LemonSqueezyCatalogPublishAdapter implements CatalogPublishAdapter
{
    private ?string $apiKey = null;

    private ?string $storeId = null;

    /** @var array<string, array> */
    private array $productsByKey = [];

    /** @var array<string, array> */
    private array $variantsByLookupKey = [];

    /** @var array<string, array> */
    private array $unmappedVariantsByProductId = [];

    public function provider(): string
    {
        return 'lemonsqueezy';
    }

    public function prepare(): void
    {
        $this->apiKey = config('services.lemonsqueezy.api_key');
        $this->storeId = config('services.lemonsqueezy.store_id');

        if (! $this->apiKey) {
            throw new RuntimeException('Lemon Squeezy API key is not configured.');
        }

        if (! $this->storeId) {
            throw new RuntimeException('Lemon Squeezy store ID is not configured.');
        }

        $this->productsByKey = [];
        $this->variantsByLookupKey = [];
        $this->unmappedVariantsByProductId = [];

        // Fetch existing products
        $productsResponse = $this->apiGet('/products', ['filter[store_id]' => $this->storeId]);

        if ($productsResponse->successful()) {
            foreach ($productsResponse->json('data') ?? [] as $product) {
                $productKey = data_get($product, 'attributes.custom_data.product_key')
                    ?? data_get($product, 'attributes.custom_data.plan_key');
                if ($productKey) {
                    $this->productsByKey[$productKey] = $product;
                }
            }
        }

        // Fetch existing variants (prices)
        $variantsResponse = $this->apiGet('/variants');

        if ($variantsResponse->successful()) {
            foreach ($variantsResponse->json('data') ?? [] as $variant) {
                $productKey = data_get($variant, 'attributes.custom_data.product_key')
                    ?? data_get($variant, 'attributes.custom_data.plan_key');
                $priceKey = data_get($variant, 'attributes.custom_data.price_key');

                if ($productKey && $priceKey) {
                    $this->variantsByLookupKey[$this->lookupKey($productKey, $priceKey)] = $variant;
                } else {
                    $productId = data_get($variant, 'relationships.product.data.id');
                    if ($productId) {
                        $this->unmappedVariantsByProductId[$productId][] = $variant;
                    }
                }
            }
        }
    }

    public function ensureProduct(Product $product, bool $apply, bool $updateExisting): array
    {
        $productKey = (string) $product->key;
        $existing = $this->productsByKey[$productKey] ?? null;

        if ($existing) {
            if ($updateExisting) {
                if ($apply) {
                    $payload = $this->productPayload($product);
                    $response = $this->apiPatch("/products/{$existing['id']}", $payload);

                    if ($response->successful()) {
                        $this->productsByKey[$productKey] = $response->json('data');
                    }
                }

                return ['action' => 'update', 'id' => (string) $existing['id']];
            }

            return ['action' => 'skip', 'id' => (string) $existing['id']];
        }

        if (! $apply) {
            return ['action' => 'create', 'id' => null];
        }

        $payload = $this->productCreatePayload($product);
        $response = $this->apiPost('/products', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Lemon Squeezy product creation failed: '.$response->body());
        }

        $created = $response->json('data');
        $this->productsByKey[$productKey] = $created;

        return ['action' => 'create', 'id' => (string) $created['id']];
    }

    public function ensurePrice(Product $product, Price $price, string $providerProductId, bool $apply, bool $updateExisting): array
    {
        $priceKey = (string) ($price->key ?: $price->interval);

        if ($priceKey === '') {
            return ['action' => 'skip', 'id' => null];
        }

        // Check for existing linkage by explicit ID
        $mappedPriceId = $this->providerPriceId($price);
        if ($mappedPriceId) {
            // Remove from unmapped if present to prevent other prices from claiming it
            // This is critical: if we don't remove it, a subsequent "unmapped" price might claim this "Default" variant
            // thinking it's free, leading to a unique constraint violation.
            if (isset($this->unmappedVariantsByProductId[$providerProductId])) {
                $this->unmappedVariantsByProductId[$providerProductId] = array_values(array_filter(
                    $this->unmappedVariantsByProductId[$providerProductId],
                    fn ($v) => (string) $v['id'] !== $mappedPriceId
                ));
            }

            // Already linked, but we might want to update it
        }

        $lookupKey = $this->lookupKey($product->key, $priceKey);
        $matched = $this->variantsByLookupKey[$lookupKey] ?? null;

        // If not matched by key, check if we can claim an existing unmapped variant
        // (This typically happens for the "Default" variant created automatically by Lemon Squeezy)
        if (! $matched && ! $mappedPriceId) {
            $unmapped = $this->unmappedVariantsByProductId[$providerProductId] ?? [];
            if (! empty($unmapped)) {
                // We'll take the first unmapped variant that matches our subscription/one-time type if possible
                // For now, just taking the first one is usually correct for the "Default" variant case
                // Use array_shift to REMOVE it from unmapped, preventing double-claim
                $matched = array_shift($unmapped);
                $this->unmappedVariantsByProductId[$providerProductId] = $unmapped;
            }
        }

        $remoteId = $mappedPriceId ?: ($matched['id'] ?? null);

        if ($remoteId) {
            if ($updateExisting) {
                if ($apply) {
                    $payload = $this->variantCreatePayload($product, $price, $providerProductId);
                    $response = $this->apiPatch("/variants/{$remoteId}", $payload);

                    if ($response->successful()) {
                        // Update cache if needed
                    }
                }

                return ['action' => 'update', 'id' => (string) $remoteId];
            }

            return ['action' => 'link', 'id' => (string) $remoteId];
        }

        if (! $apply) {
            return ['action' => 'create', 'id' => null];
        }

        // Note: Lemon Squeezy creates a default variant with the product
        // We need to update the default variant or create a new one
        $payload = $this->variantCreatePayload($product, $price, $providerProductId);
        $response = $this->apiPost('/variants', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Lemon Squeezy variant creation failed: '.$response->body());
        }

        $created = $response->json('data');
        $this->variantsByLookupKey[$lookupKey] = $created;

        return ['action' => 'create', 'id' => (string) $created['id']];
    }

    private function lookupKey(string $productKey, string $priceKey): string
    {
        return Str::slug("{$productKey}-{$priceKey}", '-');
    }

    private function productPayload(Product $product): array
    {
        $description = $product->summary ?: $product->description;

        return [
            'data' => [
                'type' => 'products',
                'attributes' => [
                    'name' => (string) $product->name,
                    'description' => $description ? (string) $description : null,
                ],
            ],
        ];
    }

    private function productCreatePayload(Product $product): array
    {
        $description = $product->summary ?: $product->description;

        return [
            'data' => [
                'type' => 'products',
                'attributes' => [
                    'name' => (string) $product->name,
                    'description' => $description ? (string) $description : null,
                    'custom_data' => array_filter([
                        'product_key' => (string) $product->key,
                    ], fn ($value) => $value !== ''),
                ],
                'relationships' => [
                    'store' => [
                        'data' => [
                            'type' => 'stores',
                            'id' => (string) $this->storeId,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function variantCreatePayload(Product $product, Price $price, string $providerProductId): array
    {
        $interval = strtolower((string) $price->interval);
        $intervalCount = (int) ($price->interval_count ?: 1);

        $isSubscription = in_array($interval, ['day', 'week', 'month', 'year'], true);

        $attributes = [
            'name' => $price->label ?: ucfirst($price->key ?: $price->interval ?: 'price'),
            'price' => (int) $price->amount,
            'is_subscription' => $isSubscription,
            'custom_data' => array_filter([
                'product_key' => (string) $product->key,
                'price_key' => (string) ($price->key ?: $price->interval),
            ], fn ($value) => $value !== ''),
        ];

        if ($isSubscription) {
            $attributes['interval'] = $interval;
            $attributes['interval_count'] = max($intervalCount, 1);
        }

        return [
            'data' => [
                'type' => 'variants',
                'attributes' => $attributes,
                'relationships' => [
                    'product' => [
                        'data' => [
                            'type' => 'products',
                            'id' => $providerProductId,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function apiGet(string $endpoint, array $query = []): \Illuminate\Http\Client\Response
    {
        return Http::withToken($this->apiKey)
            ->withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ])
            ->get("https://api.lemonsqueezy.com/v1{$endpoint}", $query);
    }

    private function apiPost(string $endpoint, array $payload): \Illuminate\Http\Client\Response
    {
        return Http::withToken($this->apiKey)
            ->withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ])
            ->withBody(json_encode($payload), 'application/vnd.api+json')
            ->post("https://api.lemonsqueezy.com/v1{$endpoint}");
    }

    private function apiPatch(string $endpoint, array $payload): \Illuminate\Http\Client\Response
    {
        return Http::withToken($this->apiKey)
            ->withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ])
            ->withBody(json_encode($payload), 'application/vnd.api+json')
            ->patch("https://api.lemonsqueezy.com/v1{$endpoint}");
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
}
