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
        $providers = $plans->providers();
        $provider = $request->query('provider');

        // If no provider selected, and only one is available, select it automatically
        if (empty($provider) && count($providers) === 1) {
            $provider = $providers[0];
        }

        $provider = in_array($provider, $providers, true) ? $provider : null;

        $planKey = (string) $request->query('plan', '');
        $priceKey = (string) $request->query('price', '');

        if ($planKey === '' || $priceKey === '') {
            return redirect()->route('pricing');
        }

        // Get the base plan/price logic
        // If provider is selected, we get the specific version
        if ($provider) {
             $plan = $plans->plansForProvider($provider)
                ->firstWhere('key', $planKey);
             
             if (! $plan) {
                 return redirect()->route('pricing');
             }

             $price = $plan->getPrice($priceKey);
             $priceCurrency = $price?->currency ?? 'USD';
        } else {
             // No provider selected, get generic plan
             $plan = $plans->plan($planKey);
             $price = $plans->price($planKey, $priceKey);
             
             // If no provider selected, just pick the currency from the default/first available
             $priceCurrency = $price->currency;
        }

        if (! $price) {
             return redirect()->route('pricing');
        }

        $couponEnabledProviders = array_map('strtolower', config('saas.billing.discounts.providers', ['stripe']));
        $couponEnabled = $provider && in_array($provider, $couponEnabledProviders, true);

        return view('billing.checkout-start', [
            'provider' => $provider,
            'providers' => $providers,
            'plan' => $plan,
            'price' => $price,
            'price_currency' => $priceCurrency,
            'coupon_enabled' => $couponEnabled,
            'social_providers' => config('saas.auth.social_providers', ['google', 'github', 'linkedin']),
        ]);
    }
}
