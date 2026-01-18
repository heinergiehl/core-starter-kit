<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Domain\Billing\Services\CheckoutService;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingCheckoutController
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly BillingPlanService $plans,
        private readonly BillingProviderManager $providers,
        private readonly DiscountService $discounts,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string'],
            'price' => ['required', 'string'],
            'provider' => ['required', 'string'],
            'coupon' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $provider = strtolower($data['provider'] ?? $this->plans->defaultProvider());
        $plan = $this->plans->plan($data['plan']);
        $price = $this->plans->price($data['plan'], $data['price']);
        $priceCurrency = $price['currency'] ?? ($price['currencies'][$provider] ?? null);

        // Build checkout start URL for error redirects
        $checkoutStartUrl = route('checkout.start', [
            'provider' => $provider,
            'plan' => $data['plan'],
            'price' => $data['price'],
        ]);

        // Validate provider price exists
        $providerPriceId = $this->plans->providerPriceId($provider, $data['plan'], $data['price']);
        if (!$providerPriceId) {
            return redirect($checkoutStartUrl)
                ->withErrors(['billing' => 'This price is not configured for ' . ucfirst($provider) . '.'])
                ->withInput();
        }

        // Resolve discount (with better error handling)
        try {
            $discount = $this->resolveDiscount($data, $provider, $priceCurrency);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect($checkoutStartUrl)
                ->withErrors($e->errors())
                ->withInput();
        }

        // Resolve or create user (SAME FLOW FOR ALL PROVIDERS)
        $userResult = $this->checkout->resolveOrCreateUser(
            $request,
            $data['email'] ?? null,
            $data['name'] ?? null
        );

        if ($userResult instanceof RedirectResponse) {
            return $userResult;
        }

        /** @var User $user */
        $user = $userResult['user'];
        /** @var Team|null $team */
        $team = $userResult['team'];

        // Resolve team
        $teamResult = $this->checkout->resolveTeam($user, $team);
        if ($teamResult instanceof RedirectResponse) {
            return $teamResult;
        }
        $team = $teamResult;

        // Check authorization
        $user->can('billing', $team) || abort(403);

        // Prevent duplicate subscriptions
        if ($this->checkout->hasActiveSubscription($team)) {
            return redirect()->route('billing.portal')
                ->with('info', __('You already have an active subscription. Manage it in the billing portal.'));
        }

        // Calculate quantity
        $quantity = $this->checkout->calculateQuantity($plan, $team);

        // Create checkout session for tracking
        $checkoutSession = $this->checkout->createCheckoutSession(
            $user,
            $team,
            $provider,
            $data['plan'],
            $data['price'],
            $quantity
        );

        // Build checkout URLs with session UUID
        $urls = $this->checkout->buildCheckoutUrls($provider, $checkoutSession);

        // Handle Paddle checkout
        if ($provider === 'paddle') {
            return $this->handleAuthenticatedPaddleCheckout(
                $request, $data, $plan, $price, $priceCurrency, $providerPriceId,
                $user, $team, $quantity, $discount, $urls, $checkoutSession
            );
        }

        // Handle other providers (Stripe, LemonSqueezy)
        return $this->handleGenericCheckout(
            $user, $team, $data, $quantity, $discount, $urls, $provider
        );
    }

    /**
     * Handle authenticated Paddle checkout.
     */
    private function handleAuthenticatedPaddleCheckout(
        Request $request,
        array $data,
        array $plan,
        array $price,
        ?string $priceCurrency,
        string $providerPriceId,
        User $user,
        Team $team,
        int $quantity,
        ?Discount $discount,
        array $urls,
        CheckoutSession $checkoutSession,
    ): RedirectResponse {
        $transactionResult = $this->checkout->createPaddleTransaction(
            $team,
            $user,
            $data['plan'],
            $data['price'],
            $quantity,
            $urls['success'],
            $urls['cancel'],
            $discount,
            [],
            $user->email,
        );

        if ($transactionResult instanceof RedirectResponse) {
            return $transactionResult;
        }

        // Store Paddle transaction ID in checkout session
        // createPaddleTransaction returns a string (transaction ID), not an array
        $checkoutSession->update([
            'provider_session_id' => $transactionResult, // It's already a string
        ]);

        $sessionData = $this->checkout->buildPaddleSessionData(
            'paddle',
            $data['plan'],
            $data['price'],
            $plan,
            $price,
            $priceCurrency,
            $quantity,
            $providerPriceId,
            $transactionResult, // Pass the transaction ID directly
            $user,
            $team,
            $discount,
        );

        // Add success URL with auth token to session data for JS redirect
        $sessionData['success_url'] = $urls['success'];

        $request->session()->put('paddle_checkout', $sessionData);

        return redirect()->route('paddle.checkout', ['_ptxn' => $transactionResult]);
    }

    /**
     * Handle checkout for non-Paddle providers.
     */
    private function handleGenericCheckout(
        User $user,
        Team $team,
        array $data,
        int $quantity,
        ?Discount $discount,
        array $urls,
        string $provider,
    ): RedirectResponse {
        try {
            $checkoutUrl = $this->providers->adapter($provider)->createCheckout(
                $team,
                $user,
                $data['plan'],
                $data['price'],
                $quantity,
                $urls['success'],
                $urls['cancel'],
                $discount
            );
        } catch (\Throwable $exception) {
            report($exception);
            return $this->checkout->handleCheckoutError($exception);
        }

        return redirect()->away($checkoutUrl);
    }

    /**
     * Resolve discount from coupon code.
     */
    private function resolveDiscount(array $data, string $provider, ?string $priceCurrency): ?Discount
    {
        if (empty($data['coupon'])) {
            return null;
        }

        return $this->discounts->validateForCheckout(
            $data['coupon'],
            $provider,
            $data['plan'],
            $data['price'],
            $priceCurrency
        );
    }
}
