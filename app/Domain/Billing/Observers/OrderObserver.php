<?php

namespace App\Domain\Billing\Observers;

use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Services\BillingPlanService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class OrderObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(Order $order): void
    {
        // Future implementation for order updates
        // For example, if we wanted to move payment failed notifications here, we could check for status changes.
        // Currently, PaddleOrderHandler handles failure explicitly with more context (failure reason) which might not be stored on the order model directly yet.
    }

    private function resolvePlanName(?string $planKey): string
    {
        if (! $planKey) {
            return 'product';
        }

        try {
            $plan = app(BillingPlanService::class)->plan($planKey);

            return $plan['name'] ?? ucfirst($planKey);
        } catch (\Throwable) {
            return ucfirst($planKey);
        }
    }
}
