<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CheckoutStartController
{
    public function __invoke(
        Request $request,
        BillingPlanService $plans,
        CheckoutService $checkoutService,
    ): View|RedirectResponse
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

        try {
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
        } catch (RuntimeException) {
            return redirect()->route('pricing');
        }

        if (! $price) {
            return redirect()->route('pricing');
        }

        $couponEnabledProviders = array_map('strtolower', config('saas.billing.discounts.providers', ['stripe']));
        $couponEnabled = $provider && in_array($provider, $couponEnabledProviders, true);
        $upgradeCreditAmount = 0;
        $upgradeAmountDue = null;

        $user = $request->user();
        if ($user) {
            $eligibility = $checkoutService->evaluateCheckoutEligibility($user, $plan, $price);

            if ($eligibility->allowed && $eligibility->isUpgrade && $price->mode() === \App\Enums\PaymentMode::OneTime) {
                $upgradeCreditAmount = $checkoutService->oneTimeUpgradeCreditAmount($user, $plan, $price);

                if ($upgradeCreditAmount > 0) {
                    $targetAmount = (int) round((float) $price->amount);
                    $upgradeAmountDue = max($targetAmount - $upgradeCreditAmount, 0);
                    $couponEnabled = false;
                }
            }
        }

        return view('billing.checkout-start', [
            'provider' => $provider,
            'providers' => $providers,
            'plan' => $plan,
            'price' => $price,
            'price_currency' => $priceCurrency,
            'coupon_enabled' => $couponEnabled,
            'upgrade_credit_amount' => $upgradeCreditAmount,
            'upgrade_amount_due' => $upgradeAmountDue,
            'social_providers' => config('saas.auth.social_providers', ['google', 'github', 'linkedin']),
        ]);
    }
}
