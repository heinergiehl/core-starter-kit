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
        $pendingOrder = null;

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
        } else {
             // Check for recent completed order (provisioning race condition)
             $pendingOrder = \App\Domain\Billing\Models\Order::query()
                ->where('team_id', $team->id)
                ->whereIn('status', ['paid', 'completed'])
                ->where('created_at', '>=', now()->subMinutes(10))
                ->latest('id')
                ->first();
        }
        
        $canCancel = $subscription && in_array($subscription->status, ['active', 'trialing']);

        return view('billing.index', compact('team', 'subscription', 'plan', 'invoices', 'pendingOrder', 'canCancel'));
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
     * Change subscription plan (upgrade/downgrade).
     */
    public function changePlan(Request $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam;

        abort_unless($user && $team, 403);
        abort_unless($user->can('billing', $team), 403);

        $data = $request->validate([
            'plan' => ['required', 'string'],
            'price' => ['required', 'string'],
        ]);

        $subscription = $team->activeSubscription();

        if (!$subscription) {
            return redirect()->route('pricing')
                ->with('error', __('No active subscription found. Please subscribe first.'));
        }

        // Can't change plan if pending cancellation
        if ($subscription->canceled_at) {
            return back()->with('error', __('Please resume your subscription before changing plans.'));
        }

        // Get new price ID
        $newPriceId = $this->planService->providerPriceId(
            $subscription->provider,
            $data['plan'],
            $data['price']
        );

        if (!$newPriceId) {
            return back()->with('error', __('This plan is not available for your current provider.'));
        }

        try {
            $this->updateSubscriptionPlan($subscription, $newPriceId, $data['plan']);

            $subscription->update([
                'plan_key' => $data['plan'],
            ]);

            $newPlan = $this->planService->plan($data['plan']);

            return redirect()->route('billing.index')
                ->with('success', __('Your subscription has been updated to :plan.', [
                    'plan' => $newPlan['name'] ?? ucfirst($data['plan']),
                ]));
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', __('Failed to change plan. Please try again or contact support.'));
        }
    }

    /**
     * Update subscription plan with provider.
     */
    private function updateSubscriptionPlan(Subscription $subscription, string $newPriceId, string $newPlanKey): void
    {
        match ($subscription->provider) {
            'stripe' => $this->updateStripeSubscription($subscription, $newPriceId),
            'paddle' => $this->updatePaddleSubscription($subscription, $newPriceId),
            'lemonsqueezy' => $this->updateLemonSqueezySubscription($subscription, $newPriceId),
            default => throw new \RuntimeException("Plan change not supported for provider: {$subscription->provider}"),
        };
    }

    private function updateStripeSubscription(Subscription $subscription, string $newPriceId): void
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            throw new \RuntimeException('Stripe is not configured.');
        }

        $client = new StripeClient($secret);
        
        // Get the current subscription to find the item ID
        $stripeSubscription = $client->subscriptions->retrieve($subscription->provider_id);
        $itemId = $stripeSubscription->items->data[0]->id ?? null;

        if (!$itemId) {
            throw new \RuntimeException('Could not find subscription item.');
        }

        // Update the subscription with the new price (proration by default)
        $client->subscriptions->update($subscription->provider_id, [
            'items' => [
                [
                    'id' => $itemId,
                    'price' => $newPriceId,
                ],
            ],
            'proration_behavior' => 'create_prorations',
        ]);
    }

    private function updatePaddleSubscription(Subscription $subscription, string $newPriceId): void
    {
        $apiKey = config('services.paddle.api_key');

        if (!$apiKey) {
            throw new \RuntimeException('Paddle is not configured.');
        }

        $baseUrl = config('services.paddle.env') === 'sandbox'
            ? 'https://sandbox-api.paddle.com'
            : 'https://api.paddle.com';

        // Get current subscription to find quantity
        $quantity = $subscription->quantity ?? 1;

        // Update subscription with new price
        $response = Http::withToken($apiKey)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->patch("{$baseUrl}/subscriptions/{$subscription->provider_id}", [
                'items' => [
                    [
                        'price_id' => $newPriceId,
                        'quantity' => $quantity,
                    ],
                ],
                'proration_billing_mode' => 'prorated_immediately',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to update Paddle subscription: ' . $response->body());
        }
    }

    private function updateLemonSqueezySubscription(Subscription $subscription, string $newPriceId): void
    {
        $apiKey = config('services.lemonsqueezy.api_key');

        if (!$apiKey) {
            throw new \RuntimeException('LemonSqueezy is not configured.');
        }

        // LemonSqueezy uses variant_id for plan changes
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
                        'variant_id' => (int) $newPriceId,
                    ],
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to update LemonSqueezy subscription: ' . $response->body());
        }
    }

    /**
     * Cancel subscription with provider and return the end date.
     */
    private function cancelSubscription(Subscription $subscription): \Carbon\Carbon
    {
        return match ($subscription->provider) {
            'stripe' => $this->cancelStripeSubscription($subscription),
            'paddle' => $this->cancelPaddleSubscription($subscription),
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
            'paddle' => $this->resumePaddleSubscription($subscription),
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

    private function cancelPaddleSubscription(Subscription $subscription): \Carbon\Carbon
    {
        $apiKey = trim(config('services.paddle.api_key'));

        if (!$apiKey) {
            throw new \RuntimeException('Paddle is not configured.');
        }

        $baseUrl = config('services.paddle.environment') === 'sandbox'
            ? 'https://sandbox-api.paddle.com'
            : 'https://api.paddle.com';

        // Cancel at next billing date (graceful cancellation)
        $response = Http::withToken($apiKey)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$baseUrl}/subscriptions/{$subscription->provider_id}/cancel", [
                'effective_from' => 'next_billing_period',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to cancel Paddle subscription: ' . $response->body());
        }

        // Get the scheduled cancel date
        $scheduledChange = $response->json('data.scheduled_change');
        $endsAt = $scheduledChange['effective_at'] ?? null;

        return $endsAt
            ? \Carbon\Carbon::parse($endsAt)
            : ($subscription->renews_at ?? now()->addMonth());
    }

    private function resumePaddleSubscription(Subscription $subscription): void
    {
        $apiKey = config('services.paddle.api_key');

        if (!$apiKey) {
            throw new \RuntimeException('Paddle is not configured.');
        }

        $baseUrl = config('services.paddle.environment') === 'sandbox'
            ? 'https://sandbox-api.paddle.com'
            : 'https://api.paddle.com';

        // Remove scheduled cancellation by setting scheduled_change to null
        $response = Http::withToken($apiKey)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->patch("{$baseUrl}/subscriptions/{$subscription->provider_id}", [
                'scheduled_change' => null,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to resume Paddle subscription: ' . $response->body());
        }
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

