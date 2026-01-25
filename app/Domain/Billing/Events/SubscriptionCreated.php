<?php

namespace App\Domain\Billing\Events;

use App\Domain\Billing\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a new subscription is created.
 */
class SubscriptionCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly User $user,
        public readonly string $planKey,
        public readonly string $provider,
    ) {}
}
