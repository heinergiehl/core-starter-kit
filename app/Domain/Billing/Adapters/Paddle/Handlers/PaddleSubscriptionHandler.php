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

        $planKey = $this->resolvePlanKey($data) ?? 'unknown';
        $status = (string) (data_get($data, 'status') ?? data_get($data, 'state') ?? 'active');
        $quantity = (int) (data_get($data, 'quantity') ?? data_get($data, 'items.0.quantity') ?? 1);

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
                'ends_at' => $this->timestampToDateTime(data_get($data, 'canceled_at')),
                'canceled_at' => $this->timestampToDateTime(data_get($data, 'canceled_at')),
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
            // 1. Welcome / Started Notification
            if ($subscription->status === 'active' && !$subscription->welcome_email_sent_at) {
                // Parse Paddle price/currency which might be in items or custom data
                $amount = (int) (data_get($data, 'items.0.price.unit_price.amount') ?? 0);
                $currency = (string) (data_get($data, 'currency_code') ?? 'USD');

                $owner->notify(new \App\Notifications\SubscriptionStartedNotification(
                    planName: ucfirst($planKey),
                    amount: $amount,
                    currency: strtoupper($currency),
                ));

                $subscription->forceFill(['welcome_email_sent_at' => now()])->save();
            }

            // 2. Cancellation Notification
            if ($subscription->status === 'canceled' && !$subscription->cancellation_email_sent_at) {
                $owner->notify(new \App\Notifications\SubscriptionCancelledNotification(
                    planName: ucfirst($planKey),
                    accessUntil: $subscription->ends_at?->format('F j, Y'),
                ));

                $subscription->forceFill(['cancellation_email_sent_at' => now()])->save();
            }
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
}
