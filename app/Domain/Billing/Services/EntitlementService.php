<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Data\Entitlements;
use App\Domain\Billing\Data\Plan;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class EntitlementService
{
    public const CACHE_KEY_PREFIX = 'entitlements:user:';

    public function __construct(
        private readonly BillingPlanService $planService
    ) {}

    public function forUser(User $user): Entitlements
    {
        return Cache::remember(
            self::CACHE_KEY_PREFIX.$user->id,
            now()->addMinutes(30),
            fn () => $this->calculateEntitlements($user)
        );
    }

    public function clearCache(User $user): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX.$user->id);
    }

    protected function calculateEntitlements(User $user): Entitlements
    {
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trialing->value,
                SubscriptionStatus::PastDue->value,
            ])
            ->latest('id')
            ->first();

        if ($subscription && ($subscription->status !== SubscriptionStatus::PastDue || $this->withinGracePeriod($subscription))) {
            return new Entitlements($this->entitlementsForPlanKey($subscription->plan_key));
        }

        $order = $this->latestPaidOneTimeOrder($user);

        if ($order) {
            return new Entitlements($this->entitlementsForPlanKey($order->plan_key));
        }

        return $this->resolveDefaultEntitlements();
    }

    protected function withinGracePeriod(Subscription $subscription): bool
    {
        if ($subscription->status !== SubscriptionStatus::PastDue) {
            return true;
        }

        // Default 5 days grace period
        $graceDays = config('saas.billing.grace_period_days', 5);
        $changedAt = $subscription->updated_at; // Approximation if status change time isn't tracked separately

        return $changedAt->copy()->addDays($graceDays)->isFuture();
    }

    private function resolvePlan(?string $planKey): ?Plan
    {
        if (! $planKey) {
            return null;
        }

        try {
            return $this->planService->plan($planKey);
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function resolveDefaultEntitlements(): Entitlements
    {
        $defaultPlanKey = config('saas.billing.default_plan');

        if ($defaultPlanKey) {
            return new Entitlements($this->entitlementsForPlanKey((string) $defaultPlanKey));
        }

        return new Entitlements([]);
    }

    /**
     * @return array<string, mixed>
     */
    private function entitlementsForPlanKey(?string $planKey): array
    {
        if (! $planKey) {
            return [];
        }

        $plan = $this->resolvePlan($planKey);

        if ($plan) {
            return $plan->entitlements;
        }

        $legacyEntitlements = config("saas.billing.plans.{$planKey}.entitlements", []);

        return is_array($legacyEntitlements) ? $legacyEntitlements : [];
    }

    private function latestPaidOneTimeOrder(User $user): ?Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                OrderStatus::Paid->value,
                OrderStatus::Completed->value,
            ])
            ->latest('id')
            ->get()
            ->first(fn (Order $order): bool => $this->isOneTimeOrder($order));
    }

    private function isOneTimeOrder(Order $order): bool
    {
        $metadata = $order->metadata ?? [];
        $subscriptionId = data_get($metadata, 'subscription_id')
            ?? data_get($metadata, 'provider_subscription_id')
            ?? data_get($metadata, 'metadata.subscription_id')
            ?? data_get($metadata, 'metadata.provider_subscription_id');

        return empty($subscriptionId);
    }
}
