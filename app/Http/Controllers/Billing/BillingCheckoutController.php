<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Domain\Billing\Services\CheckoutService;
use App\Domain\Billing\Services\DiscountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
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
        $availableProviders = $plans->providers();

        if (! in_array($provider, $availableProviders, true)) {
            return back()
                ->withErrors(['billing' => 'Selected payment provider is not available.'])
                ->withInput();
        }

        try {
            // Get generic plan/price first for validation
            $genericPlan = $plans->plan($data['plan']);
            $price = $genericPlan->getPrice($data['price']); // Generic price
        } catch (RuntimeException) {
            return back()
                ->withErrors(['billing' => 'The selected plan or price is invalid.'])
                ->withInput();
        }

        if (! $price) {
            return back()->withErrors(['billing' => 'Invalid price selected.']);
        }

        // Try to get provider-specific resolved price
        $providerPlan = $plans->plansForProvider($provider)
            ->firstWhere('key', $data['plan']);

        if (! $providerPlan) {
            return back()
                ->withErrors(['billing' => 'This plan is not available for the selected provider.'])
                ->withInput();
        }

        $providerPrice = $providerPlan->getPrice($data['price']);

        if ($providerPrice) {
            $price = $providerPrice; // Use resolved DTO
        }

        $priceCurrency = $price->currency;

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
            report($e);
            session(['url.intended' => route('checkout.start', $request->only(['provider', 'plan', 'price']))]);

            return redirect()->route('login')
                ->with('info', __('An account with this email already exists. Please log in to continue.'));
        }

        $user = $resolved->user;

        $eligibility = $checkoutService->evaluateCheckoutEligibility($user, $genericPlan, $price);

        if (! $eligibility->allowed) {
            if (in_array($eligibility->errorCode, [
                'BILLING_CHECKOUT_BLOCKED_ACTIVE_SUBSCRIPTION',
            ], true)) {
                return redirect()->route('billing.index')
                    ->with('info', $eligibility->message ?? __('You already have an active subscription. Use billing to change your plan.'));
            }

            return redirect()->route('checkout.start', $request->only(['provider', 'plan', 'price']))
                ->withErrors([
                    'billing' => $eligibility->message ?? __('Checkout is not available for this plan.'),
                ])
                ->withInput();
        }

        $quantity = $checkoutService->calculateQuantity($genericPlan);

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
            $transactionDto = $checkoutService->createPaddleTransaction(
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

            if ($transactionDto instanceof RedirectResponse) {
                $checkoutSession->markCanceled();

                return $transactionDto;
            }

            $transactionId = $transactionDto->id;

            $checkoutSession->update([
                'provider_session_id' => $transactionId,
            ]);

            $paddleSession = $checkoutService->buildPaddleSessionData(
                $provider,
                $data['plan'],
                $data['price'],
                $genericPlan, 
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
            $checkoutDto = $providers->adapter($provider)->createCheckout(
                $user,
                $data['plan'],
                $data['price'],
                $quantity,
                $successUrl,
                $cancelUrl,
                $discount
            );
            $checkoutUrl = $checkoutDto->url;

            if (! empty($checkoutDto->id)) {
                $checkoutSession->update([
                    'provider_session_id' => $checkoutDto->id,
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);
            $checkoutSession->markCanceled();

            return $checkoutService->handleCheckoutError($exception);
        }

        return redirect()->away($checkoutUrl);
    }
}
