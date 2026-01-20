<?php

namespace App\Domain\Billing\Imports;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\StripeClient;

class StripeCatalogImportAdapter implements CatalogImportAdapter
{
    public function provider(): string
    {
        return 'stripe';
    }

    private const PER_PAGE = 100;

    public function fetch(): array
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            throw new RuntimeException('Stripe secret is not configured.');
        }

        $client = new StripeClient($secret);
        $items = [];
        $warnings = [];

        foreach ($client->products->all(['limit' => self::PER_PAGE])->autoPagingIterator() as $product) {
            $productMetadata = $this->normalizeMetadata($product->metadata ?? []);

            $prices = [];
            foreach ($client->prices->all(['limit' => self::PER_PAGE, 'product' => $product->id])->autoPagingIterator() as $price) {
                $pricePayload = $this->normalizePrice($price, $product, $productMetadata);

                if (!$pricePayload) {
                    $warnings[] = "Skipped Stripe price {$price->id} for product {$product->id} because unit_amount is missing.";
                    continue;
                }

                $prices[] = $pricePayload;
            }

            $items[] = $this->normalizeProductPlan($product, $productMetadata, $prices);
        }

        return [
            'items' => $items,
            'warnings' => $warnings,
        ];
    }

    private function normalizeProductPlan(object $product, array $metadata, array $prices): array
    {
        $productKey = $this->resolveProductKey($metadata);
        $planKey = $this->resolvePlanKey($product, $metadata);
        $planType = $this->resolvePlanType($metadata, $prices);
        $productDescription = $metadata['product_description']
            ?? ($productKey === 'stripe' ? 'Imported from Stripe.' : (string) ($product->description ?? ''));

        return [
            'product' => [
                'provider_id' => $product->id,
                'key' => $productKey,
                'name' => $this->resolveProductName($productKey, $metadata, (string) $product->name),
                'description' => $productDescription,
                'is_active' => (bool) $product->active,
            ],
            'plan' => [
                'key' => $planKey,
                'name' => (string) $product->name,
                'summary' => $metadata['summary'] ?? null,
                'description' => (string) ($product->description ?? ''),
                'type' => $planType,
                'seat_based' => $this->boolFromMetadata($metadata['seat_based'] ?? $metadata['seatBased'] ?? null),
                'max_seats' => $this->intFromMetadata($metadata['max_seats'] ?? $metadata['maxSeats'] ?? null),
                'is_featured' => $this->boolFromMetadata($metadata['featured'] ?? $metadata['is_featured'] ?? null),
                'features' => $this->parseFeatures($metadata['features'] ?? null),
                'entitlements' => $this->parseEntitlements($metadata['entitlements'] ?? null),
                'is_active' => (bool) $product->active,
            ],
            'prices' => $prices,
        ];
    }

    private function normalizePrice(object $price, object $product, array $productMetadata): ?array
    {
        $amount = $this->resolveAmount($price);

        if ($amount === null) {
            return null;
        }

        $interval = $price->recurring?->interval;
        $intervalCount = $price->recurring?->interval_count ?? 1;
        $trialDays = (int) ($price->recurring?->trial_period_days ?? 0);

        if (!$interval) {
            $interval = 'once';
            $intervalCount = 1;
        }

        $priceKey = $this->resolvePriceKey($price, $interval, $intervalCount);
        $label = $this->resolvePriceLabel($price, $priceKey, (string) $product->name);

        return [
            'provider' => $this->provider(),
            'provider_id' => (string) $price->id,
            'key' => $priceKey,
            'label' => $label,
            'interval' => (string) $interval,
            'interval_count' => (int) $intervalCount,
            'currency' => strtoupper((string) $price->currency),
            'amount' => $amount,
            'type' => 'flat',
            'has_trial' => $trialDays > 0,
            'trial_interval' => $trialDays > 0 ? 'day' : null,
            'trial_interval_count' => $trialDays > 0 ? $trialDays : null,
            'is_active' => (bool) $price->active,
        ];
    }

    private function normalizeMetadata(array $metadata): array
    {
        return Arr::map($metadata, function ($value) {
            if (is_string($value)) {
                return trim($value);
            }

            return $value;
        });
    }

    private function resolveProductKey(array $metadata): string
    {
        $productKey = $metadata['product_key'] ?? $metadata['productKey'] ?? null;

        if ($productKey) {
            return Str::slug((string) $productKey, '-');
        }

        return 'stripe';
    }

    private function resolveProductName(string $productKey, array $metadata, string $fallbackName): string
    {
        $name = $metadata['product_name'] ?? $metadata['productName'] ?? null;

        if ($name) {
            return (string) $name;
        }

        if ($productKey === 'stripe') {
            return 'Stripe Catalog';
        }

        return $fallbackName ?: 'Stripe Product';
    }

    private function resolvePlanKey(object $product, array $metadata): string
    {
        $planKey = $metadata['plan_key'] ?? $metadata['planKey'] ?? null;

        if ($planKey) {
            return Str::slug((string) $planKey, '-');
        }

        return Str::slug('stripe_'.$product->id, '-');
    }

    private function resolvePlanType(array $metadata, array $prices): string
    {
        $type = $metadata['plan_type'] ?? $metadata['type'] ?? null;

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

    private function resolvePriceKey(object $price, string $interval, int $intervalCount): string
    {
        $lookupKey = $price->lookup_key ?? null;
        if ($lookupKey) {
            return Str::slug((string) $lookupKey, '-');
        }

        $metadataKey = $price->metadata['price_key'] ?? $price->metadata['key'] ?? null;
        if ($metadataKey) {
            return Str::slug((string) $metadataKey, '-');
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

    private function resolvePriceLabel(object $price, string $priceKey, string $planName): string
    {
        if (!empty($price->nickname)) {
            return (string) $price->nickname;
        }

        return ucfirst($priceKey ?: $planName);
    }

    private function resolveAmount(object $price): ?int
    {
        if (isset($price->unit_amount) && $price->unit_amount !== null) {
            return (int) $price->unit_amount;
        }

        if (isset($price->unit_amount_decimal) && is_numeric($price->unit_amount_decimal)) {
            return (int) $price->unit_amount_decimal;
        }

        return null;
    }

    private function boolFromMetadata(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
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

        $lines = preg_split('/\r?\n/', $value) ?: [];
        $items = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode(',', $line));
            foreach ($parts as $part) {
                if ($part !== '') {
                    $items[] = $part;
                }
            }
        }

        return array_values(array_unique($items));
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
