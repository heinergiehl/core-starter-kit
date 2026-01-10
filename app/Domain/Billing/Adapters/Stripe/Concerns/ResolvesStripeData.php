<?php

namespace App\Domain\Billing\Adapters\Stripe\Concerns;

use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingPlanService;
use Illuminate\Support\Carbon;

/**
 * Shared helper methods for Stripe webhook handlers.
 *
 * Provides common functionality for resolving team IDs, plan keys,
 * and syncing billing customers across all Stripe webhook handlers.
 */
trait ResolvesStripeData
{
    /**
     * Get the Stripe provider identifier.
     */
    protected function provider(): string
    {
        return 'stripe';
    }

    /**
     * Resolve team ID from webhook object metadata.
     */
    protected function resolveTeamIdFromMetadata(array $object): ?int
    {
        $teamId = data_get($object, 'metadata.team_id');

        return $teamId ? (int) $teamId : null;
    }

    /**
     * Resolve team ID from Stripe customer ID.
     */
    protected function resolveTeamIdFromCustomerId(?string $customerId): ?int
    {
        if (!$customerId) {
            return null;
        }

        return BillingCustomer::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $customerId)
            ->value('team_id');
    }

    /**
     * Resolve team ID from Stripe subscription ID.
     */
    protected function resolveTeamIdFromSubscriptionId(?string $subscriptionId): ?int
    {
        if (!$subscriptionId) {
            return null;
        }

        return Subscription::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $subscriptionId)
            ->value('team_id');
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

        if (!$priceId) {
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
        if (!$timestamp) {
            return null;
        }

        return now()->setTimestamp($timestamp);
    }

    /**
     * Sync or create billing customer record.
     */
    protected function syncBillingCustomer(int $teamId, ?string $providerId, ?string $email): void
    {
        $payload = [
            'team_id' => $teamId,
            'provider' => $this->provider(),
            'provider_id' => $providerId,
            'email' => $email,
        ];

        $customer = BillingCustomer::query()
            ->where('team_id', $teamId)
            ->where('provider', $this->provider())
            ->first();

        if ($customer) {
            $customer->update(array_filter($payload, fn ($value) => $value !== null));
            return;
        }

        BillingCustomer::query()->create($payload);
    }
}
