<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Domain\Billing\Services\CheckoutService;
use App\Domain\Billing\Services\DiscountService;
use App\Enums\DiscountType;
use App\Enums\PaymentMode;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        $checkoutRequestId = (string) Str::uuid();

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

        Log::info('billing.checkout.requested', [
            'request_id' => $checkoutRequestId,
            'request_user_id' => $request->user()?->id,
            'provider' => $provider,
            'plan_key' => $data['plan'],
            'price_key' => $data['price'],
            'has_coupon' => ! empty($data['coupon']),
        ]);

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

        Log::info('billing.checkout.user_resolved', [
            'request_id' => $checkoutRequestId,
            'user_id' => $user->id,
            'user_created' => $resolved->created,
        ]);

        $eligibility = $checkoutService->evaluateCheckoutEligibility($user, $genericPlan, $price);

        if (! $eligibility->allowed) {
            Log::warning('billing.checkout.denied', [
                'request_id' => $checkoutRequestId,
                'user_id' => $user->id,
                'provider' => $provider,
                'plan_key' => $data['plan'],
                'price_key' => $data['price'],
                'error_code' => $eligibility->errorCode,
            ]);

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

        $autoUpgradeCreditAmount = 0;
        if ($eligibility->isUpgrade && $price->mode() === PaymentMode::OneTime) {
            $autoUpgradeCreditAmount = $checkoutService->oneTimeUpgradeCreditAmount(
                $user,
                $genericPlan,
                $price
            );
        }

        if ($autoUpgradeCreditAmount > 0) {
            if ($discount) {
                return redirect()->route('checkout.start', $request->only(['provider', 'plan', 'price']))
                    ->withErrors([
                        'coupon' => __('One-time upgrade credits cannot be combined with coupons.'),
                    ])
                    ->withInput();
            }

            try {
                $discount = $this->createOneTimeUpgradeCreditDiscount(
                    providers: $providers,
                    provider: $provider,
                    userId: $user->id,
                    planKey: $data['plan'],
                    priceKey: $data['price'],
                    currency: $priceCurrency,
                    creditAmount: $autoUpgradeCreditAmount,
                    requestId: $checkoutRequestId,
                );
            } catch (Throwable $exception) {
                report($exception);
                Log::error('billing.checkout.upgrade_credit_failed', [
                    'request_id' => $checkoutRequestId,
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'plan_key' => $data['plan'],
                    'price_key' => $data['price'],
                    'error' => $exception->getMessage(),
                ]);

                return redirect()->route('checkout.start', $request->only(['provider', 'plan', 'price']))
                    ->withErrors([
                        'billing' => __('Unable to apply your upgrade credit right now. Please try again or contact support.'),
                    ])
                    ->withInput();
            }
        }

        $quantity = $checkoutService->calculateQuantity($genericPlan);

        $checkoutSession = $checkoutService->createCheckoutSession(
            $user,
            $provider,
            $data['plan'],
            $data['price'],
            $quantity
        );

        Log::info('billing.checkout.session_created', [
            'request_id' => $checkoutRequestId,
            'user_id' => $user->id,
            'session_uuid' => $checkoutSession->uuid,
            'provider' => $provider,
            'plan_key' => $data['plan'],
            'price_key' => $data['price'],
        ]);

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

            Log::info('billing.checkout.provider_redirect', [
                'request_id' => $checkoutRequestId,
                'user_id' => $user->id,
                'session_uuid' => $checkoutSession->uuid,
                'provider' => $provider,
                'provider_session_id' => $transactionId,
            ]);

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

            Log::info('billing.checkout.provider_redirect', [
                'request_id' => $checkoutRequestId,
                'user_id' => $user->id,
                'session_uuid' => $checkoutSession->uuid,
                'provider' => $provider,
                'provider_session_id' => $checkoutDto->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $checkoutSession->markCanceled();
            Log::error('billing.checkout.provider_failed', [
                'request_id' => $checkoutRequestId,
                'user_id' => $user->id,
                'session_uuid' => $checkoutSession->uuid,
                'provider' => $provider,
                'plan_key' => $data['plan'],
                'price_key' => $data['price'],
                'error' => $exception->getMessage(),
            ]);

            return $checkoutService->handleCheckoutError($exception);
        }

        return redirect()->away($checkoutUrl);
    }

    private function createOneTimeUpgradeCreditDiscount(
        BillingProviderManager $providers,
        string $provider,
        int $userId,
        string $planKey,
        string $priceKey,
        string $currency,
        int $creditAmount,
        string $requestId,
    ): Discount {
        $lockKey = implode(':', [
            'billing',
            'upgrade-credit',
            strtolower($provider),
            $userId,
            strtolower($planKey),
            strtolower($priceKey),
        ]);

        try {
            return Cache::lock($lockKey, 10)->block(3, function () use ($providers, $provider, $userId, $planKey, $priceKey, $currency, $creditAmount, $requestId): Discount {
                $existing = $this->findReusableOneTimeUpgradeCreditDiscount(
                    provider: $provider,
                    userId: $userId,
                    planKey: $planKey,
                    priceKey: $priceKey,
                    creditAmount: $creditAmount,
                );

                if ($existing) {
                    Log::info('billing.checkout.upgrade_credit_reused', [
                        'request_id' => $requestId,
                        'user_id' => $userId,
                        'provider' => $provider,
                        'plan_key' => $planKey,
                        'price_key' => $priceKey,
                        'discount_id' => $existing->id,
                    ]);

                    return $existing;
                }

                $discount = Discount::query()->create([
                    'code' => $this->upgradeCreditCode($userId),
                    'name' => 'One-time upgrade credit',
                    'description' => 'Automatic credit from previous one-time purchase',
                    'provider' => $provider,
                    'provider_type' => $provider === 'stripe' ? 'coupon' : null,
                    'type' => DiscountType::Fixed,
                    'amount' => $creditAmount,
                    'currency' => strtoupper((string) $currency),
                    'max_redemptions' => 1,
                    'is_active' => true,
                    'starts_at' => now(),
                    'ends_at' => now()->addHours(6),
                    'plan_keys' => [$planKey],
                    'price_keys' => [$priceKey],
                    'metadata' => [
                        'auto_upgrade_credit' => true,
                        'user_id' => $userId,
                        'checkout_request_id' => $requestId,
                    ],
                ]);

                try {
                    $providerId = $providers->adapter($provider)->createDiscount($discount);
                    $discount->update(['provider_id' => $providerId]);
                } catch (Throwable $exception) {
                    $discount->delete();
                    throw $exception;
                }

                Log::info('billing.checkout.upgrade_credit_created', [
                    'request_id' => $requestId,
                    'user_id' => $userId,
                    'provider' => $provider,
                    'plan_key' => $planKey,
                    'price_key' => $priceKey,
                    'discount_id' => $discount->id,
                ]);

                return $discount->fresh();
            });
        } catch (LockTimeoutException $exception) {
            throw new RuntimeException('Unable to acquire upgrade-credit lock for checkout request.', 0, $exception);
        }
    }

    private function findReusableOneTimeUpgradeCreditDiscount(
        string $provider,
        int $userId,
        string $planKey,
        string $priceKey,
        int $creditAmount,
    ): ?Discount {
        $now = now();

        $candidates = Discount::query()
            ->where('provider', strtolower($provider))
            ->where('is_active', true)
            ->whereNotNull('provider_id')
            ->where('type', DiscountType::Fixed->value)
            ->where('amount', $creditAmount)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $now);
            })
            ->latest('id')
            ->take(25)
            ->get();

        return $candidates->first(function (Discount $discount) use ($userId, $planKey, $priceKey): bool {
            $metadata = $discount->metadata ?? [];
            if (! data_get($metadata, 'auto_upgrade_credit')) {
                return false;
            }

            if ((int) data_get($metadata, 'user_id') !== $userId) {
                return false;
            }

            $planKeys = $discount->plan_keys ?? [];
            if ($planKeys !== [] && ! in_array($planKey, $planKeys, true)) {
                return false;
            }

            $priceKeys = $discount->price_keys ?? [];
            if ($priceKeys !== [] && ! in_array($priceKey, $priceKeys, true)) {
                return false;
            }

            return $discount->max_redemptions === null
                || $discount->redeemed_count < $discount->max_redemptions;
        });
    }

    private function upgradeCreditCode(int $userId): string
    {
        return 'UPG-'.$userId.'-'.Str::upper(Str::random(10));
    }
}
