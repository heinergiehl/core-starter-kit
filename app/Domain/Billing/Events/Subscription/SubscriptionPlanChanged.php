<?php

namespace App\Domain\Billing\Events\Subscription;

use App\Domain\Billing\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionPlanChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public string $previousPlanKey,
        public string $newPlanKey
    ) {}
}
