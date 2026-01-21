<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Domain\Billing\Services\CheckoutService;
use App\Domain\Billing\Services\DiscountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class BillingCheckoutController
{
    public function store(
        Request $request,
        BillingPlanService $plans,
        BillingProviderManager $providers,
        DiscountService $discounts,
        CheckoutService $checkoutService
    ): RedirectResponse {
        $data = $request->validate([
            'plan' => ['required', 'string'],
            'price' => ['required', 'string'],
            'provider' => ['required', 'string'],
            'coupon' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $provider = strtolower($data['provider'] ?? $plans->defaultProvider());

        $plan = $plans->plan($data['plan']);
        $price = $plans->price($data['plan'], $data['price']);
        $priceCurrency = $price['currency'] ?? ($price['currencies'][$provider] ?? null);

        $providerPlan = collect($plans->plansForProvider($provider))
            ->firstWhere('key', $data['plan']) ?? [];
        $providerPrice = $providerPlan['prices'][$data['price']] ?? null;

        if (is_array($providerPrice)) {
            $price = array_merge($price, $providerPrice);
            $priceCurrency = $providerPrice['currency'] ?? $priceCurrency;
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
                    'billing' => 'This price is not configured for ' . ucfirst($provider) . '.',
                ])
                ->withInput();
        }

        $resolved = $checkoutService->resolveOrCreateUser(
            $request,
            $data['email'] ?? null,
            $data['name'] ?? null
        );

        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        $user = $resolved['user'];
        $team = $checkoutService->resolveTeam($user, $resolved['team']);

        if ($team instanceof RedirectResponse) {
            return $team;
        }

        abort_unless($user->can('billing', $team), 403);

        $planType = (string) ($plan['type'] ?? 'subscription');

        if ($planType === 'one_time' && $checkoutService->hasAnyPurchase($team)) {
            return redirect()->route('billing.index')
                ->with('info', __('You already have an active plan or purchase. Manage it in billing.'));
        }

        if ($planType !== 'one_time' && $checkoutService->hasActiveSubscription($team)) {
            return redirect()->route('billing.portal')
                ->with('info', __('You already have an active subscription. Manage it in the billing portal.'));
        }

        $quantity = $checkoutService->calculateQuantity($plan, $team);

        $checkoutSession = $checkoutService->createCheckoutSession(
            $user,
            $team,
            $provider,
            $data['plan'],
            $data['price'],
            $quantity
        );

        $urls = $checkoutService->buildCheckoutUrls($provider, $checkoutSession);
        $successUrl = $urls['success'];
        $cancelUrl = $urls['cancel'];

        if ($provider === 'paddle') {
            $transactionId = $checkoutService->createPaddleTransaction(
                $team,
                $user,
                $data['plan'],
                $data['price'],
                $quantity,
                $successUrl,
                $cancelUrl,
                $discount,
                [],
                $user->email
            );

            if ($transactionId instanceof RedirectResponse) {
                $checkoutSession->markCanceled();
                return $transactionId;
            }

            $checkoutSession->update([
                'provider_session_id' => $transactionId,
            ]);

            $paddleSession = $checkoutService->buildPaddleSessionData(
                $provider,
                $data['plan'],
                $data['price'],
                $plan,
                $price,
                $priceCurrency,
                $quantity,
                $providerPriceId,
                $transactionId,
                $user,
                $team,
                $discount
            );
            $paddleSession['success_url'] = $successUrl;
            $paddleSession['cancel_url'] = $cancelUrl;

            $request->session()->put('paddle_checkout', $paddleSession);

            return redirect()->route('paddle.checkout', ['_ptxn' => $transactionId]);
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
        } catch (Throwable $exception) {
            report($exception);
            $checkoutSession->markCanceled();
            return $checkoutService->handleCheckoutError($exception);
        }

        return redirect()->away($checkoutUrl);
    }
}
