<?php

namespace App\Domain\Billing\Adapters\Paddle\Handlers;

use App\Domain\Billing\Adapters\Paddle\Concerns\ResolvesPaddleData;
use App\Domain\Billing\Contracts\PaddleWebhookHandler;
use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\DiscountService;
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

    public function __construct(
        protected \App\Domain\Billing\Services\SubscriptionService $subscriptionService
    ) {}

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
        $userId = $this->resolveUserId($data);

        if (! $subscriptionId || ! $userId) {
            return null;
        }

        $planKey = $this->resolvePlanKey($data) ?? 'unknown';
        $status = (string) (data_get($data, 'status') ?? data_get($data, 'state') ?? 'active');
        $quantity = (int) (data_get($data, 'quantity') ?? data_get($data, 'items.0.quantity') ?? 1);

        $canceledAt = $this->timestampToDateTime(data_get($data, 'canceled_at'));
        $scheduledChange = data_get($data, 'scheduled_change');
        $scheduledAction = data_get($scheduledChange, 'action');
        $scheduledCancelAt = $scheduledAction === 'cancel'
            ? $this->timestampToDateTime(data_get($scheduledChange, 'effective_at'))
            : null;

        $endsAt = $scheduledCancelAt ?? $canceledAt;

        $subscription = $this->subscriptionService->syncFromProvider(
            provider: 'paddle',
            providerId: (string) $subscriptionId,
            userId: $userId,
            planKey: $planKey,
            status: $status,
            quantity: max($quantity, 1),
            dates: [
                'trial_ends_at' => $this->timestampToDateTime(data_get($data, 'trial_ends_at')),
                'renews_at' => $this->timestampToDateTime(data_get($data, 'next_billed_at')),
                'ends_at' => $endsAt,
                'canceled_at' => $canceledAt,
            ],
            metadata: Arr::only($data, ['id', 'status', 'items', 'custom_data', 'management_urls', 'customer_id', 'customer'])
        );

        $this->syncBillingCustomer(
            $userId,
            data_get($data, 'customer_id') ?? data_get($data, 'customer.id'),
            data_get($data, 'customer_email') ?? data_get($data, 'customer.email')
        );

        $this->recordDiscountRedemption(
            $data,
            $userId,
            $planKey,
            data_get($data, 'custom_data.price_key'),
            (string) $subscriptionId
        );

        if ($status === 'active') {
            app(\App\Domain\Billing\Services\CheckoutService::class)
                ->verifyUserAfterPayment($userId);
        }

        return $subscription;
    }

    /**
     * Sync billing customer from webhook data.
     */
    private function syncBillingCustomer(int $userId, ?string $providerId, ?string $email): void
    {
        if ($providerId) {
            $existing = BillingCustomer::query()
                ->where('provider', 'paddle')
                ->where('provider_id', $providerId)
                ->first();

            if ($existing && $existing->user_id !== $userId) {
                \Illuminate\Support\Facades\Log::warning('Paddle webhook customer id already mapped', [
                    'provider_id' => $providerId,
                    'existing_user_id' => $existing->user_id,
                    'incoming_user_id' => $userId,
                ]);

                return;
            }

            BillingCustomer::query()->updateOrCreate(
                [
                    'provider' => 'paddle',
                    'provider_id' => $providerId,
                ],
                [
                    'user_id' => $userId,
                    'email' => $email,
                ]
            );

            return;
        }

        BillingCustomer::query()->updateOrCreate(
            [
                'user_id' => $userId,
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
        int $userId,
        ?string $planKey,
        ?string $priceKey,
        string $providerId
    ): void {
        $customData = data_get($data, 'custom_data', []);
        $discountId = $customData['discount_id'] ?? data_get($data, 'metadata.discount_id');
        $discountCode = $customData['discount_code'] ?? data_get($data, 'metadata.discount_code');

        if (! $discountId && ! $discountCode) {
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

        if (! $discount) {
            return;
        }

        $user = User::find($userId);

        app(DiscountService::class)->recordRedemption(
            $discount,
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
        if (! $planKey) {
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
