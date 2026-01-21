<?php

namespace App\Domain\Billing\Adapters\Paddle\Handlers;

use App\Domain\Billing\Adapters\Paddle\Concerns\ResolvesPaddleData;
use App\Domain\Billing\Contracts\PaddleWebhookHandler;
use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Organization\Models\Team;
use App\Models\User;

use Illuminate\Support\Arr;

/**
 * Handles Paddle subscription lifecycle webhook events.
 *
 * Processes: subscription.created, subscription.updated, subscription.canceled, etc.
 */
class PaddleSubscriptionHandler implements PaddleWebhookHandler
{
    use ResolvesPaddleData;

    public function eventTypes(): array
    {
        return [
            'subscription.created',
            'subscription.updated',
            'subscription.activated',
            'subscription.canceled',
            'subscription.paused',
            'subscription.resumed',
            'subscription.past_due',
            'subscription.trialing',
        ];
    }

    public function handle(WebhookEvent $event, array $data): void
    {
        $this->syncSubscription($data);
    }

    /**
     * Sync a Paddle subscription to the local database.
     */
    public function syncSubscription(array $data): ?Subscription
    {
        $subscriptionId = data_get($data, 'id') ?? data_get($data, 'subscription_id');
        $teamId = $this->resolveTeamId($data);

        if (!$subscriptionId || !$teamId) {
            return null;
        }

        $existingSubscription = Subscription::query()
            ->where('provider', 'paddle')
            ->where('provider_id', (string) $subscriptionId)
            ->first();

        $previousPlanKey = $existingSubscription?->plan_key;

        $planKey = $this->resolvePlanKey($data) ?? 'unknown';
        $status = (string) (data_get($data, 'status') ?? data_get($data, 'state') ?? 'active');
        $quantity = (int) (data_get($data, 'quantity') ?? data_get($data, 'items.0.quantity') ?? 1);

        $canceledAt = $this->timestampToDateTime(data_get($data, 'canceled_at'));
        $scheduledChange = data_get($data, 'scheduled_change');
        $scheduledAction = data_get($scheduledChange, 'action');
        $scheduledCancelAt = $scheduledAction === 'cancel'
            ? $this->timestampToDateTime(data_get($scheduledChange, 'effective_at'))
            : null;

        if (!$canceledAt && $scheduledCancelAt) {
            $canceledAt = $existingSubscription?->canceled_at ?? now();
        }

        $endsAt = $scheduledCancelAt ?? $canceledAt;

        $subscription = Subscription::query()->updateOrCreate(
            [
                'provider' => 'paddle',
                'provider_id' => (string) $subscriptionId,
            ],
            [
                'team_id' => $teamId,
                'plan_key' => $planKey,
                'status' => $status,
                'quantity' => max($quantity, 1),
                'trial_ends_at' => $this->timestampToDateTime(data_get($data, 'trial_ends_at')),
                'renews_at' => $this->timestampToDateTime(data_get($data, 'next_billed_at')),
                'ends_at' => $endsAt,
                'canceled_at' => $canceledAt,
                'metadata' => Arr::only($data, ['id', 'status', 'items', 'custom_data', 'management_urls', 'customer_id', 'customer']),
            ]
        );

        $this->syncBillingCustomer(
            $teamId,
            data_get($data, 'customer_id') ?? data_get($data, 'customer.id'),
            data_get($data, 'customer_email')
        );

        $this->recordDiscountRedemption(
            $data,
            $teamId,
            $planKey,
            data_get($data, 'custom_data.price_key'),
            (string) $subscriptionId
        );

        // Dispatch Notifications
        $team = $subscription->team;
        $owner = $team?->owner;

        if ($owner) {
             // Notifications are now handled by SubscriptionObserver
        }

        return $subscription;
    }

    /**
     * Sync billing customer from webhook data.
     */
    private function syncBillingCustomer(int $teamId, ?string $providerId, ?string $email): void
    {
        if ($providerId) {
            BillingCustomer::query()->updateOrCreate(
                [
                    'provider' => 'paddle',
                    'provider_id' => $providerId,
                ],
                [
                    'team_id' => $teamId,
                    'email' => $email,
                ]
            );
            return;
        }

        BillingCustomer::query()->updateOrCreate(
            [
                'team_id' => $teamId,
                'provider' => 'paddle',
            ],
            [
                'email' => $email,
            ]
        );
    }

    /**
     * Record discount redemption from webhook data.
     */
    private function recordDiscountRedemption(
        array $data,
        int $teamId,
        ?string $planKey,
        ?string $priceKey,
        string $providerId
    ): void {
        $customData = data_get($data, 'custom_data', []);
        $discountId = $customData['discount_id'] ?? data_get($data, 'metadata.discount_id');
        $discountCode = $customData['discount_code'] ?? data_get($data, 'metadata.discount_code');

        if (!$discountId && !$discountCode) {
            return;
        }

        $discount = null;

        if ($discountId) {
            $discount = Discount::query()->find($discountId);
        } elseif ($discountCode) {
            $discount = Discount::query()
                ->where('provider', 'paddle')
                ->where('code', strtoupper((string) $discountCode))
                ->first();
        }

        if (!$discount) {
            return;
        }

        $team = Team::find($teamId);

        if (!$team) {
            return;
        }

        $userId = $customData['user_id'] ?? null;
        $user = $userId ? User::find($userId) : null;

        app(DiscountService::class)->recordRedemption(
            $discount,
            $team,
            $user,
            'paddle',
            $providerId,
            $planKey,
            $priceKey,
            [
                'source' => 'paddle_webhook',
            ]
        );
    }

    private function resolvePlanName(?string $planKey): string
    {
        if (!$planKey) {
            return 'subscription';
        }

        try {
            $plan = app(\App\Domain\Billing\Services\BillingPlanService::class)->plan($planKey);

            return $plan['name'] ?? ucfirst($planKey);
        } catch (\Throwable) {
            return ucfirst($planKey);
        }
    }
}
