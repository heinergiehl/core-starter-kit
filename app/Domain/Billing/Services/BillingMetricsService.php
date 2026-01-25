<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Subscription;

class BillingMetricsService
{
    public function snapshot(): array
    {
        $activeStatuses = ['active', 'trialing'];
        $subscriptions = Subscription::query()
            ->whereIn('status', $activeStatuses)
            ->get();

        $mrr = $subscriptions->sum(fn (Subscription $subscription): float => $this->monthlyAmount($subscription));
        $activeCount = $subscriptions->count();
        $trialingCount = $subscriptions->where('status', 'trialing')->count();
        $userCount = $subscriptions->pluck('user_id')->unique()->count();

        $canceledLast30 = Subscription::query()
            ->whereNotNull('canceled_at')
            ->where('canceled_at', '>=', now()->subDays(30))
            ->count();

        $churnRate = $activeCount > 0 ? ($canceledLast30 / $activeCount) * 100 : 0.0;
        $arpu = $userCount > 0 ? $mrr / $userCount : 0.0;

        return [
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'active_subscriptions' => $activeCount,
            'trialing_subscriptions' => $trialingCount,
            'active_customers' => $userCount,
            'churn_rate' => $churnRate,
            'arpu' => $arpu,
        ];
    }

    private function monthlyAmount(Subscription $subscription): float
    {
        $planService = app(BillingPlanService::class);
        $planKey = $subscription->plan_key;

        if (! $planKey) {
            return 0.0;
        }

        try {
            $plan = $planService->plan($planKey);
        } catch (\RuntimeException $exception) {
            return 0.0;
        }

        if (($plan['type'] ?? 'subscription') !== 'subscription') {
            return 0.0;
        }

        $price = $this->priceForSubscription($subscription, $plan);

        if (! $price) {
            return 0.0;
        }

        $amount = (float) ($price['amount'] ?? 0);

        if (! empty($price['amount_is_minor'])) {
            $amount /= 100;
        }

        $interval = $price['interval'] ?? 'month';
        $intervalCount = (int) ($price['interval_count'] ?? 1);
        $intervalCount = max($intervalCount, 1);

        $monthly = match ($interval) {
            'year' => $amount / (12 * $intervalCount),
            'week' => $amount * (52 / 12) / $intervalCount,
            'day' => $amount * (365 / 12) / $intervalCount,
            default => $amount / $intervalCount,
        };

        return $monthly;
    }

    private function priceForSubscription(Subscription $subscription, array $plan): ?array
    {
        $provider = strtolower((string) $subscription->provider->value);
        $prices = $this->pricesForProvider($provider, $plan['key'] ?? '');

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
                if (($price['provider_id'] ?? null) === $providerPriceId) {
                    return $price;
                }
            }
        }

        return collect($prices)->first();
    }

    private function pricesForProvider(string $provider, string $planKey): array
    {
        $planService = app(BillingPlanService::class);
        $plans = collect($planService->plansForProvider($provider));
        $plan = $plans->firstWhere('key', $planKey);

        return $plan['prices'] ?? [];
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
}
