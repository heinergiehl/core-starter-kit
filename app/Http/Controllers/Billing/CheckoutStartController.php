<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Services\BillingPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutStartController
{
    public function __invoke(Request $request, BillingPlanService $plans): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('pricing', ['provider' => $request->query('provider')]);
        }

        $provider = strtolower((string) $request->query('provider', $plans->defaultProvider()));
        $planKey = (string) $request->query('plan', '');
        $priceKey = (string) $request->query('price', '');

        if ($planKey === '' || $priceKey === '') {
            return redirect()->route('pricing', ['provider' => $provider]);
        }

        $plan = $plans->plan($planKey);
        $price = $plans->price($planKey, $priceKey);
        $priceCurrency = $price['currency'] ?? ($price['currencies'][$provider] ?? null);

        $providerPlan = collect($plans->plansForProvider($provider))
            ->firstWhere('key', $planKey);
        $providerPrice = $providerPlan['prices'][$priceKey] ?? null;

        if (is_array($providerPrice)) {
            $price = array_merge($price, $providerPrice);
            $priceCurrency = $providerPrice['currency'] ?? $priceCurrency;
        }

        $couponEnabledProviders = array_map('strtolower', config('saas.billing.discounts.providers', ['stripe']));
        $couponEnabled = in_array($provider, $couponEnabledProviders, true);

        return view('billing.checkout-start', [
            'provider' => $provider,
            'plan' => $plan,
            'price' => $price,
            'price_currency' => $priceCurrency,
            'coupon_enabled' => $couponEnabled,
            'social_providers' => config('saas.auth.social_providers', ['google', 'github', 'linkedin']),
        ]);
    }
}
