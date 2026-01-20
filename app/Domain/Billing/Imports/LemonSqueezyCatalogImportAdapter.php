<?php

namespace App\Domain\Billing\Imports;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class LemonSqueezyCatalogImportAdapter implements CatalogImportAdapter
{
    public function provider(): string
    {
        return 'lemonsqueezy';
    }

    private const PER_PAGE = 100;
    private const MAX_RETRIES = 3;
    private const RETRY_SLEEP_MS = 200;

    public function fetch(): array
    {
        $apiKey = config('services.lemonsqueezy.api_key');
        $storeId = config('services.lemonsqueezy.store_id');

        if (!$apiKey) {
            throw new RuntimeException('Lemon Squeezy API key is not configured.');
        }

        $items = [];
        $warnings = [];

        // Fetch products
        $products = [];
        // Start with relative path for first request
        $nextUrl = '/products';
        $queryParams = $storeId ? ['filter[store_id]' => $storeId, 'page[size]' => self::PER_PAGE] : ['page[size]' => self::PER_PAGE];

        do {
            $response = $this->apiGet($apiKey, $nextUrl, $queryParams);

            if (!$response->successful()) {
                throw new RuntimeException('Failed to fetch Lemon Squeezy products: ' . $response->body());
            }

            $data = $response->json();
            $products = array_merge($products, $data['data'] ?? []);

            $nextUrl = $data['links']['next'] ?? null;
            // Clear query params because nextUrl from LS API includes them
            $queryParams = [];
        } while ($nextUrl);


        // Fetch all variants
        $allVariants = collect();
        $nextUrl = '/variants';
        $queryParams = ['page[size]' => self::PER_PAGE];

        do {
            $response = $this->apiGet($apiKey, $nextUrl, $queryParams);

            if (!$response->successful()) {
                throw new RuntimeException('Failed to fetch Lemon Squeezy variants: ' . $response->body());
            }

            $data = $response->json();
            $allVariants = $allVariants->merge($data['data'] ?? []);

            $nextUrl = $data['links']['next'] ?? null;
            $queryParams = [];
        } while ($nextUrl);

        foreach ($products as $product) {
            $productId = $product['id'] ?? null;
            if (!$productId) {
                continue;
            }

            $attributes = $product['attributes'] ?? [];
            $customData = $attributes['custom_data'] ?? [];

            // Find variants for this product
            $productVariants = $allVariants->filter(function ($variant) use ($productId) {
                $relationships = $variant['relationships'] ?? [];
                $productRel = $relationships['product']['data']['id'] ?? null;
                return $productRel === $productId;
            });

            $prices = [];
            foreach ($productVariants as $variant) {
                $pricePayload = $this->normalizeVariant($variant, $product, $customData);

                if (!$pricePayload) {
                    $warnings[] = "Skipped Lemon Squeezy variant {$variant['id']} for product {$productId} because price is missing.";
                    continue;
                }

                $prices[] = $pricePayload;
            }

            $items[] = $this->normalizeProductPlan($product, $attributes, $customData, $prices);
        }

        return [
            'items' => $items,
            'warnings' => $warnings,
        ];
    }

    // ... (keep normalization methods as is, they are fine)

    private function apiGet(string $apiKey, string $endpoint, array $query = []): \Illuminate\Http\Client\Response
    {
        // Check if endpoint is a full URL (starts with http)
        if (str_starts_with($endpoint, 'http')) {
            $url = $endpoint;
        } else {
            // Ensure endpoint starts with slash if not present
            if (!str_starts_with($endpoint, '/')) {
                $endpoint = '/' . $endpoint;
            }
            $url = "https://api.lemonsqueezy.com/v1{$endpoint}";
        }
        
        return Http::withToken($apiKey)
            ->withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ])
            ->retry(self::MAX_RETRIES, self::RETRY_SLEEP_MS)
            ->get($url, $query);
    }
}
