<?php

namespace App\Domain\Billing\Adapters\Stripe\Handlers;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use Stripe\StripeClient;

/**
 * Handles Stripe subscription lifecycle webhook events.
 *
 * Processes: customer.subscription.created, customer.subscription.updated,
 * customer.subscription.deleted
 */
class StripeSubscriptionHandler implements StripeWebhookHandler
{
    use ResolvesStripeData;

    public function __construct(
        protected \App\Domain\Billing\Services\SubscriptionService $subscriptionService
    ) {}

    /**
     * {@inheritDoc}
     */
    public function eventTypes(): array
    {
        return [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function handle(WebhookEvent $event, array $object): void
    {
        $payload = $event->payload ?? [];
        $eventType = $payload['type'] ?? $event->type;

        $this->syncSubscription($object, $eventType);
    }

    /**
     * Sync subscription data from Stripe webhook.
     */
    public function syncSubscription(array $object, string $eventType): void
    {
        $providerId = data_get($object, 'id');
        $customerId = data_get($object, 'customer');

        if (! $providerId) {
            return;
        }

        $existingSubscription = Subscription::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $providerId)
            ->first();

        $previousPlanKey = $existingSubscription?->plan_key;

        $userId = $this->resolveUserIdFromMetadata($object)
            ?? $this->resolveUserIdFromCustomerId($customerId);

        if (! $userId) {
            return;
        }

        $planKey = $this->resolvePlanKey($object) ?? 'unknown';
        $status = (string) data_get($object, 'status', 'active');
        $quantity = (int) (data_get($object, 'items.data.0.quantity') ?? data_get($object, 'quantity') ?? 1);
        $trialEnd = $this->timestampToDateTime(data_get($object, 'trial_end'));
        $renewsAt = $this->timestampToDateTime(data_get($object, 'current_period_end'));
        $canceledAt = $this->timestampToDateTime(data_get($object, 'canceled_at'));

        $endsAt = $this->timestampToDateTime(data_get($object, 'ended_at'));
        if (! $endsAt && data_get($object, 'cancel_at_period_end') && $renewsAt) {
            $endsAt = $renewsAt;
        }

        $metadata = data_get($object, 'metadata', []);
        $metadata['stripe_item_id'] = data_get($object, 'items.data.0.id');
        $metadata['stripe_price_id'] = data_get($object, 'items.data.0.price.id');
        $metadata['stripe_status'] = $status;
        $metadata['event_type'] = $eventType;
        $metadata['items'] = data_get($object, 'items');
        $metadata['currency'] = data_get($object, 'currency');

        $this->subscriptionService->syncFromProvider(
            provider: $this->provider(),
            providerId: $providerId,
            userId: $userId,
            planKey: $planKey,
            status: $status,
            quantity: max($quantity, 1),
            dates: [
                'trial_ends_at' => $trialEnd,
                'renews_at' => $renewsAt,
                'ends_at' => $endsAt,
                'canceled_at' => $canceledAt,
            ],
            metadata: $metadata
        );

        $this->syncBillingCustomer($userId, $customerId, data_get($object, 'customer_email'));

        if ($status === 'active') {
            app(\App\Domain\Billing\Services\CheckoutService::class)
                ->verifyUserAfterPayment($userId);
        }
    }

    /**
     * Sync subscription from Stripe API (used after checkout).
     */
    public function syncSubscriptionFromStripe(string $subscriptionId): void
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            return;
        }

        try {
            $client = new StripeClient($secret);
            $subscription = $client->subscriptions->retrieve($subscriptionId, []);

            if ($subscription) {
                $this->syncSubscription($subscription->toArray(), 'checkout.session.completed');
            }
        } catch (\Throwable $exception) {
            // Keep checkout flow resilient; webhook processing will update later.
        }
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
