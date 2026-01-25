<?php

namespace App\Domain\Billing\Adapters\Stripe\Handlers;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Support\Arr;

/**
 * Handles Stripe invoice-related webhook events.
 *
 * Processes: invoice.paid, invoice.payment_succeeded, invoice.payment_failed,
 * invoice.finalized, invoice.voided, invoice.marked_uncollectible
 */
class StripeInvoiceHandler implements StripeWebhookHandler
{
    use ResolvesStripeData;

    /**
     * {@inheritDoc}
     */
    public function eventTypes(): array
    {
        return [
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

        $this->syncInvoice($object);
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
                'status' => 'active',
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
