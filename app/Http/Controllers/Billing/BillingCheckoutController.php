<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Billing\Services\EntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingCheckoutController
{
    public function store(
        Request $request,
        BillingPlanService $plans,
        BillingProviderManager $providers,
        EntitlementService $entitlements,
        DiscountService $discounts
    ): RedirectResponse {
        $data = $request->validate([
            'plan' => ['required', 'string'],
            'price' => ['required', 'string'],
            'provider' => ['nullable', 'string'],
            'coupon' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $team = $user?->currentTeam;

        if (!$user || !$team) {
            abort(403);
        }

        $request->user()->can('billing', $team) || abort(403);

        // Prevent duplicate subscription purchase
        $hasActiveSubscription = Subscription::query()
            ->where('team_id', $team->id)
            ->whereIn('status', ['active', 'trialing'])
            ->exists();

        if ($hasActiveSubscription) {
            return redirect()->route('billing.portal')
                ->with('info', __('You already have an active subscription. Manage it in the billing portal.'));
        }

        $provider = strtolower($data['provider'] ?? $plans->defaultProvider());
        $plan = $plans->plan($data['plan']);
        $price = $plans->price($data['plan'], $data['price']);
        $priceCurrency = $price['currency'] ?? ($price['currencies'][$provider] ?? null);

        $successUrl = config('saas.billing.success_url');
        $cancelUrl = config('saas.billing.cancel_url');

        if (!$successUrl) {
            $successUrl = route('billing.processing', ['provider' => $provider], true);

            if ($provider === 'stripe') {
                $delimiter = str_contains($successUrl, '?') ? '&' : '?';
                $successUrl .= $delimiter.'session_id={CHECKOUT_SESSION_ID}';
            }
        }

        if (!$cancelUrl) {
            $cancelUrl = route('pricing', [], true);
        }

        $quantity = 1;

        if (!empty($plan['seat_based'])) {
            $quantity = max(1, $entitlements->seatsInUse($team));
        }

        $discount = null;
        if (!empty($data['coupon'])) {
            $discount = $discounts->validateForCheckout(
                $data['coupon'],
                $provider,
                $data['plan'],
                $data['price'],
                $priceCurrency
            );
        }

        $providerPriceId = $plans->providerPriceId($provider, $data['plan'], $data['price']);

        if (!$providerPriceId) {
            return back()
                ->withErrors([
                    'billing' => 'This price is not configured for '.ucfirst($provider).'.',
                ])
                ->withInput();
        }

        try {
            $checkoutUrl = $providers->adapter($provider)->createCheckout(
                $team,
                $user,
                $data['plan'],
                $data['price'],
                $quantity,
                $successUrl,
                $cancelUrl,
                $discount
            );
        } catch (\Throwable $exception) {
            report($exception);

            $message = app()->environment('local')
                ? $exception->getMessage()
                : 'Checkout failed. Please try again or contact support.';

            return back()
                ->withErrors(['billing' => $message])
                ->withInput();
        }

        return redirect()->away($checkoutUrl);
    }
}
