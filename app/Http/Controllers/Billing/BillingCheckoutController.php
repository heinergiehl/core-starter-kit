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
        if (! empty($data['coupon'])) {
            $discount = $discounts->validateForCheckout(
                $data['coupon'],
                $provider,
                $data['plan'],
                $data['price'],
                $priceCurrency
            );
        }

        $providerPriceId = $plans->providerPriceId($provider, $data['plan'], $data['price']);

        if (! $providerPriceId) {
            return back()
                ->withErrors([
                    'billing' => 'This price is not configured for '.ucfirst($provider).'.',
                ])
                ->withInput();
        }

        try {
            $resolved = $checkoutService->resolveOrCreateUser(
                $request,
                $data['email'] ?? null,
                $data['name'] ?? null
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('checkout.start', $request->only(['provider', 'plan', 'price']))
                ->withErrors($e->errors())
                ->withInput();
        } catch (\App\Domain\Billing\Exceptions\BillingException $e) {
            error_log($e->getMessage()); // Or check error code if implemented
            // Assuming this is the user exists error
            session(['url.intended' => route('checkout.start', $request->only(['provider', 'plan', 'price']))]);

            return redirect()->route('login')
                ->with('info', __('An account with this email already exists. Please log in to continue.'));
        }

        $user = $resolved['user'];

        $planType = (string) ($plan['type'] ?? 'subscription');

        if ($planType === 'one_time' && $checkoutService->hasAnyPurchase($user)) {
            return redirect()->route('billing.index')
                ->with('info', __('You already have an active plan or purchase. Manage it in billing.'));
        }

        $quantity = $checkoutService->calculateQuantity($plan);

        $checkoutSession = $checkoutService->createCheckoutSession(
            $user,
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
                $discount
            );
            $paddleSession['success_url'] = $successUrl;
            $paddleSession['cancel_url'] = $cancelUrl;

            $request->session()->put('paddle_checkout', $paddleSession);

            return redirect()->route('paddle.checkout', ['_ptxn' => $transactionId]);
        }

        try {
            $checkoutUrl = $providers->adapter($provider)->createCheckout(
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
