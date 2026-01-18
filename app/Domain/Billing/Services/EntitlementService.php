<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\Order;
use App\Domain\Organization\Models\Team;

class EntitlementService
{
    public function forTeam(Team $team): Entitlements
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "entitlements:team:{$team->id}",
            now()->addMinutes(30),
            fn () => $this->calculateEntitlements($team)
        );
    }

    public function clearCache(Team $team): void
    {
        \Illuminate\Support\Facades\Cache::forget("entitlements:team:{$team->id}");
    }

    protected function calculateEntitlements(Team $team): Entitlements
    {
        $subscription = Subscription::query()
            ->where('team_id', $team->id)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->latest('id')
            ->first();

        $seatsInUse = $this->seatCount($team);
        $planService = app(BillingPlanService::class);

        if (!$subscription) {
            $order = Order::query()
                ->where('team_id', $team->id)
                ->whereIn('status', ['paid', 'completed'])
                ->latest('id')
                ->first();

            if ($order) {
                $planKey = $order->plan_key;
                $plan = $this->resolvePlan($planKey, $planService);
                $entitlements = $plan['entitlements'] ?? [];
                $entitlements['seats_in_use'] = $seatsInUse;

                return new Entitlements($this->withDerivedEntitlements($entitlements));
            }

            $defaultPlanKey = config('saas.billing.default_plan');

            if ($defaultPlanKey) {
                $plan = $this->resolvePlan($defaultPlanKey, $planService);
                $entitlements = $plan['entitlements'] ?? [];
                $entitlements['seats_in_use'] = $seatsInUse;

                return new Entitlements($this->withDerivedEntitlements($entitlements));
            }

            return new Entitlements($this->withDerivedEntitlements([
                'max_seats' => 0,
                'seats_in_use' => $seatsInUse,
            ]));
        }

        // Handle grace period for past_due subscriptions
        if ($subscription->status === 'past_due' && !$this->withinGracePeriod($subscription)) {
            // Treat as no subscription / free plan if outside grace period
            // For now, let's fallback to default plan logic via recursion or just copy logic.
            // Simplest is to check default plan.
            $defaultPlanKey = config('saas.billing.default_plan');
            if ($defaultPlanKey) {
                 $plan = $this->resolvePlan($defaultPlanKey, $planService);
                 $entitlements = $plan['entitlements'] ?? [];
                 $entitlements['seats_in_use'] = $seatsInUse;
                 return new Entitlements($this->withDerivedEntitlements($entitlements));
            }
             return new Entitlements($this->withDerivedEntitlements([
                'max_seats' => 0,
                'seats_in_use' => $seatsInUse,
            ]));
        }

        $planKey = $subscription->plan_key;
        $plan = $this->resolvePlan($planKey, $planService);
        $entitlements = $plan['entitlements'] ?? [];

        if (!empty($plan['seat_based'])) {
            $entitlements['max_seats'] = max(1, (int) $subscription->quantity);
        }

        $entitlements['seats_in_use'] = $seatsInUse;

        return new Entitlements($this->withDerivedEntitlements($entitlements));
    }

    protected function withinGracePeriod(Subscription $subscription): bool
    {
        if ($subscription->status !== 'past_due') {
            return true;
        }
        
        // Default 5 days grace period
        $graceDays = config('saas.billing.grace_period_days', 5);
        $changedAt = $subscription->updated_at; // Approximation if status change time isn't tracked separately
        
        return $changedAt->copy()->addDays($graceDays)->isFuture();
    }

    public function seatsInUse(Team $team): int
    {
        return $this->seatCount($team);
    }

    private function seatCount(Team $team): int
    {
        $count = $team->members()->count();

        if (config('saas.seats.count_pending_invites')) {
            $count += $team->invitations()->whereNull('accepted_at')->count();
        }

        return $count;
    }

    private function withDerivedEntitlements(array $entitlements): array
    {
        $maxSeats = $entitlements['max_seats'] ?? null;
        $maxSeats = is_numeric($maxSeats) ? (int) $maxSeats : null;
        $seatsInUse = (int) ($entitlements['seats_in_use'] ?? 0);

        $hasLimit = $maxSeats !== null && $maxSeats > 0;
        $hasAvailableSeats = !$hasLimit || $seatsInUse < $maxSeats;
        $isOverSeatLimit = $hasLimit && $seatsInUse > $maxSeats;

        $entitlements['max_seats'] = $maxSeats;
        $entitlements['seats_in_use'] = $seatsInUse;
        $entitlements['has_available_seats'] = $hasAvailableSeats;
        $entitlements['is_over_seat_limit'] = $isOverSeatLimit;
        $entitlements['can_invite_members'] = $hasAvailableSeats && ($maxSeats === null || $maxSeats > 0);

        return $entitlements;
    }

    private function resolvePlan(?string $planKey, BillingPlanService $planService): array
    {
        if (!$planKey) {
            return [];
        }

        try {
            return $planService->plan($planKey);
        } catch (\RuntimeException $exception) {
            return config("saas.billing.plans.{$planKey}", []);
        }
    }
}
