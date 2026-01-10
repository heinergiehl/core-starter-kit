<?php

namespace App\Domain\Billing\Adapters\Stripe\Handlers;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\WebhookEvent;

/**
 * Handles Stripe payment-related webhook events.
 *
 * Processes: payment_intent.succeeded, charge.refunded
 */
class StripePaymentHandler implements StripeWebhookHandler
{
    use ResolvesStripeData;

    /**
     * {@inheritDoc}
     */
    public function eventTypes(): array
    {
        return [
            'payment_intent.succeeded',
            'charge.refunded',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function handle(WebhookEvent $event, array $object): void
    {
        $payload = $event->payload ?? [];
        $eventType = $payload['type'] ?? $event->type;

        match ($eventType) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($object),
            'charge.refunded' => $this->handleChargeRefunded($object),
            default => null,
        };
    }

    /**
     * Handle successful payment intent.
     */
    private function handlePaymentIntentSucceeded(array $object): void
    {
        $providerId = data_get($object, 'id');

        if (!$providerId) {
            return;
        }

        Order::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $providerId)
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
    }

    /**
     * Handle refunded charge.
     */
    private function handleChargeRefunded(array $object): void
    {
        $paymentIntent = data_get($object, 'payment_intent');
        $providerId = $paymentIntent ?: data_get($object, 'id');

        if (!$providerId) {
            return;
        }

        Order::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $providerId)
            ->update([
                'status' => 'refunded',
                'refunded_at' => now(),
            ]);
    }
}
