<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use Illuminate\Support\Collection;

/**
 * SaaS Stats Service.
 *
 * Calculates key SaaS metrics: MRR, churn, ARPU, etc.
 */
class SaasStatsService
{
    /**
     * @var array<string, float|int>|null
     */
    private ?array $snapshotCache = null;

    /**
     * @var Collection<int, Order>|null
     */
    private ?Collection $oneTimeOrdersThisMonthCache = null;

    public function __construct(
        private readonly BillingMetricsService $billingMetricsService
    ) {}

    /**
     * Get all key SaaS metrics.
     */
    public function getMetrics(): array
    {
        $snapshot = $this->snapshot();

        return [
            'mrr' => $snapshot['mrr'],
            'arr' => $snapshot['arr'],
            'active_subscriptions' => $snapshot['active_subscriptions'],
            'new_subscriptions_this_month' => $this->getNewSubscriptionsThisMonth(),
            'cancellations_this_month' => $this->getCancellationsThisMonth(),
            'churn_rate' => $snapshot['churn_rate'],
            'arpu' => $snapshot['arpu'],
            'one_time_orders_this_month' => $this->getOneTimeOrdersThisMonth(),
            'one_time_revenue_this_month' => $this->getOneTimeRevenueThisMonth(),
        ];
    }

    /**
     * Calculate Monthly Recurring Revenue.
     */
    public function calculateMRR(): float
    {
        return (float) $this->snapshot()['mrr'];
    }

    /**
     * Get active subscription breakdown by plan.
     */
    public function getPlanDistribution(): array
    {
        $distribution = Subscription::query()
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trialing->value,
            ])
            ->selectRaw('plan_key, count(*) as count')
            ->groupBy('plan_key')
            ->get()
            ->mapWithKeys(function ($item) {
                // Formatting plan key to Title Case for display
                $name = str($item->plan_key)->title()->replace('-', ' ')->toString();

                return [$name => $item->count];
            })
            ->toArray();

        return $distribution;
    }

    /**
     * Get active subscriptions count.
     */
    public function getActiveSubscriptionCount(): int
    {
        return (int) $this->snapshot()['active_subscriptions'];
    }

    /**
     * Get new subscriptions count for current month.
     */
    public function getNewSubscriptionsThisMonth(): int
    {
        return Subscription::query()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    /**
     * Get cancellations count for current month.
     */
    public function getCancellationsThisMonth(): int
    {
        $month = now()->month;
        $year = now()->year;

        return Subscription::query()
            ->where(function ($query) use ($month, $year) {
                $query
                    ->where(function ($q) use ($month, $year) {
                        $q->whereNotNull('canceled_at')
                            ->whereMonth('canceled_at', $month)
                            ->whereYear('canceled_at', $year);
                    })
                    ->orWhere(function ($q) use ($month, $year) {
                        $q->where('status', SubscriptionStatus::Canceled->value)
                            ->whereNull('canceled_at')
                            ->whereNotNull('ends_at')
                            ->whereMonth('ends_at', $month)
                            ->whereYear('ends_at', $year);
                    });
            })
            ->count();
    }

    /**
     * Calculate 30-day churn rate.
     */
    public function calculateChurnRate(): float
    {
        return round((float) $this->snapshot()['churn_rate'], 2);
    }

    /**
     * Calculate ARPU.
     */
    public function calculateARPU(): float
    {
        return round((float) $this->snapshot()['arpu'], 2);
    }

    /**
     * Get completed one-time order count for current month.
     */
    public function getOneTimeOrdersThisMonth(): int
    {
        return $this->oneTimeOrdersThisMonth()->count();
    }

    /**
     * Get completed one-time revenue for current month.
     *
     * Returned in major currency units (e.g. USD dollars).
     */
    public function getOneTimeRevenueThisMonth(): float
    {
        $amountMinor = $this->oneTimeOrdersThisMonth()
            ->sum(fn (Order $order): int => (int) $order->amount);

        return $amountMinor / 100;
    }

    /**
     * Get monthly growth trend (active subscriptions).
     */
    public function getMonthlyGrowth(int $months = 12): array
    {
        $data = [];
        $now = now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $monthHost = $date->format('M Y');

            // This is a snapshot query - simplistic but fast
            // Ideally we'd have a daily_stats table
            $count = Subscription::query()
                ->where('created_at', '<=', $date->endOfMonth())
                ->where(function ($q) use ($date) {
                    $q->whereNull('canceled_at')
                        ->orWhere('canceled_at', '>', $date->endOfMonth());
                })
                ->count();

            $data[$monthHost] = $count;
        }

        return $data;
    }

    /**
     * @return array<string, float|int>
     */
    private function snapshot(): array
    {
        return $this->snapshotCache ??= $this->billingMetricsService->snapshot();
    }

    /**
     * @return Collection<int, Order>
     */
    private function oneTimeOrdersThisMonth(): Collection
    {
        if ($this->oneTimeOrdersThisMonthCache !== null) {
            return $this->oneTimeOrdersThisMonthCache;
        }

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $this->oneTimeOrdersThisMonthCache = Order::query()
            ->with('product:id,key,type')
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Completed->value])
            ->where(function ($query) use ($startOfMonth, $endOfMonth): void {
                $query->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($subQuery) use ($startOfMonth, $endOfMonth): void {
                        $subQuery
                            ->whereNull('paid_at')
                            ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
                    });
            })
            ->latest('id')
            ->get()
            ->filter(fn (Order $order): bool => $this->isOneTimeOrder($order))
            ->values();

        return $this->oneTimeOrdersThisMonthCache;
    }

    private function isOneTimeOrder(Order $order): bool
    {
        if ($order->product && $order->product->type === 'one_time') {
            return true;
        }

        $metadata = $order->metadata ?? [];
        $subscriptionId = data_get($metadata, 'subscription_id')
            ?? data_get($metadata, 'provider_subscription_id')
            ?? data_get($metadata, 'metadata.subscription_id')
            ?? data_get($metadata, 'custom_data.subscription_id');

        return empty($subscriptionId);
    }
}
