<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Subscription;
use Illuminate\Support\Collection;

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
     * Calculate Monthly Recurring Revenue.
     *
     * Uses plan_key from subscriptions to look up prices.
     */
    public function calculateMRR(): float
    {
        // Get all active subscriptions
        $activeSubscriptions = Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->whereNull('canceled_at')
            ->get();

        if ($activeSubscriptions->isEmpty()) {
            return 0;
        }

        // Get unique plan keys
        $planKeys = $activeSubscriptions->pluck('plan_key')->unique()->filter();

        if ($planKeys->isEmpty()) {
            return 0;
        }

        // Get plans with their prices
        $plans = Plan::query()
            ->whereIn('key', $planKeys)
            ->with('prices')
            ->get()
            ->keyBy('key');

        $mrr = 0;

        foreach ($activeSubscriptions as $subscription) {
            $plan = $plans->get($subscription->plan_key);
            
            if (!$plan) {
                continue;
            }

            // Get the first active price for this plan
            $price = $plan->prices->where('is_active', true)->first();
            
            if (!$price) {
                continue;
            }

            $amount = $price->amount / 100; // Convert from cents
            $interval = $price->interval ?? 'month';
            $intervalCount = $price->interval_count ?? 1;

            // Normalize to monthly
            $mrr += match ($interval) {
                'year' => ($amount / 12) / $intervalCount,
                'week' => ($amount * 4.33) / $intervalCount,
                'day' => ($amount * 30) / $intervalCount,
                default => $amount / $intervalCount, // month
            };
        }

        return round($mrr, 2);
    }

    /**
     * Get count of active subscriptions.
     */
    public function getActiveSubscriptionCount(): int
    {
        return Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->whereNull('canceled_at')
            ->count();
    }

    /**
     * Get new subscriptions this month.
     */
    public function getNewSubscriptionsThisMonth(): int
    {
        return Subscription::query()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    /**
     * Get cancellations this month.
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
     * Calculate churn rate (30-day).
     */
    public function calculateChurnRate(): float
    {
        $startDate = now()->subDays(30);
        
        $startingCount = Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->where('created_at', '<', $startDate)
            ->count();

        if ($startingCount === 0) {
            return 0;
        }

        $churned = Subscription::query()
            ->whereNotNull('canceled_at')
            ->where('canceled_at', '>=', $startDate)
            ->count();

        return round(($churned / $startingCount) * 100, 2);
    }

    /**
     * Calculate Average Revenue Per User.
     */
    public function calculateARPU(): float
    {
        $activeCount = $this->getActiveSubscriptionCount();

        if ($activeCount === 0) {
            return 0;
        }

        return round($this->calculateMRR() / $activeCount, 2);
    }

    /**
     * Get subscription growth data for charts.
     */
    public function getGrowthData(int $months = 6): Collection
    {
        $data = collect();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            
            $count = Subscription::query()
                ->whereIn('status', ['active', 'trialing'])
                ->whereNull('canceled_at')
                ->where('created_at', '<=', $date->endOfMonth())
                ->count();

            $data->push([
                'month' => $date->format('M Y'),
                'subscriptions' => $count,
            ]);
        }

        return $data;
    }
}
