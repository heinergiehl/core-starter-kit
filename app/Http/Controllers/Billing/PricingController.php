<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\CheckoutService;
use App\Enums\SubscriptionStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingController
{
    public function __invoke(
        Request $request,
        BillingPlanService $plans,
        CheckoutService $checkoutService,
    ): View {
        $planCollection = $plans->plans();
        $user = $request->user();
        $canCheckout = (bool) $user;
        $activeSubscription = $user?->activeSubscription();
        $latestOneTimeOrder = $canCheckout ? $checkoutService->latestPaidOneTimeOrder($user) : null;
        $canChangeSubscription = $activeSubscription
            && in_array($activeSubscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true)
            && ! $activeSubscription->canceled_at;
        $activeProvider = $activeSubscription?->provider?->value;
        $activeProviderPriceId = $activeSubscription
            ? (
                data_get($activeSubscription->metadata, 'stripe_price_id')
                ?? data_get($activeSubscription->metadata, 'paddle_price_id')
                ?? data_get($activeSubscription->metadata, 'items.data.0.price.id')
                ?? data_get($activeSubscription->metadata, 'items.0.price.id')
                ?? data_get($activeSubscription->metadata, 'items.0.price_id')
            )
            : null;
        $activePriceKey = $activeSubscription
            ? (
                data_get($activeSubscription->metadata, 'price_key')
                ?? data_get($activeSubscription->metadata, 'custom_data.price_key')
            )
            : null;

        $priceStates = [];
        foreach ($planCollection as $plan) {
            foreach ($plan->prices as $price) {
                $priceIdForActiveProvider = $activeProvider ? ($price->providerIds[$activeProvider] ?? null) : null;
                $isCurrentSubscriptionPrice = $canChangeSubscription
                    && $activeSubscription->plan_key === $plan->key
                    && (
                        ($activeProviderPriceId && $priceIdForActiveProvider && $activeProviderPriceId === $priceIdForActiveProvider)
                        || ($activePriceKey && $activePriceKey === $price->key)
                    );

                $checkoutEligibility = null;
                if ($canCheckout && ! $canChangeSubscription) {
                    $checkoutEligibility = $checkoutService->evaluateCheckoutEligibility(
                        $user,
                        $plan,
                        $price,
                        $activeSubscription,
                        $latestOneTimeOrder
                    );
                }

                $priceStates[$plan->key][$price->key] = [
                    'price_id_for_active_provider' => $priceIdForActiveProvider,
                    'is_current_subscription_price' => $isCurrentSubscriptionPrice,
                    'checkout_eligibility' => $checkoutEligibility,
                ];
            }
        }

        return view('billing.pricing', [
            'plans' => $planCollection,
            'canCheckout' => $canCheckout,
            'canChangeSubscription' => $canChangeSubscription,
            'catalog' => strtolower((string) config('saas.billing.catalog', 'config')),
            'priceStates' => $priceStates,
        ]);
    }
}
