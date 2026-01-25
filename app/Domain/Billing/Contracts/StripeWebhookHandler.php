<?php

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\Models\WebhookEvent;

/**
 * Contract for Stripe webhook event handlers.
 *
 * Each handler is responsible for processing a specific category
 * of webhook events (checkout, subscription, invoice, etc.).
 */
interface StripeWebhookHandler
{
    /**
     * Get the event types this handler can process.
     *
     * @return array<int, string>
     */
    public function eventTypes(): array;

    /**
     * Process the webhook event.
     *
     * @param  WebhookEvent  $event  The persisted webhook event
     * @param  array<string, mixed>  $object  The event data object
     */
    public function handle(WebhookEvent $event, array $object): void;
}
