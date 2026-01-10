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

        if (!$providerId) {
            return;
        }

        $teamId = $this->resolveTeamIdFromMetadata($object)
            ?? $this->resolveTeamIdFromCustomerId($customerId);

        if (!$teamId) {
            return;
        }

        $planKey = $this->resolvePlanKey($object) ?? 'unknown';
        $status = (string) data_get($object, 'status', 'active');
        $quantity = (int) (data_get($object, 'items.data.0.quantity') ?? data_get($object, 'quantity') ?? 1);
        $trialEnd = $this->timestampToDateTime(data_get($object, 'trial_end'));
        $renewsAt = $this->timestampToDateTime(data_get($object, 'current_period_end'));
        $canceledAt = $this->timestampToDateTime(data_get($object, 'canceled_at'));

        $endsAt = $this->timestampToDateTime(data_get($object, 'ended_at'));
        if (!$endsAt && data_get($object, 'cancel_at_period_end') && $renewsAt) {
            $endsAt = $renewsAt;
        }

        $metadata = data_get($object, 'metadata', []);
        $metadata['stripe_item_id'] = data_get($object, 'items.data.0.id');
        $metadata['stripe_price_id'] = data_get($object, 'items.data.0.price.id');
        $metadata['stripe_status'] = $status;
        $metadata['event_type'] = $eventType;

        Subscription::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => $providerId,
            ],
            [
                'team_id' => $teamId,
                'plan_key' => $planKey,
                'status' => $status,
                'quantity' => max($quantity, 1),
                'trial_ends_at' => $trialEnd,
                'renews_at' => $renewsAt,
                'ends_at' => $endsAt,
                'canceled_at' => $canceledAt,
                'metadata' => $metadata,
            ]
        );

        $this->syncBillingCustomer($teamId, $customerId, data_get($object, 'customer_email'));
    }

    /**
     * Sync subscription from Stripe API (used after checkout).
     */
    public function syncSubscriptionFromStripe(string $subscriptionId): void
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
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
}
