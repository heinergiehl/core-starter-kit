<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Order;
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
     *    plan: ?array,
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
                $plan = ['name' => ucfirst($subscription->plan_key), 'key' => $subscription->plan_key];
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
                ->whereIn('status', ['paid', 'completed'])
                ->where('created_at', '>=', now()->subMinutes(10))
                // Exclude one-time product orders
                ->whereDoesntHave('product', fn ($q) => $q->where('type', 'one_time'))
                ->latest('id')
                ->first();
        }

        // Check for recent one-time purchases to show success banner (within 10 minutes)
        $recentOneTimeOrder = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['paid', 'completed'])
            ->where('created_at', '>=', now()->subMinutes(10))
            ->whereHas('product', fn ($q) => $q->where('type', 'one_time'))
            ->latest('id')
            ->first();

        // Get all one-time orders for purchase history
        $oneTimeOrders = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['paid', 'completed'])
            ->whereHas('product', fn ($q) => $q->where('type', 'one_time'))
            ->with('product')
            ->latest('id')
            ->get();

        $canCancel = $subscription && in_array($subscription->status, ['active', 'trialing']);

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
