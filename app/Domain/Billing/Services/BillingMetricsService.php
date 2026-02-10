<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Data\Plan;
use App\Domain\Billing\Data\Price;
use App\Domain\Billing\Models\Subscription;
use App\Enums\SubscriptionStatus;
use Illuminate\Support\Carbon;

class BillingMetricsService
{
    /**
     * @var array<string, Plan|null>
     */
    private array $planCache = [];

    /**
     * @var array<string, array<string, Plan>>
     */
    private array $providerPlanCache = [];

    public function __construct(
        private readonly BillingPlanService $planService
    ) {}

    public function snapshot(): array
    {
        $activeStatuses = [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value];
        $subscriptions = Subscription::query()
            ->whereIn('status', $activeStatuses)
            ->get();

        $windowStart = now()->subDays(30);
        $mrr = $subscriptions->sum(fn (Subscription $subscription): float => $this->monthlyAmount($subscription));
        $activeCount = $subscriptions->count();
        $trialingCount = $subscriptions->filter(
            fn (Subscription $subscription): bool => $subscription->status === SubscriptionStatus::Trialing
        )->count();
        $userCount = $subscriptions->pluck('user_id')->unique()->count();

        $canceledLast30 = $this->countRecentCancellations($windowStart);
        $startingSubscriptions = $this->countStartingSubscriptions($windowStart);

        $churnRate = $startingSubscriptions > 0 ? ($canceledLast30 / $startingSubscriptions) * 100 : 0.0;
        $arpu = $userCount > 0 ? $mrr / $userCount : 0.0;

        return [
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'active_subscriptions' => $activeCount,
            'trialing_subscriptions' => $trialingCount,
            'active_customers' => $userCount,
            'cancellations_last_30_days' => $canceledLast30,
            'churn_rate' => $churnRate,
            'arpu' => $arpu,
        ];
    }

    private function countStartingSubscriptions(Carbon $since): int
    {
        return Subscription::query()
            ->where('created_at', '<', $since)
            ->where(function ($query) use ($since) {
                $query
                    ->whereIn('status', [
                        SubscriptionStatus::Active->value,
                        SubscriptionStatus::Trialing->value,
                    ])
                    ->orWhere(function ($q) use ($since) {
                        $q->whereNotNull('canceled_at')
                            ->where('canceled_at', '>=', $since);
                    })
                    ->orWhere(function ($q) use ($since) {
                        $q->where('status', SubscriptionStatus::Canceled->value)
                            ->whereNull('canceled_at')
                            ->where(function ($q2) use ($since) {
                                $q2->where('ends_at', '>=', $since)
                                    ->orWhereNull('ends_at');
                            });
                    });
            })
            ->count();
    }

    private function countRecentCancellations(Carbon $since): int
    {
        return Subscription::query()
            ->where(function ($query) use ($since) {
                $query
                    ->where(function ($q) use ($since) {
                        $q->whereNotNull('canceled_at')
                            ->where('canceled_at', '>=', $since);
                    })
                    ->orWhere(function ($q) use ($since) {
                        $q->where('status', SubscriptionStatus::Canceled->value)
                            ->whereNull('canceled_at')
                            ->where(function ($q2) use ($since) {
                                $q2->where('ends_at', '>=', $since)
                                    ->orWhere(function ($q3) use ($since) {
                                        $q3->whereNull('ends_at')
                                            ->where('updated_at', '>=', $since);
                                    });
                            });
                    });
            })
            ->count();
    }

    private function monthlyAmount(Subscription $subscription): float
    {
        $planKey = $subscription->plan_key;

        if (! $planKey) {
            return 0.0;
        }

        $plan = $this->resolvePlan($planKey);

        if (! $plan) {
            return 0.0;
        }

        if ($plan->isOneTime()) {
            return 0.0;
        }

        $price = $this->priceForSubscription($subscription, $plan);

        if (! $price) {
            return 0.0;
        }

        $amount = (float) $price->amount;

        if ($price->amountIsMinor) {
            $amount /= 100;
        }

        $interval = $price->interval ?: 'month';
        $intervalCount = max($price->intervalCount, 1);

        $monthly = match ($interval) {
            'year' => $amount / (12 * $intervalCount),
            'week' => $amount * (52 / 12) / $intervalCount,
            'day' => $amount * (365 / 12) / $intervalCount,
            default => $amount / $intervalCount,
        };

        return $monthly;
    }

    private function priceForSubscription(Subscription $subscription, Plan $plan): ?Price
    {
        $provider = strtolower((string) $subscription->provider->value);
        $prices = $this->pricesForProvider($provider, $plan->key);

        if (empty($prices)) {
            $prices = $plan->prices;
        }

        if (empty($prices)) {
            return null;
        }

        $priceKey = data_get($subscription->metadata, 'price_key')
            ?? data_get($subscription->metadata, 'metadata.price_key');

        if ($priceKey && isset($prices[$priceKey])) {
            return $prices[$priceKey];
        }

        $providerPriceId = $this->resolveProviderPriceId($subscription);

        if ($providerPriceId) {
            foreach ($prices as $price) {
                if ($price->idFor($provider) === $providerPriceId) {
                    return $price;
                }
            }
        }

        return collect($prices)->first();
    }

    private function pricesForProvider(string $provider, string $planKey): array
    {
        if (! isset($this->providerPlanCache[$provider])) {
            $this->providerPlanCache[$provider] = $this->planService
                ->plansForProvider($provider)
                ->keyBy('key')
                ->all();
        }

        $plan = $this->providerPlanCache[$provider][$planKey] ?? null;

        return $plan?->prices ?? [];
    }

    private function resolveProviderPriceId(Subscription $subscription): ?string
    {
        $metadata = $subscription->metadata ?? [];

        return data_get($metadata, 'stripe_price_id')
            ?? data_get($metadata, 'items.0.price_id')
            ?? data_get($metadata, 'items.0.price.id')
            ?? data_get($metadata, 'price_id')
            ?? data_get($metadata, 'variant_id');
    }

    private function resolvePlan(string $planKey): ?Plan
    {
        if (array_key_exists($planKey, $this->planCache)) {
            return $this->planCache[$planKey];
        }

        try {
            $this->planCache[$planKey] = $this->planService->plan($planKey);
        } catch (\RuntimeException) {
            $this->planCache[$planKey] = null;
        }

        return $this->planCache[$planKey];
    }
}
