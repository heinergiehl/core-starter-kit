<?php

namespace App\Domain\Audit\Observers;

use App\Domain\Audit\Services\ActivityLogService;
use App\Domain\Billing\Models\Order;

class OrderActivityObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function created(Order $order): void
    {
        $this->activityLogService->log(
            category: 'billing',
            event: 'billing.order_created',
            subject: $order,
            description: "Order created with status {$order->status->value}.",
            metadata: [
                'customer_id' => $order->user_id,
                'provider' => $order->provider?->value,
                'provider_id' => $order->provider_id,
                'plan_key' => $order->plan_key,
                'status' => $order->status->value,
                'amount' => $order->amount,
                'currency' => $order->currency,
            ],
        );
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $fromStatus = $this->normalizeEnumValue($order->getOriginal('status'));

        $this->activityLogService->log(
            category: 'billing',
            event: 'billing.order_status_changed',
            subject: $order,
            description: sprintf(
                'Order status changed from %s to %s.',
                $fromStatus ?? 'unknown',
                $order->status->value,
            ),
            metadata: [
                'customer_id' => $order->user_id,
                'provider' => $order->provider?->value,
                'provider_id' => $order->provider_id,
                'plan_key' => $order->plan_key,
                'from_status' => $fromStatus,
                'to_status' => $order->status->value,
            ],
        );
    }

    private function normalizeEnumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
