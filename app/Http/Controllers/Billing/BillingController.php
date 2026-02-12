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

            // Get plan name for success message (optional, could be moved to service return, but acceptable here)
            // For simplicity, we just use the plan key or let the user see the update on index
            // But let's keep the nice message
            $newPlanName = $this->resolvePlanName($data['plan']);

            return redirect()->route('billing.index')
                ->with('success', __('Your subscription plan change to :plan is pending provider confirmation.', [
                    'plan' => $newPlanName,
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
        $message = $exception->getMessage();

        if (str_contains($message, 'subscription_locked_pending_changes')) {
            return __('Your subscription already has a pending change with Paddle. Please wait for it to complete or manage it in the billing portal.');
        }

        return __('Failed to cancel subscription. Please try again or contact support.');
    }

    private function formatPlanChangeError(\Throwable $exception): string
    {
        if ($exception instanceof BillingException) {
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
