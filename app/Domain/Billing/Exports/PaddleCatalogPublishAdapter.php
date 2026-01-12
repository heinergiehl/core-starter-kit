<?php

namespace App\Domain\Billing\Exports;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaddleCatalogPublishAdapter implements CatalogPublishAdapter
{
    private ?string $apiKey = null;
    /** @var array<string, array> */
    private array $productsByKey = [];
    /** @var array<string, array> */
    private array $pricesByLookupKey = [];

    public function provider(): string
    {
        return 'paddle';
    }

    public function prepare(): void
    {
        $this->apiKey = config('services.paddle.api_key');

        if (!$this->apiKey) {
            throw new RuntimeException('Paddle API key is not configured.');
        }

        $this->productsByKey = [];
        $this->pricesByLookupKey = [];

        // Fetch existing products
        $productsResponse = Http::withToken($this->apiKey)
            ->acceptJson()
            ->get('https://api.paddle.com/products', ['per_page' => 200]);

        if ($productsResponse->successful()) {
            foreach ($productsResponse->json('data') ?? [] as $product) {
                $productKey = data_get($product, 'custom_data.product_key')
                    ?? data_get($product, 'custom_data.plan_key');
                if ($productKey) {
                    $this->productsByKey[$productKey] = $product;
                }
            }
        }

        // Fetch existing prices
        $pricesResponse = Http::withToken($this->apiKey)
            ->acceptJson()
            ->get('https://api.paddle.com/prices', ['per_page' => 200]);

        if ($pricesResponse->successful()) {
            foreach ($pricesResponse->json('data') ?? [] as $price) {
                $productKey = data_get($price, 'custom_data.product_key')
                    ?? data_get($price, 'custom_data.plan_key');
                $priceKey = data_get($price, 'custom_data.price_key');

                if ($productKey && $priceKey) {
                    $this->pricesByLookupKey[$this->lookupKey($productKey, $priceKey)] = $price;
                }
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
                    $response = Http::withToken($this->apiKey)
                        ->acceptJson()
                        ->patch("https://api.paddle.com/products/{$existing['id']}", $payload);

                    if ($response->successful()) {
                        $this->productsByKey[$productKey] = $response->json('data');
                    }
                }

                return ['action' => 'update', 'id' => (string) $existing['id']];
            }

            return ['action' => 'skip', 'id' => (string) $existing['id']];
        }

        if (!$apply) {
            return ['action' => 'create', 'id' => null];
        }

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->post('https://api.paddle.com/products', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Paddle product creation failed: ' . $response->body());
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

        // 1. Check for a match in the cache based on natural keys
        $lookupKey = $this->lookupKey($product->key, $priceKey);
        $matched = $this->pricesByLookupKey[$lookupKey] ?? null;

        if ($matched) {
            // Remove from array so it can't be claimed again (prevents Unique Constraint Violation)
            unset($this->pricesByLookupKey[$lookupKey]);
        }

        // 2. Determine the Remote ID: Either explicitly set on Price OR from the matched lookup
        $remoteId = $price->provider_id ?: ($matched['id'] ?? null);

        // 3. If we have a remote ID, we are either updating or linking/skipping
        if ($remoteId) {
            // Logic to update...
            if ($updateExisting) {
                if ($apply) {
                    // Update logic currently skipped as Paddle prices are mostly immutable.
                    // If we needed to update mutable fields (like description), we'd do it here.
                }
                return ['action' => 'update', 'id' => (string) $remoteId];
            }
            
            return ['action' => 'link', 'id' => (string) $remoteId];
        }

        // 4. If we are here, we need to CREATE a new price
        if (!$apply) {
            return ['action' => 'create', 'id' => null];
        }

        $payload = $this->pricePayload($product, $price, $providerProductId);

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->post('https://api.paddle.com/prices', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Paddle price creation failed: ' . $response->body());
        }

        $created = $response->json('data');

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
            'name' => (string) $product->name,
            'description' => $description ? (string) $description : null,
            'tax_category' => 'standard',
            'custom_data' => array_filter([
                'product_key' => (string) $product->key,
            ], fn ($value) => $value !== ''),
        ];
    }

    private function pricePayload(Product $product, Price $price, string $providerProductId): array
    {
        $interval = strtolower((string) $price->interval);
        $intervalCount = (int) ($price->interval_count ?: 1);

        $payload = [
            'product_id' => $providerProductId,
            'description' => $price->label ?: ucfirst($price->key ?: $price->interval ?: 'price'),
            'unit_price' => [
                'amount' => (string) ((int) $price->amount),
                'currency_code' => strtoupper((string) $price->currency),
            ],
            'custom_data' => array_filter([
                'product_key' => (string) $product->key,
                'price_key' => (string) ($price->key ?: $price->interval),
            ], fn ($value) => $value !== ''),
        ];

        if ($this->isRecurringInterval($interval)) {
            $payload['billing_cycle'] = [
                'interval' => $interval,
                'frequency' => max($intervalCount, 1),
            ];
        }

        return $payload;
    }

    private function isRecurringInterval(string $interval): bool
    {
        return in_array($interval, ['day', 'week', 'month', 'year'], true);
    }
}
