<?php

namespace App\Domain\Billing\Imports;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaddleCatalogImportAdapter implements CatalogImportAdapter
{
    public function provider(): string
    {
        return 'paddle';
    }

    public function fetch(): array
    {
        $apiKey = config('services.paddle.api_key');

        if (!$apiKey) {
            throw new RuntimeException('Paddle API key is not configured.');
        }

        $items = [];
        $warnings = [];

        // Fetch products
        $productsResponse = Http::withToken($apiKey)
            ->acceptJson()
            ->get('https://api.paddle.com/products', ['per_page' => 200]);

        if (!$productsResponse->successful()) {
            throw new RuntimeException('Failed to fetch Paddle products: ' . $productsResponse->body());
        }

        $products = $productsResponse->json('data') ?? [];

        // Fetch all prices
        $pricesResponse = Http::withToken($apiKey)
            ->acceptJson()
            ->get('https://api.paddle.com/prices', ['per_page' => 200]);

        if (!$pricesResponse->successful()) {
            throw new RuntimeException('Failed to fetch Paddle prices: ' . $pricesResponse->body());
        }

        $allPrices = collect($pricesResponse->json('data') ?? []);

        foreach ($products as $product) {
            $productId = $product['id'] ?? null;
            if (!$productId) {
                continue;
            }

            $customData = $product['custom_data'] ?? [];
            $productPrices = $allPrices->filter(fn ($p) => ($p['product_id'] ?? null) === $productId);

            $prices = [];
            foreach ($productPrices as $price) {
                $pricePayload = $this->normalizePrice($price, $product, $customData);

                if (!$pricePayload) {
                    $warnings[] = "Skipped Paddle price {$price['id']} for product {$productId} because amount is missing.";
                    continue;
                }

                $prices[] = $pricePayload;
            }

            $items[] = $this->normalizeProductPlan($product, $customData, $prices);
        }

        return [
            'items' => $items,
            'warnings' => $warnings,
        ];
    }

    private function normalizeProductPlan(array $product, array $customData, array $prices): array
    {
        $productKey = $this->resolveProductKey($customData, $product);
        $planKey = $this->resolvePlanKey($product, $customData);
        $planType = $this->resolvePlanType($customData, $prices);

        return [
            'product' => [
                'key' => $productKey,
                'name' => $customData['product_name'] ?? $product['name'] ?? 'Paddle Product',
                'description' => $product['description'] ?? '',
                'is_active' => ($product['status'] ?? 'active') === 'active',
            ],
            'plan' => [
                'key' => $planKey,
                'name' => $product['name'] ?? 'Paddle Plan',
                'summary' => $customData['summary'] ?? null,
                'description' => $product['description'] ?? '',
                'type' => $planType,
                'seat_based' => $this->boolFromMetadata($customData['seat_based'] ?? null),
                'max_seats' => $this->intFromMetadata($customData['max_seats'] ?? null),
                'is_featured' => $this->boolFromMetadata($customData['featured'] ?? null),
                'features' => $this->parseFeatures($customData['features'] ?? null),
                'entitlements' => $this->parseEntitlements($customData['entitlements'] ?? null),
                'is_active' => ($product['status'] ?? 'active') === 'active',
            ],
            'prices' => $prices,
        ];
    }

    private function normalizePrice(array $price, array $product, array $customData): ?array
    {
        $unitPrice = $price['unit_price'] ?? [];
        $amount = (int) ($unitPrice['amount'] ?? 0);

        if ($amount === 0) {
            return null;
        }

        $billingCycle = $price['billing_cycle'] ?? null;
        $interval = $billingCycle['interval'] ?? 'once';
        $intervalCount = (int) ($billingCycle['frequency'] ?? 1);
        $trialDays = (int) ($price['trial_period']['frequency'] ?? 0);

        $priceCustomData = $price['custom_data'] ?? [];
        $priceKey = $this->resolvePriceKey($price, $priceCustomData, $interval, $intervalCount);
        $label = $price['description'] ?? ucfirst($priceKey);

        return [
            'provider' => $this->provider(),
            'provider_id' => (string) $price['id'],
            'key' => $priceKey,
            'label' => $label,
            'interval' => (string) $interval,
            'interval_count' => $intervalCount,
            'currency' => strtoupper($unitPrice['currency_code'] ?? 'USD'),
            'amount' => $amount,
            'type' => 'flat',
            'has_trial' => $trialDays > 0,
            'trial_interval' => $trialDays > 0 ? 'day' : null,
            'trial_interval_count' => $trialDays > 0 ? $trialDays : null,
            'is_active' => ($price['status'] ?? 'active') === 'active',
        ];
    }

    private function resolveProductKey(array $customData, array $product): string
    {
        $productKey = $customData['product_key'] ?? null;

        if ($productKey) {
            return Str::slug((string) $productKey, '-');
        }

        return Str::slug('paddle-' . ($product['id'] ?? 'product'), '-');
    }

    private function resolvePlanKey(array $product, array $customData): string
    {
        $planKey = $customData['plan_key'] ?? null;

        if ($planKey) {
            return Str::slug((string) $planKey, '-');
        }

        return Str::slug('paddle-' . ($product['id'] ?? 'plan'), '-');
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

    private function resolvePriceKey(array $price, array $customData, string $interval, int $intervalCount): string
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
}
