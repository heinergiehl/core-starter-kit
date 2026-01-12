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
        $productsResponse = $this->apiGet($apiKey, '/products', $storeId ? ['filter[store_id]' => $storeId] : []);

        if (!$productsResponse->successful()) {
            throw new RuntimeException('Failed to fetch Lemon Squeezy products: ' . $productsResponse->body());
        }

        $products = $productsResponse->json('data') ?? [];

        // Fetch all variants
        $variantsResponse = $this->apiGet($apiKey, '/variants');

        if (!$variantsResponse->successful()) {
            throw new RuntimeException('Failed to fetch Lemon Squeezy variants: ' . $variantsResponse->body());
        }

        $allVariants = collect($variantsResponse->json('data') ?? []);

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

    private function normalizeProductPlan(array $product, array $attributes, array $customData, array $prices): array
    {
        $productKey = $this->resolveProductKey($customData, $product);
        $planKey = $this->resolvePlanKey($product, $customData);
        $planType = $this->resolvePlanType($customData, $prices);

        return [
            'product' => [
                'key' => $productKey,
                'name' => $customData['product_name'] ?? $attributes['name'] ?? 'LemonSqueezy Product',
                'description' => $attributes['description'] ?? '',
                'is_active' => ($attributes['status'] ?? 'published') === 'published',
            ],
            'plan' => [
                'key' => $planKey,
                'name' => $attributes['name'] ?? 'LemonSqueezy Plan',
                'summary' => $customData['summary'] ?? null,
                'description' => $attributes['description'] ?? '',
                'type' => $planType,
                'seat_based' => $this->boolFromMetadata($customData['seat_based'] ?? null),
                'max_seats' => $this->intFromMetadata($customData['max_seats'] ?? null),
                'is_featured' => $this->boolFromMetadata($customData['featured'] ?? null),
                'features' => $this->parseFeatures($customData['features'] ?? null),
                'entitlements' => $this->parseEntitlements($customData['entitlements'] ?? null),
                'is_active' => ($attributes['status'] ?? 'published') === 'published',
            ],
            'prices' => $prices,
        ];
    }

    private function normalizeVariant(array $variant, array $product, array $productCustomData): ?array
    {
        $attributes = $variant['attributes'] ?? [];
        $price = (int) ($attributes['price'] ?? 0);

        if ($price === 0 && !($attributes['is_subscription'] ?? false)) {
            return null;
        }

        $isSubscription = $attributes['is_subscription'] ?? false;
        $interval = $isSubscription ? ($attributes['interval'] ?? 'month') : 'once';
        $intervalCount = (int) ($attributes['interval_count'] ?? 1);
        $trialDays = (int) ($attributes['trial_interval_count'] ?? 0);

        $variantCustomData = $attributes['custom_data'] ?? [];
        $priceKey = $this->resolvePriceKey($variant, $variantCustomData, $interval, $intervalCount);
        $label = $attributes['name'] ?? ucfirst($priceKey);

        return [
            'provider' => $this->provider(),
            'provider_id' => (string) $variant['id'],
            'key' => $priceKey,
            'label' => $label,
            'interval' => (string) $interval,
            'interval_count' => $intervalCount,
            'currency' => 'USD', // LemonSqueezy stores in cents, currency is at store level
            'amount' => $price,
            'type' => 'flat',
            'has_trial' => $trialDays > 0,
            'trial_interval' => $trialDays > 0 ? ($attributes['trial_interval'] ?? 'day') : null,
            'trial_interval_count' => $trialDays > 0 ? $trialDays : null,
            'is_active' => ($attributes['status'] ?? 'published') === 'published',
        ];
    }

    private function resolveProductKey(array $customData, array $product): string
    {
        $productKey = $customData['product_key'] ?? null;

        if ($productKey) {
            return Str::slug((string) $productKey, '-');
        }

        return Str::slug('ls-' . ($product['id'] ?? 'product'), '-');
    }

    private function resolvePlanKey(array $product, array $customData): string
    {
        $planKey = $customData['plan_key'] ?? null;

        if ($planKey) {
            return Str::slug((string) $planKey, '-');
        }

        return Str::slug('ls-' . ($product['id'] ?? 'plan'), '-');
    }

    private function resolvePlanType(array $customData, array $prices): string
    {
        $type = $customData['plan_type'] ?? $customData['type'] ?? null;

        if ($type && in_array($type, ['subscription', 'one_time'], true)) {
            return $type;
        }

        foreach ($prices as $price) {
            if (!empty($price['interval']) && $price['interval'] !== 'once') {
                return 'subscription';
            }
        }

        return 'one_time';
    }

    private function resolvePriceKey(array $variant, array $customData, string $interval, int $intervalCount): string
    {
        $priceKey = $customData['price_key'] ?? null;
        if ($priceKey) {
            return Str::slug((string) $priceKey, '-');
        }

        if ($interval === 'once') {
            return 'one_time';
        }

        if ($intervalCount === 1) {
            return match ($interval) {
                'month' => 'monthly',
                'year' => 'yearly',
                'week' => 'weekly',
                'day' => 'daily',
                default => Str::slug($interval, '-'),
            };
        }

        return Str::slug("every-{$intervalCount}-{$interval}", '-');
    }

    private function boolFromMetadata(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function intFromMetadata(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function parseFeatures(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function parseEntitlements(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function apiGet(string $apiKey, string $endpoint, array $query = []): \Illuminate\Http\Client\Response
    {
        return Http::withToken($apiKey)
            ->withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ])
            ->get("https://api.lemonsqueezy.com/v1{$endpoint}", $query);
    }
}
