<?php

namespace App\Domain\Billing\Adapters\Stripe\Handlers;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
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
        } else {
            $this->syncInvoice($object);
        }
    }

    /**
     * Handle paid invoice - syncs invoice and updates subscription.
     */
    private function handleInvoicePaid(array $object): void
    {
        $subscriptionId = data_get($object, 'subscription');

        if (!$subscriptionId) {
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
     * Sync invoice data from Stripe webhook.
     */
    public function syncInvoice(array $object, ?string $overrideStatus = null): void
    {
        $providerId = data_get($object, 'id');

        if (!$providerId) {
            return;
        }

        $subscriptionId = data_get($object, 'subscription');
        $customerId = data_get($object, 'customer');
        $teamId = $this->resolveTeamIdFromMetadata($object)
            ?? $this->resolveTeamIdFromCustomerId($customerId)
            ?? $this->resolveTeamIdFromSubscriptionId($subscriptionId);

        if (!$teamId) {
            return;
        }

        $subscription = $subscriptionId
            ? Subscription::query()
                ->where('provider', $this->provider())
                ->where('provider_id', $subscriptionId)
                ->first()
            : null;

        $status = $overrideStatus ?: (string) data_get($object, 'status', 'open');

        Invoice::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => (string) $providerId,
            ],
            [
                'team_id' => $teamId,
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
    }
}
