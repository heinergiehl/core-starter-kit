<?php

namespace App\Domain\RepoAccess\Observers;

use App\Domain\Billing\Models\Order;
use App\Domain\RepoAccess\Services\RepoAccessService;
use App\Enums\OrderStatus;
use App\Models\User;

class OrderRepoAccessObserver
{
    public function created(Order $order): void
    {
        if (! $this->canGrant($order)) {
            return;
        }

        if (! $this->isPaidStatus($order->status)) {
            return;
        }

        $this->queueGrant($order, 'one_time_order_paid');
    }

    public function updated(Order $order): void
    {
        if (! $this->canGrant($order)) {
            return;
        }

        if (! $this->isPaidStatus($order->status)) {
            return;
        }

        if ($this->isPaidStatus($order->getOriginal('status'))) {
            return;
        }

        $this->queueGrant($order, 'one_time_order_paid');
    }

    private function canGrant(Order $order): bool
    {
        if (! app(RepoAccessService::class)->isEnabled()) {
            return false;
        }

        if (! $order->user_id) {
            return false;
        }

        if ($this->isSubscriptionOrder($order)) {
            return false;
        }

        return true;
    }

    private function isPaidStatus(mixed $status): bool
    {
        $normalized = $status instanceof OrderStatus
            ? $status->value
            : strtolower((string) $status);

        return in_array($normalized, [OrderStatus::Paid->value, OrderStatus::Completed->value], true);
    }

    private function isSubscriptionOrder(Order $order): bool
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $subscriptionId = data_get($metadata, 'subscription_id');

        return ! empty($subscriptionId);
    }

    private function queueGrant(Order $order, string $source): void
    {
        $user = User::query()->find($order->user_id);

        if (! $user) {
            return;
        }

        app(RepoAccessService::class)->queueGrant($user, $source);
    }
}
