<?php

namespace App\Domain\Billing\Adapters\Paddle\Concerns;

use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Services\BillingPlanService;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Shared methods for resolving Paddle webhook data.
 */
trait ResolvesPaddleData
{
    /**
     * Resolve user ID from webhook data.
     */
    protected function resolveUserId(array $data): ?int
    {
        $userId = data_get($data, 'custom_data.user_id')
            ?? data_get($data, 'metadata.user_id')
            ?? data_get($data, 'user_id');

        if (! $userId) {
            $customerId = data_get($data, 'customer_id') ?? data_get($data, 'customer.id');
            if ($customerId) {
                $mappedUserId = BillingCustomer::query()
                    ->where('provider', \App\Enums\BillingProvider::Paddle->value)
                    ->where('provider_id', (string) $customerId)
                    ->value('user_id');

                if ($mappedUserId) {
                    return (int) $mappedUserId;
                }
            }

            return null;
        }

        $userId = (int) $userId;

        if (! User::query()->whereKey($userId)->exists()) {
            Log::warning('Paddle webhook references missing user', [
                'user_id' => $userId,
                'event_id' => data_get($data, 'id') ?? data_get($data, 'subscription_id') ?? data_get($data, 'transaction_id'),
            ]);

            return null;
        }

        return $userId;
    }

    /**
     * Resolve plan key from webhook data.
     */
    protected function resolvePlanKey(array $data): ?string
    {
        $planKey = data_get($data, 'custom_data.plan_key')
            ?? data_get($data, 'metadata.plan_key')
            ?? data_get($data, 'plan_key');

        if ($planKey) {
            return (string) $planKey;
        }

        $priceId = data_get($data, 'items.0.price_id') ?? data_get($data, 'price_id');

        if (! $priceId) {
            return null;
        }

        return app(BillingPlanService::class)
            ->resolvePlanKeyByProviderId(\App\Enums\BillingProvider::Paddle->value, $priceId);
    }

    /**
     * Convert Paddle timestamp to Carbon instance.
     */
    protected function timestampToDateTime(?string $timestamp): ?Carbon
    {
        if (! $timestamp) {
            return null;
        }

        if (is_numeric($timestamp)) {
            return now()->setTimestamp((int) $timestamp);
        }

        return Carbon::parse($timestamp);
    }
}
