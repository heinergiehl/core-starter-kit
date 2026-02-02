<?php

namespace App\Domain\Billing\Adapters\Stripe\Handlers;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Support\Arr;
use Stripe\StripeClient;

/**
 * Handles Stripe subscription lifecycle webhook events.
 *
 * Handles Stripe subscription and invoice lifecycle webhook events.
 *
 * Processes: customer.subscription.*, invoice.*
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
            'invoice.paid',
            'invoice.payment_succeeded',
            'invoice.payment_failed',
            'invoice.finalized',
            'invoice.voided',
            'invoice.marked_uncollectible',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function handle(WebhookEvent $event, array $object): void
    {
        $payload = $event->payload ?? [];
        $eventType = $payload['type'] ?? $event->type;

        if (in_array($eventType, ['invoice.paid', 'invoice.payment_succeeded'])) {
            $this->handleInvoicePaid($object);
            return;
        }

        if ($eventType === 'invoice.payment_failed') {
            $this->handleInvoicePaymentFailed($object);
            return;
        }

        if (str_starts_with($eventType, 'invoice.')) {
            $this->syncInvoice($object);
            return;
        }

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
            \App\Domain\Billing\Data\SubscriptionData::fromProvider(
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
            )
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
    /**
     * Handle paid invoice - syncs invoice and updates subscription.
     */
    private function handleInvoicePaid(array $object): void
    {
        $subscriptionId = data_get($object, 'subscription');

        if (! $subscriptionId) {
            return;
        }

        $this->syncInvoice($object, 'paid');

        Subscription::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $subscriptionId)
            ->update([
                'status' => \App\Enums\SubscriptionStatus::Active,
            ]);
    }

    /**
     * Handle failed invoice payment - syncs invoice and notifies the owner.
     */
    private function handleInvoicePaymentFailed(array $object): void
    {
        $invoice = $this->syncInvoice($object);

        if (! $invoice || $invoice->payment_failed_email_sent_at) {
            return;
        }

        $owner = $invoice->user;

        if (! $owner) {
            return;
        }

        $subscription = $invoice->subscription;
        $planKey = $subscription?->plan_key;

        $failureReason = data_get($object, 'last_finalization_error.message')
            ?? data_get($object, 'payment_intent.last_payment_error.message')
            ?? data_get($object, 'status_transitions')
            ?? null;

        $owner->notify(new PaymentFailedNotification(
            planName: $this->resolvePlanName($planKey),
            amount: $invoice->amount_due,
            currency: $invoice->currency,
            failureReason: is_string($failureReason) ? $failureReason : null,
        ));

        $invoice->forceFill(['payment_failed_email_sent_at' => now()])->save();
    }

    /**
     * Sync invoice data from Stripe webhook.
     */
    public function syncInvoice(array $object, ?string $overrideStatus = null): ?Invoice
    {
        $providerId = data_get($object, 'id');

        if (! $providerId) {
            return null;
        }

        $subscriptionId = data_get($object, 'subscription');
        $customerId = data_get($object, 'customer');
        $userId = $this->resolveUserIdFromMetadata($object)
            ?? $this->resolveUserIdFromCustomerId($customerId)
            ?? $this->resolveUserIdFromSubscriptionId($subscriptionId);

        if (! $userId) {
            return null;
        }

        $subscription = $subscriptionId
            ? Subscription::query()
                ->where('provider', $this->provider())
                ->where('provider_id', $subscriptionId)
                ->first()
            : null;

        $status = $overrideStatus ?: (string) data_get($object, 'status', 'open');

        $invoice = Invoice::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => (string) $providerId,
            ],
            [
                'user_id' => $userId,
                'subscription_id' => $subscription?->id,
                'status' => $status,
                'number' => data_get($object, 'number'),
                'amount_due' => (int) (data_get($object, 'amount_due') ?? 0),
                'amount_paid' => (int) (data_get($object, 'amount_paid') ?? 0),
                'currency' => strtoupper((string) data_get($object, 'currency', 'USD')),
                'issued_at' => $this->timestampToDateTime(data_get($object, 'created')),
                'due_at' => $this->timestampToDateTime(data_get($object, 'due_date')),
                'paid_at' => $this->timestampToDateTime(data_get($object, 'status_transitions.paid_at')),
                'hosted_invoice_url' => data_get($object, 'hosted_invoice_url'),
                'invoice_pdf' => data_get($object, 'invoice_pdf'),
                'metadata' => Arr::only($object, [
                    'id',
                    'status',
                    'subscription',
                    'customer',
                    'total',
                    'lines',
                    'metadata',
                    'status_transitions',
                ]),
            ]
        );

        return $invoice;
    }
}
