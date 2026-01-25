<?php

namespace App\Domain\Billing\Adapters\Paddle\Handlers;

use App\Domain\Billing\Adapters\Paddle\Concerns\ResolvesPaddleData;
use App\Domain\Billing\Contracts\PaddleWebhookHandler;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\WebhookEvent;

/**
 * Handles Paddle price lifecycle webhook events.
 *
 * Processes: price.created, price.updated
 */
class PaddlePriceHandler implements PaddleWebhookHandler
{
    use ResolvesPaddleData;

    public function eventTypes(): array
    {
        return [
            'price.created',
            'price.updated',
        ];
    }

    public function handle(WebhookEvent $event, array $data): void
    {
        if (! config('saas.billing.sync_catalog_via_webhooks', true)) {
            return;
        }

        $this->syncPrice($data);
    }

    /**
     * Sync a Paddle price to the local database.
     */
    public function syncPrice(array $data): ?Price
    {
        $priceId = data_get($data, 'id');
        $productId = data_get($data, 'product_id');

        if (! $priceId) {
            return null;
        }

        $product = Product::query()
            ->whereHas('providerMappings', function ($q) use ($productId) {
                $q->where('provider', 'paddle')
                    ->where('provider_id', $productId);
            })
            ->first();

        if (! $product) {
            return null;
        }

        $customData = data_get($data, 'custom_data', []);
        $billingCycle = data_get($data, 'billing_cycle', []);
        $unitPrice = data_get($data, 'unit_price', []);

        $interval = $billingCycle['interval'] ?? 'once';
        $intervalCount = (int) ($billingCycle['frequency'] ?? 1);
        $key = $customData['price_key'] ?? $this->generatePriceKey($interval, $intervalCount);

        $mapping = PriceProviderMapping::where('provider', 'paddle')
            ->where('provider_id', (string) $priceId)
            ->first();

        if ($mapping && ! $mapping->price && ! config('saas.billing.allow_import_deleted', false)) {
            return null;
        }

        $status = data_get($data, 'status', 'active');
        if (! $mapping && $status !== 'active') {
            return null;
        }

        $priceData = [
            'product_id' => $product->id,
            'key' => $key,
            'label' => data_get($data, 'description', ucfirst($key)),
            'interval' => $interval,
            'interval_count' => $intervalCount,
            'currency' => strtoupper($unitPrice['currency_code'] ?? 'USD'),
            'amount' => (int) ($unitPrice['amount'] ?? 0),
            'type' => 'flat',
            'is_active' => $status === 'active',
        ];

        if ($mapping) {
            $mapping->price->update($priceData);

            return $mapping->price;
        }

        $price = Price::create($priceData);

        PriceProviderMapping::create([
            'price_id' => $price->id,
            'provider' => 'paddle',
            'provider_id' => (string) $priceId,
        ]);

        return $price;
    }

    /**
     * Generate a price key from interval.
     */
    private function generatePriceKey(string $interval, int $intervalCount): string
    {
        if ($interval === 'once') {
            return 'one_time';
        }

        if ($intervalCount === 1) {
            return "{$interval}ly";
        }

        return "every-{$intervalCount}-{$interval}";
    }
}
