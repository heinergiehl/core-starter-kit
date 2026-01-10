<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\BillingProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Stripe\StripeClient;

class BillingController
{
    public function __construct(
        private readonly BillingPlanService $planService,
        private readonly BillingProviderManager $providerManager
    ) {}

    /**
     * Show billing management page.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $team = $user?->currentTeam;

        abort_unless($user && $team, 403);
        abort_unless($user->can('billing', $team), 403);

        $subscription = $team->activeSubscription();
        $plan = null;
        $invoices = collect();

        if ($subscription) {
            try {
                $plan = $this->planService->plan($subscription->plan_key);
            } catch (\RuntimeException) {
                $plan = ['name' => ucfirst($subscription->plan_key), 'key' => $subscription->plan_key];
            }

            $invoices = Invoice::query()
                ->where('team_id', $team->id)
                ->latest('issued_at')
                ->take(5)
                ->get();
        }

        return view('billing.index', [
            'team' => $team,
            'subscription' => $subscription,
            'plan' => $plan,
            'invoices' => $invoices,
            'canCancel' => $subscription && in_array($subscription->status, ['active', 'trialing']),
        ]);
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam;

        abort_unless($user && $team, 403);
        abort_unless($user->can('billing', $team), 403);

        $subscription = $team->activeSubscription();

        if (!$subscription) {
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
            $endsAt = $this->cancelSubscription($subscription);

            // Keep status active but mark as pending cancellation
            $subscription->update([
                'canceled_at' => now(),
                'ends_at' => $endsAt,
            ]);

            return back()->with('success', __('Your subscription has been canceled. You will retain access until :date.', [
                'date' => $endsAt->format('F j, Y'),
            ]));
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', __('Failed to cancel subscription. Please try again or contact support.'));
        }
    }

    /**
     * Resume a canceled subscription.
     */
    public function resume(Request $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam;

        abort_unless($user && $team, 403);
        abort_unless($user->can('billing', $team), 403);

        // Find subscription pending cancellation (has canceled_at but still active)
        $subscription = Subscription::query()
            ->where('team_id', $team->id)
            ->whereIn('status', ['active', 'trialing'])
            ->whereNotNull('canceled_at')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now())
            ->latest('id')
            ->first();

        if (!$subscription) {
            return back()->with('error', __('No subscription pending cancellation found to resume.'));
        }

        try {
            $this->resumeSubscription($subscription);

            $subscription->update([
                'canceled_at' => null,
                'ends_at' => null,
            ]);

            return back()->with('success', __('Your subscription has been resumed and will continue to renew.'));
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', __('Failed to resume subscription. Please try again or contact support.'));
        }
    }

    /**
     * Cancel subscription with provider and return the end date.
     */
    private function cancelSubscription(Subscription $subscription): \Carbon\Carbon
    {
        return match ($subscription->provider) {
            'stripe' => $this->cancelStripeSubscription($subscription),
            'lemonsqueezy' => $this->cancelLemonSqueezySubscription($subscription),
            default => throw new \RuntimeException("Cancellation not supported for provider: {$subscription->provider}"),
        };
    }

    /**
     * Resume subscription with provider.
     */
    private function resumeSubscription(Subscription $subscription): void
    {
        match ($subscription->provider) {
            'stripe' => $this->resumeStripeSubscription($subscription),
            'lemonsqueezy' => $this->resumeLemonSqueezySubscription($subscription),
            default => throw new \RuntimeException("Resume not supported for provider: {$subscription->provider}"),
        };
    }

    private function cancelStripeSubscription(Subscription $subscription): \Carbon\Carbon
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            throw new \RuntimeException('Stripe is not configured.');
        }

        $client = new StripeClient($secret);
        
        // Cancel at period end (graceful cancellation)
        $stripeSubscription = $client->subscriptions->update($subscription->provider_id, [
            'cancel_at_period_end' => true,
        ]);

        return \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
    }

    private function resumeStripeSubscription(Subscription $subscription): void
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            throw new \RuntimeException('Stripe is not configured.');
        }

        $client = new StripeClient($secret);
        
        $client->subscriptions->update($subscription->provider_id, [
            'cancel_at_period_end' => false,
        ]);
    }

    private function cancelLemonSqueezySubscription(Subscription $subscription): \Carbon\Carbon
    {
        $apiKey = config('services.lemonsqueezy.api_key');

        if (!$apiKey) {
            throw new \RuntimeException('LemonSqueezy is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ])
            ->delete("https://api.lemonsqueezy.com/v1/subscriptions/{$subscription->provider_id}");

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to cancel LemonSqueezy subscription: ' . $response->body());
        }

        // Get ends_at from response or use renews_at
        $endsAt = $response->json('data.attributes.ends_at');
        
        return $endsAt 
            ? \Carbon\Carbon::parse($endsAt)
            : ($subscription->renews_at ?? now()->addMonth());
    }

    private function resumeLemonSqueezySubscription(Subscription $subscription): void
    {
        $apiKey = config('services.lemonsqueezy.api_key');

        if (!$apiKey) {
            throw new \RuntimeException('LemonSqueezy is not configured.');
        }

        // LemonSqueezy uses PATCH to update subscription status
        $response = Http::withToken($apiKey)
            ->withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ])
            ->patch("https://api.lemonsqueezy.com/v1/subscriptions/{$subscription->provider_id}", [
                'data' => [
                    'type' => 'subscriptions',
                    'id' => $subscription->provider_id,
                    'attributes' => [
                        'cancelled' => false,
                    ],
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to resume LemonSqueezy subscription: ' . $response->body());
        }
    }
}
