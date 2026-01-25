<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Models\User;

class EntitlementService
{
    public function forUser(User $user): Entitlements
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "entitlements:user:{$user->id}",
            now()->addMinutes(30),
            fn () => $this->calculateEntitlements($user)
        );
    }

    public function clearCache(User $user): void
    {
        \Illuminate\Support\Facades\Cache::forget("entitlements:user:{$user->id}");
    }

    protected function calculateEntitlements(User $user): Entitlements
    {
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                \App\Enums\SubscriptionStatus::Active,
                \App\Enums\SubscriptionStatus::Trialing,
                \App\Enums\SubscriptionStatus::PastDue,
            ])
            ->latest('id')
            ->first();

        $planService = app(BillingPlanService::class);

        if (! $subscription) {
            $order = Order::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [
                    \App\Enums\OrderStatus::Paid,
                    \App\Enums\OrderStatus::Completed,
                ])
                ->latest('id')
                ->first();

            if ($order) {
                $planKey = $order->plan_key;
                $plan = $this->resolvePlan($planKey, $planService);
                $entitlements = $plan['entitlements'] ?? [];

                return new Entitlements($entitlements);
            }

            return $this->resolveDefaultEntitlements($planService);
        }

        // Handle grace period for past_due subscriptions
        if ($subscription->status === \App\Enums\SubscriptionStatus::PastDue && ! $this->withinGracePeriod($subscription)) {
            // Treat as no subscription / free plan if outside grace period
            // For now, let's fallback to default plan logic via recursion or just copy logic.
            // Simplest is to check default plan.
            return $this->resolveDefaultEntitlements($planService);
        }

        $planKey = $subscription->plan_key;
        $plan = $this->resolvePlan($planKey, $planService);
        $entitlements = $plan['entitlements'] ?? [];

        return new Entitlements($entitlements);
    }

    protected function withinGracePeriod(Subscription $subscription): bool
    {
        if ($subscription->status !== \App\Enums\SubscriptionStatus::PastDue) {
            return true;
        }

        // Default 5 days grace period
        $graceDays = config('saas.billing.grace_period_days', 5);
        $changedAt = $subscription->updated_at; // Approximation if status change time isn't tracked separately

        return $changedAt->copy()->addDays($graceDays)->isFuture();
    }

    private function resolvePlan(?string $planKey, BillingPlanService $planService): array
    {
        if (! $planKey) {
            return [];
        }

        try {
            return $planService->plan($planKey);
        } catch (\RuntimeException $exception) {
            return config("saas.billing.plans.{$planKey}", []);
        }
    }

    private function resolveDefaultEntitlements(BillingPlanService $planService): Entitlements
    {
        $defaultPlanKey = config('saas.billing.default_plan');

        if ($defaultPlanKey) {
            $plan = $this->resolvePlan($defaultPlanKey, $planService);
            $entitlements = $plan['entitlements'] ?? [];

            return new Entitlements($entitlements);
        }

        return new Entitlements([]);
    }
}
