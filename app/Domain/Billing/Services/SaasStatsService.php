<?php

namespace App\Domain\Billing\Services;

use App\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Subscription;

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
}
