<?php

namespace App\Domain\Billing\Jobs;

use App\Domain\Billing\Adapters\Stripe\Handlers\StripeSubscriptionHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSubscriptionFromStripeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $subscriptionId
    ) {}

    public function handle(StripeSubscriptionHandler $handler): void
    {
        $handler->syncSubscriptionFromStripe($this->subscriptionId);
    }
}
