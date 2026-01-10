<?php

namespace App\Domain\Billing\Adapters;

use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Models\Discount;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

interface BillingProviderAdapter
{
    public function provider(): string;

    /**
     * Validate the webhook request and return normalized event data.
     *
     * @return array{id: string, type: string|null, payload: array}
     */
    public function parseWebhook(Request $request): array;

    public function processEvent(WebhookEvent $event): void;

    public function syncSeatQuantity(Team $team, int $quantity): void;

    public function createCheckout(
        Team $team,
        User $user,
        string $planKey,
        string $priceKey,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        ?Discount $discount = null
    ): string;
}
