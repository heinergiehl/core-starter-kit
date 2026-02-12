<?php

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\WebhookEvent;
use App\Models\User;
use Illuminate\Http\Request;

interface BillingRuntimeProvider
{
    public function provider(): string;

    /**
     * Update an existing subscription.
     */
    public function updateSubscription(\App\Domain\Billing\Models\Subscription $subscription, string $newPriceId): void;

    /**
     * Cancel a subscription.
     * Returns the date when the subscription will end (effective cancellation date).
     */
    public function cancelSubscription(\App\Domain\Billing\Models\Subscription $subscription): \Carbon\Carbon;

    /**
     * Resume a canceled (but still active) subscription.
     */
    public function resumeSubscription(\App\Domain\Billing\Models\Subscription $subscription): void;

    /**
     * Pull and normalize the latest subscription state from the provider API.
     */
    public function syncSubscriptionState(\App\Domain\Billing\Models\Subscription $subscription): void;

    /**
     * Validate the webhook request and return normalized event data.
     *
     * @return array{id: string, type: string|null, payload: array}
     */
    public function parseWebhook(Request $request): \App\Domain\Billing\Data\WebhookPayload;

    public function processEvent(WebhookEvent $event): void;

    public function createCheckout(
        User $user,
        string $planKey,
        string $priceKey,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        ?Discount $discount = null
    ): \App\Domain\Billing\Data\TransactionDTO;

    /**
     * Create a discount on the provider and return the provider's ID.
     */
    public function createDiscount(Discount $discount): string;
}
