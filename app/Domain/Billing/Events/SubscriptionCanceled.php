<?php

namespace App\Domain\Billing\Events;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Organization\Models\Team;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a subscription is canceled.
 */
class SubscriptionCanceled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Team $team,
        public readonly ?\DateTimeInterface $endsAt,
    ) {}
}
