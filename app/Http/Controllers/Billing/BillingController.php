<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Exceptions\BillingException;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingPlanService;
use App\Enums\SubscriptionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController
{
    public function __construct(
        private readonly BillingPlanService $planService,
        private readonly \App\Domain\Billing\Services\BillingDashboardService $dashboardService,
        private readonly \App\Domain\Billing\Services\SubscriptionService $subscriptionService
    ) {}

    /**
     * Show billing management page.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 403);

        $data = $this->dashboardService->getData($user);

        return view('billing.index', $data);
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $subscription = $user->activeSubscription();

        if (! $subscription) {
            return back()->with('error', __('No active subscription found.'));
        }

        // Can't cancel if already pending cancellation
        if ($subscription->canceled_at) {
            return back()->with('info', __('Your subscription is already scheduled for cancellation.'));
        }

        // Confirm cancellation
        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        try {
            $endsAt = $this->subscriptionService->cancel($subscription);

            return back()->with('success', __('Your subscription has been canceled. You will retain access until :date.', [
                'date' => $endsAt->format('F j, Y'),
            ]));
        } catch (\Throwable $e) {
            report($e);

            $latestSubscription = $subscription->fresh();
            if ($latestSubscription?->canceled_at && $latestSubscription->ends_at?->isFuture()) {
                return back()->with('success', __('Your subscription has been canceled. You will retain access until :date.', [
                    'date' => $latestSubscription->ends_at->format('F j, Y'),
                ]));
            }

            try {
                $syncedSubscription = $this->subscriptionService->syncSubscriptionState($latestSubscription ?? $subscription);
                if ($syncedSubscription->canceled_at && $syncedSubscription->ends_at?->isFuture()) {
                    return back()->with('success', __('Your subscription has been canceled. You will retain access until :date.', [
                        'date' => $syncedSubscription->ends_at->format('F j, Y'),
                    ]));
                }
            } catch (\Throwable $syncException) {
                report($syncException);
            }

            return back()->with('error', $this->formatCancellationError($e));
        }
    }

    /**
     * Resume a canceled subscription.
     */
    public function resume(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        // Find subscription pending cancellation (has canceled_at but still active)
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->pendingCancellation()
            ->latest('id')
            ->first();

        if (! $subscription) {
            // Check if it's already active (user might have double clicked or race condition)
            $activeSub = Subscription::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
                ->whereNull('canceled_at')
                ->latest('id')
                ->first();

            if ($activeSub) {
                return back()->with('success', __('Your subscription is active and will renew automatically.'));
            }

            return back()->with('error', __('No subscription pending cancellation found to resume.'));
        }

        try {
            $this->subscriptionService->resume($subscription);

            return back()->with('success', __('Your subscription has been resumed and will continue to renew.'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Failed to resume subscription. Please try again or contact support.'));
        }
    }

    /**
     * Change subscription plan (upgrade/downgrade).
     */
    public function changePlan(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $data = $request->validate([
            'plan' => ['required', 'string'],
            'price' => ['required', 'string'],
        ]);

        $subscription = $user->activeSubscription();

        if (! $subscription) {
            return redirect()->route('pricing')
                ->with('error', __('No active subscription found. Please subscribe first.'));
        }

        try {
            $this->subscriptionService->changePlan($user, $data['plan'], $data['price']);

            $updatedSubscription = $user->activeSubscription();
            if ($updatedSubscription) {
                try {
                    $updatedSubscription = $this->subscriptionService->syncSubscriptionState($updatedSubscription);
                } catch (\Throwable $syncException) {
                    report($syncException);
                }

                $stillPending = (string) data_get($updatedSubscription->metadata, 'pending_plan_key', '') !== ''
                    || (string) data_get($updatedSubscription->metadata, 'pending_provider_price_id', '') !== '';

                if (! $stillPending && $updatedSubscription->plan_key === $data['plan']) {
                    return redirect()->route('billing.index')
                        ->with('success', __('Your subscription has been updated to :plan.', [
                            'plan' => $this->resolvePlanName($data['plan']),
                        ]));
                }
            }

            return redirect()->route('billing.index')
                ->with('info', __('Your subscription plan change to :plan is pending provider confirmation.', [
                    'plan' => $this->resolvePlanName($data['plan']),
                ]));
        } catch (BillingException $e) {
            report($e);

            if (in_array($e->getErrorCode(), ['BILLING_PLAN_ALREADY_ACTIVE', 'BILLING_PLAN_CHANGE_ALREADY_PENDING'], true)) {
                return back()->with('info', $e->getMessage());
            }

            return back()->with('error', $this->formatPlanChangeError($e));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Failed to change plan. Please try again or contact support.'));
        }
    }

    /**
     * Retry provider sync for a pending subscription plan change.
     */
    public function syncPendingPlanChange(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $subscription = $user->activeSubscription();
        if (! $subscription) {
            return back()->with('error', __('No active subscription found.'));
        }

        $hasPendingPlanChange = (string) data_get($subscription->metadata, 'pending_plan_key', '') !== ''
            || (string) data_get($subscription->metadata, 'pending_provider_price_id', '') !== '';

        if (! $hasPendingPlanChange) {
            return back()->with('info', __('No pending plan change found to sync.'));
        }

        try {
            $subscription = $this->subscriptionService->syncSubscriptionState($subscription);

            $stillPending = (string) data_get($subscription->metadata, 'pending_plan_key', '') !== ''
                || (string) data_get($subscription->metadata, 'pending_provider_price_id', '') !== '';

            if ($stillPending) {
                return back()->with('info', __('Sync requested. Your provider still reports this plan change as pending.'));
            }

            return back()->with('success', __('Subscription state synced successfully. Your current plan is now up to date.'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Failed to sync subscription state from your billing provider. Please try again or contact support.'));
        }
    }

    private function resolvePlanName(?string $planKey): string
    {
        if (! $planKey) {
            return 'subscription';
        }

        try {
            $plan = $this->planService->plan($planKey);

            return $plan->name ?: ucfirst($planKey);
        } catch (\Throwable) {
            return ucfirst($planKey);
        }
    }

    private function formatCancellationError(\Throwable $exception): string
    {
        if ($exception instanceof BillingException) {
            return match ($exception->getErrorCode()) {
                'BILLING_PLAN_CHANGE_ALREADY_PENDING' => __('A subscription plan change is still pending provider confirmation. Please wait for it to finish before cancelling.'),
                default => __('Failed to cancel subscription. Please try again or contact support.'),
            };
        }

        $message = $exception->getMessage();

        if (str_contains($message, 'subscription_locked_pending_changes')) {
            return __('Your subscription already has a pending change with Paddle. Please wait for it to complete or manage it in the billing portal.');
        }

        return __('Failed to cancel subscription. Please try again or contact support.');
    }

    private function formatPlanChangeError(\Throwable $exception): string
    {
        if ($exception instanceof BillingException) {
            if ($exception->getErrorCode() === 'BILLING_ACTION_FAILED') {
                $message = strtolower($exception->getMessage());

                if (str_contains($message, 'no such price') || str_contains($message, 'price not found')) {
                    return __('The selected plan price is not configured in your billing provider account. Please update the provider price mapping and try again.');
                }
            }

            return match ($exception->getErrorCode()) {
                'BILLING_SUBSCRIPTION_PENDING_CANCELLATION' => __('Please resume your pending cancellation before changing plans.'),
                'BILLING_PROVIDER_PRICE_UNAVAILABLE' => __('The selected plan is not available for your billing provider.'),
                'BILLING_PLAN_CHANGE_INVALID_TARGET' => __('You can only switch between recurring subscription plans.'),
                'BILLING_PLAN_CHANGE_ALREADY_PENDING' => __('This plan change is already pending provider confirmation.'),
                'BILLING_UNKNOWN_PLAN', 'BILLING_UNKNOWN_PRICE' => __('The selected plan or billing interval is invalid.'),
                default => __('Failed to change plan. Please try again or contact support.'),
            };
        }

        if (str_contains($exception->getMessage(), 'subscription_locked_pending_changes')) {
            return __('Your subscription has a pending provider change. Please wait for it to complete or use the billing portal.');
        }

        return __('Failed to change plan. Please try again or contact support.');
    }
}
