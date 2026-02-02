<?php

namespace App\Domain\Billing\Adapters\Stripe\Concerns;

use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingPlanService;
use Illuminate\Support\Carbon;

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

                // Self-heal: Create the mapping immediately
                $this->syncBillingCustomer($userId, $customerId, $customer->email);

                return $userId;
            }
        } catch (\Throwable $e) {
            // Settle for null if API fails
            report($e);
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

    /**
     * Resolve plan key from webhook object.
     */
    protected function resolvePlanKey(array $object): ?string
    {
        $planKey = data_get($object, 'metadata.plan_key')
            ?? data_get($object, 'metadata.planKey');

        if ($planKey) {
            return (string) $planKey;
        }

        $priceId = data_get($object, 'items.data.0.price.id')
            ?? data_get($object, 'line_items.data.0.price.id')
            ?? data_get($object, 'plan.id');

        if (! $priceId) {
            return null;
        }

        return $this->planKeyForPriceId($priceId);
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
    protected function syncBillingCustomer(int $userId, ?string $providerId, ?string $email): void
    {
        $payload = [
            'user_id' => $userId,
            'provider' => $this->provider(),
            'provider_id' => $providerId,
            'email' => $email,
        ];

        $customer = BillingCustomer::query()
            ->where('user_id', $userId)
            ->where('provider', $this->provider())
            ->first();

        if ($customer) {
            $customer->update(array_filter($payload, fn ($value) => $value !== null));

            return;
        }

        BillingCustomer::query()->create($payload);
    }
}
