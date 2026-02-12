<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\CheckoutService;
use App\Enums\SubscriptionStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $pendingProviderPriceId = $canChangeSubscription
            ? (string) data_get($activeSubscription?->metadata, 'pending_provider_price_id', '')
            : '';
        $hasPendingPlanChange = $pendingProviderPriceId !== '';
        $pendingPlanKey = $hasPendingPlanChange
            ? (string) data_get($activeSubscription?->metadata, 'pending_plan_key', '')
            : '';
        $pendingPlanName = null;
        if ($pendingPlanKey !== '') {
            $pendingPlan = $planCollection->first(fn ($plan) => $plan->key === $pendingPlanKey);
            $pendingPlanName = $pendingPlan?->name ?: ucfirst($pendingPlanKey);
        }
        $pendingPriceKey = $hasPendingPlanChange
            ? (string) data_get($activeSubscription?->metadata, 'pending_price_key', '')
            : '';

        $currentSubscriptionContext = $this->resolveCurrentSubscriptionContext(
            $planCollection,
            $activeSubscription?->plan_key,
            $activeProvider,
            $activeProviderPriceId,
            $activePriceKey
        );

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
                    'plan_change_pending' => $hasPendingPlanChange,
                ];
            }
        }

        return view('billing.pricing', [
            'plans' => $planCollection,
            'canCheckout' => $canCheckout,
            'canChangeSubscription' => $canChangeSubscription,
            'catalog' => strtolower((string) config('saas.billing.catalog', 'config')),
            'priceStates' => $priceStates,
            'currentSubscriptionContext' => $currentSubscriptionContext,
            'hasPendingPlanChange' => $hasPendingPlanChange,
            'pendingPlanName' => $pendingPlanName,
            'pendingPriceKey' => $pendingPriceKey,
        ]);
    }

    /**
     * @return array{
     *   plan_name: ?string,
     *   amount_minor: ?int,
     *   currency: ?string,
     *   interval: ?string,
     *   price_key: ?string,
     *   price_label: ?string
     * }
     */
    private function resolveCurrentSubscriptionContext(
        Collection $plans,
        ?string $currentPlanKey,
        ?string $activeProvider,
        ?string $activeProviderPriceId,
        ?string $activePriceKey
    ): array {
        if (! $currentPlanKey) {
            return [
                'plan_name' => null,
                'amount_minor' => null,
                'currency' => null,
                'interval' => null,
                'price_key' => null,
                'price_label' => null,
            ];
        }

        $currentPlan = $plans->first(fn ($plan) => $plan->key === $currentPlanKey);

        if (! $currentPlan) {
            return [
                'plan_name' => ucfirst($currentPlanKey),
                'amount_minor' => null,
                'currency' => null,
                'interval' => null,
                'price_key' => null,
                'price_label' => null,
            ];
        }

        $matchingPrice = collect($currentPlan->prices)->first(function ($price) use ($activePriceKey, $activeProvider, $activeProviderPriceId) {
            if ($activePriceKey && $price->key === $activePriceKey) {
                return true;
            }

            if ($activeProvider && $activeProviderPriceId) {
                return ($price->providerIds[$activeProvider] ?? null) === $activeProviderPriceId;
            }

            return false;
        });

        if (! $matchingPrice) {
            return [
                'plan_name' => $currentPlan->name ?: ucfirst($currentPlanKey),
                'amount_minor' => null,
                'currency' => null,
                'interval' => null,
                'price_key' => null,
                'price_label' => null,
            ];
        }

        $amount = (float) $matchingPrice->amount;
        $amountMinor = $matchingPrice->amountIsMinor
            ? (int) round($amount)
            : (int) round($amount * 100);

        return [
            'plan_name' => $currentPlan->name ?: ucfirst($currentPlanKey),
            'amount_minor' => $amountMinor > 0 ? $amountMinor : null,
            'currency' => strtoupper((string) ($matchingPrice->currency ?? 'USD')),
            'interval' => $matchingPrice->interval ?: null,
            'price_key' => $matchingPrice->key,
            'price_label' => $matchingPrice->label ?: ucfirst($matchingPrice->key),
        ];
    }
}
