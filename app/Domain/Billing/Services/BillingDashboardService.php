<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Order;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Support\Collection;

class BillingDashboardService
{
    public function __construct(
        private readonly BillingPlanService $planService
    ) {}

    /**
     * Get all necessary data for the billing dashboard.
     *
     * @return array{
     *    subscription: ?\App\Domain\Billing\Models\Subscription,
     *    plan: ?\App\Domain\Billing\Data\Plan,
     *    invoices: Collection,
     *    pendingOrder: ?Order,
     *    recentOneTimeOrder: ?Order,
     *    oneTimeOrders: Collection,
     *    canCancel: bool
     * }
     */
    public function getData(User $user): array
    {
        $subscription = $user->activeSubscription();
        $plan = null;
        $invoices = collect();
        $pendingOrder = null;

        if ($subscription) {
            try {
                $plan = $this->planService->plan($subscription->plan_key);
            } catch (\RuntimeException) {
                // Fallback for deprecated or missing plans
                $plan = null;
            }

            $invoices = Invoice::query()
                ->where('user_id', $user->id)
                ->latest('issued_at')
                ->take(5)
                ->get();
        } else {
            // Check for recent completed subscription order (provisioning race condition)
            // We determine one-time vs subscription by checking if the associated product has type='one_time'
            $pendingOrder = Order::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Completed->value])
                ->where('created_at', '>=', now()->subMinutes(10))
                // Exclude one-time product orders or orders without subscription_id in metadata
                ->whereDoesntHave('product', fn ($q) => $q->where('type', 'one_time'))
                ->latest('id')
                ->get()
                // Ensure it's actually a subscription order by checking metadata or product type relationship
                ->filter(function ($order) {
                    $meta = $order->metadata ?? [];
                    // If we have explicit subscription_id, it is a sub.
                    if (! empty($meta['subscription_id'])) {
                        return true;
                    }

                    // If the order is paid/completed but has no subscription_id,
                    // it's a one-time purchase (or a one-time price on a sub product).
                    // We should NOT treat it as a pending subscription.
                    return false;
                })
                ->first();
        }

        // Check for recent one-time purchases to show success banner (within 10 minutes)
        $recentOneTimeOrder = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Completed->value])
            ->where('created_at', '>=', now()->subMinutes(10))
            ->latest('id')
            ->get()
            ->filter(function ($order) {
                // explicit one-time product
                if ($order->product && $order->product->type === 'one_time') {
                    return true;
                }
                // OR no subscription_id in metadata
                $meta = $order->metadata ?? [];

                return empty($meta['subscription_id']);
            })
            ->first();

        // Get all one-time orders for purchase history
        $oneTimeOrders = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Completed->value])
            ->with('product')
            ->latest('id')
            ->get()
            ->filter(function ($order) {
                // explicit one-time product
                if ($order->product && $order->product->type === 'one_time') {
                    return true;
                }
                // OR no subscription_id in metadata
                $meta = $order->metadata ?? [];

                return empty($meta['subscription_id']);
            });

        $canCancel = $subscription
            && in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true);

        return compact(
            'subscription',
            'plan',
            'invoices',
            'pendingOrder',
            'recentOneTimeOrder',
            'oneTimeOrders',
            'canCancel'
        );
    }
}
