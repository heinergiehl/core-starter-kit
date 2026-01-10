<?php

namespace App\Domain\Billing\Events;

use App\Domain\Billing\Models\Order;
use App\Domain\Organization\Models\Team;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a payment is received (for one-time purchases).
 */
class PaymentReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly Team $team,
        public readonly int $amount,
        public readonly string $currency,
    ) {}
}
