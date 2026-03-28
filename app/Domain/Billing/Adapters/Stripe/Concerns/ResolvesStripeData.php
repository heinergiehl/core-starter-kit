<?php

namespace App\Domain\Billing\Adapters\Stripe\Concerns;

use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Identity\Models\Account;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Shared helper methods for Stripe webhook handlers.
 *
 * Provides common functionality for resolving user IDs, plan keys,
 * and syncing billing customers across all Stripe webhook handlers.
 */
trait ResolvesStripeData
{
    /**
     * Get the Stripe provider identifier.
     */
    protected function provider(): string
    {
        return \App\Enums\BillingProvider::Stripe->value;
    }

    /**
     * Resolve user ID from webhook object metadata.
     */
    protected function resolveUserIdFromMetadata(array $object): ?int
    {
        $userId = data_get($object, 'metadata.user_id');

        return $userId ? (int) $userId : null;
    }

    protected function resolveAccountIdFromMetadata(array $object): ?int
    {
        $accountId = data_get($object, 'metadata.account_id');

        return $accountId ? (int) $accountId : null;
    }

    /**
     * Resolve user ID from Stripe customer ID.
     */
    protected function resolveUserIdFromCustomerId(?string $customerId): ?int
    {
        if (! $customerId) {
            return null;
        }

        // 1. Try local lookup
        $userId = BillingCustomer::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $customerId)
            ->value('user_id');

        if ($userId) {
            return $userId;
        }

        // 2. Fallback: Fetch from Stripe to fix race conditions
        // (If Invoice webhook arrives before Checkout webhook)
        try {
            $secret = config('services.stripe.secret');
            if (! $secret) {
                return null;
            }

            $stripe = new \Stripe\StripeClient($secret);
            $customer = $stripe->customers->retrieve($customerId);

            if ($customer && isset($customer->metadata['user_id'])) {
                $userId = (int) $customer->metadata['user_id'];
                $accountId = isset($customer->metadata['account_id'])
                    ? (int) $customer->metadata['account_id']
                    : $this->resolvePersonalAccountIdForUserId($userId);

                // Self-heal: Create the mapping immediately
                $this->syncBillingCustomer($userId, $accountId, $customerId, $customer->email);

                return $userId;
            }
        } catch (\Throwable $e) {
            // Settle for null if API fails
            report($e);
        }

        return null;
    }

    protected function resolveAccountIdFromCustomerId(?string $customerId): ?int
    {
        if (! $customerId) {
            return null;
        }

        $accountId = BillingCustomer::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $customerId)
            ->value('account_id');

        if ($accountId) {
            return (int) $accountId;
        }

        return null;
    }

    /**
     * Resolve user ID from Stripe subscription ID.
     */
    protected function resolveUserIdFromSubscriptionId(?string $subscriptionId): ?int
    {
        if (! $subscriptionId) {
            return null;
        }

        return Subscription::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $subscriptionId)
            ->value('user_id');
    }

    protected function resolveAccountIdFromSubscriptionId(?string $subscriptionId): ?int
    {
        if (! $subscriptionId) {
            return null;
        }

        $accountId = Subscription::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $subscriptionId)
            ->value('account_id');

        return $accountId ? (int) $accountId : null;
    }

    /**
     * Resolve plan key from webhook object.
     */
    protected function resolvePlanKey(array $object): ?string
    {
        $priceId = data_get($object, 'items.data.0.price.id')
            ?? data_get($object, 'line_items.data.0.price.id')
            ?? data_get($object, 'plan.id');

        if ($priceId) {
            $resolvedPlanKey = $this->planKeyForPriceId($priceId);

            if ($resolvedPlanKey) {
                return $resolvedPlanKey;
            }
        }

        $planKey = data_get($object, 'metadata.plan_key')
            ?? data_get($object, 'metadata.planKey');

        return $planKey ? (string) $planKey : null;
    }

    /**
     * Get plan key for a Stripe price ID.
     */
    protected function planKeyForPriceId(string $priceId): ?string
    {
        return app(BillingPlanService::class)
            ->resolvePlanKeyByProviderId($this->provider(), $priceId);
    }

    /**
     * Convert Unix timestamp to Carbon instance.
     */
    protected function timestampToDateTime(?int $timestamp): ?Carbon
    {
        if (! $timestamp) {
            return null;
        }

        return now()->setTimestamp($timestamp);
    }

    /**
     * Sync or create billing customer record.
     */
    protected function syncBillingCustomer(int $userId, ?int $accountId, ?string $providerId, ?string $email): void
    {
        if ($providerId) {
            $existing = BillingCustomer::query()
                ->where('provider', $this->provider())
                ->where('provider_id', $providerId)
                ->first();

            $hasConflict = $existing
                && ($existing->user_id !== $userId
                    || ($accountId && (int) $existing->account_id !== $accountId));

            if ($hasConflict) {
                Log::warning('Stripe webhook customer id already mapped', [
                    'provider_id' => $providerId,
                    'existing_user_id' => $existing->user_id,
                    'incoming_user_id' => $userId,
                    'existing_account_id' => $existing->account_id,
                    'incoming_account_id' => $accountId,
                ]);

                return;
            }

            BillingCustomer::query()->updateOrCreate(
                [
                    'provider' => $this->provider(),
                    'provider_id' => $providerId,
                ],
                [
                    'user_id' => $userId,
                    'account_id' => $accountId,
                    'email' => $email,
                ]
            );

            return;
        }

        $attributes = ['provider' => $this->provider()];
        if ($accountId) {
            $attributes['account_id'] = $accountId;
        } else {
            $attributes['user_id'] = $userId;
        }

        BillingCustomer::query()->updateOrCreate($attributes, [
            'user_id' => $userId,
            'account_id' => $accountId,
            'email' => $email,
        ]);
    }

    protected function resolvePersonalAccountIdForUserId(?int $userId): ?int
    {
        return Account::resolvePersonalAccountIdForUserId($userId);
    }
}
