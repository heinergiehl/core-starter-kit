<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\Subscription;

/**
 * SaaS Stats Service.
 *
 * Calculates key SaaS metrics: MRR, churn, ARPU, etc.
 */
class SaasStatsService
{
    /**
     * Get all key SaaS metrics.
     */
    public function getMetrics(): array
    {
        return [
            'mrr' => $this->calculateMRR(),
            'arr' => $this->calculateMRR() * 12,
            'active_subscriptions' => $this->getActiveSubscriptionCount(),
            'new_subscriptions_this_month' => $this->getNewSubscriptionsThisMonth(),
            'cancellations_this_month' => $this->getCancellationsThisMonth(),
            'churn_rate' => $this->calculateChurnRate(),
            'arpu' => $this->calculateARPU(),
        ];
    }

    /**
     * Calculate Monthly Recurring Revenue using DB aggregation.
     */
    public function calculateMRR(): float
    {
        // 1. Get all active subscriptions with their plan keys
        // We do this lightweight query first to get the breakdown
        $subscriptions = Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->whereNull('canceled_at')
            ->selectRaw('plan_key, count(*) as count')
            ->groupBy('plan_key')
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0.0;
        }

        // 2. Get prices for these plans efficiently
        $planKeys = $subscriptions->pluck('plan_key')->unique();

        $products = Product::query()
            ->whereIn('key', $planKeys)
            ->with(['prices' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get()
            ->keyBy('key');

        $mrr = 0.0;

        // 3. Calculate MRR based on aggregate counts
        foreach ($subscriptions as $group) {
            $product = $products->get($group->plan_key);

            if (! $product || $product->prices->isEmpty()) {
                continue;
            }

            // Heuristic: Use the first active price.
            // Ideally, subscription table should store the specific price_id used.
            $price = $product->prices->first();

            $amount = $price->amount / 100; // Convert cents to dollars
            $interval = $price->interval ?? 'month';
            $intervalCount = max($price->interval_count ?? 1, 1);

            // Normalize to monthly value
            $monthlyValue = match ($interval) {
                'year' => $amount / 12,
                'week' => $amount * 4.33,
                'day' => $amount * 30,
                default => $amount, // month
            };

            $monthlyValue = $monthlyValue / $intervalCount;

            $mrr += $monthlyValue * $group->count;
        }

        return round($mrr, 2);
    }

    /**
     * Get active subscription breakdown by plan.
     */
    public function getPlanDistribution(): array
    {
        $distribution = Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->whereNull('canceled_at')
            ->selectRaw('plan_key, count(*) as count')
            ->groupBy('plan_key')
            ->get()
            ->mapWithKeys(function ($item) {
                // Formatting plan key to Title Case for display
                $name = str($item->plan_key)->title()->replace('-', ' ')->toString();

                return [$name => $item->count];
            })
            ->toArray();

        // Ensure we handle empty state
        if (empty($distribution)) {
            return ['No Active Plans' => 0];
        }

        return $distribution;
    }

    /**
     * Get active subscriptions count.
     */
    public function getActiveSubscriptionCount(): int
    {
        return Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->whereNull('canceled_at')
            ->count();
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
        return Subscription::query()
            ->whereNotNull('canceled_at')
            ->whereMonth('canceled_at', now()->month)
            ->whereYear('canceled_at', now()->year)
            ->count();
    }

    /**
     * Calculate 30-day churn rate.
     */
    public function calculateChurnRate(): float
    {
        $startDate = now()->subDays(30);

        // Count subscriptions active at start of period (approximate)
        $startingCount = Subscription::query()
            ->where('created_at', '<', $startDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('canceled_at')
                    ->orWhere('canceled_at', '>=', $startDate);
            })
            ->count();

        if ($startingCount === 0) {
            return 0.0;
        }

        $churned = Subscription::query()
            ->whereNotNull('canceled_at')
            ->where('canceled_at', '>=', $startDate)
            ->count();

        return round(($churned / $startingCount) * 100, 2);
    }

    /**
     * Calculate ARPU.
     */
    public function calculateARPU(): float
    {
        $activeCount = $this->getActiveSubscriptionCount();

        if ($activeCount === 0) {
            return 0.0;
        }

        return round($this->calculateMRR() / $activeCount, 2);
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
}
