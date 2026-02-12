<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Adapters\PaddleAdapter;
use App\Domain\Billing\Data\CheckoutEligibility;
use App\Domain\Billing\Data\CheckoutUserDTO;
use App\Domain\Billing\Data\Plan;
use App\Domain\Billing\Data\Price;
use App\Domain\Billing\Data\TransactionDTO;
use App\Domain\Billing\Exceptions\BillingException;
use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Enums\BillingProvider;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orchestrates the checkout flow for all billing providers.
 *
 * Responsibilities:
 * - User provisioning for guest checkouts
 * - Paddle transaction creation with error handling
 * - Session data management for inline checkouts
 */
class CheckoutService
{
    public function __construct(
        private readonly BillingProviderManager $providerManager,
    ) {}

    /**
     * Resolve or create a user for checkout.
     *
     * For guest checkouts:
     * - If user exists with no purchase → reuse (abandoned checkout retry)
     * - If user exists with purchase → error (prevent duplicate purchase)
     * - If user doesn't exist → create new
     *
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \App\Domain\Billing\Exceptions\BillingException
     */
    public function resolveOrCreateUser(Request $request, ?string $email, ?string $name): CheckoutUserDTO
    {
        $user = $request->user();

        if ($user) {
            return new CheckoutUserDTO($user, false);
        }

        if (! $email) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => __('Email is required.'),
            ]);
        }

        // Check if user already exists
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            // If user has any completed purchase, block retry
            if ($this->hasAnyPurchase($existingUser)) {
                throw BillingException::userAlreadyExists($email);
            }

            // Reuse incomplete user (abandoned checkout retry)
            return new CheckoutUserDTO($existingUser, false);
        }

        // Create new user
        $user = User::create([
            'name' => ! empty($name) ? $name : $this->nameFromEmail($email),
            'email' => $email,
            'password' => Str::random(32),
        ]);

        return new CheckoutUserDTO($user, true);
    }

    /**
     * Check if user already has an active subscription.
     */
    public function hasActiveSubscription(User $user): bool
    {
        return Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->exists();
    }

    /**
     * Check if user has any purchase (subscription OR one-time order).
     *
     * This is used to prevent duplicate purchases - once a user has bought
     * either a subscription or a one-time product, they cannot purchase again.
     */
    public function hasAnyPurchase(User $user): bool
    {
        // Check for active subscription
        if ($this->hasActiveSubscription($user)) {
            return true;
        }

        // Check for any paid one-time order
        return Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Completed->value])
            ->exists();
    }

    public function latestPaidOneTimeOrder(User $user): ?Order
    {
        $order = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Completed->value])
            ->where(function ($query): void {
                $query->whereNull('metadata->subscription_id')
                    ->whereNull('metadata->provider_subscription_id');
            })
            ->latest('id')
            ->first();

        if ($order) {
            return $order;
        }

        return Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Completed->value])
            ->latest('id')
            ->cursor()
            ->first(fn (Order $candidate): bool => $this->isOneTimeOrder($candidate));
    }

    public function evaluateCheckoutEligibility(
        User $user,
        Plan $targetPlan,
        Price $targetPrice,
        ?Subscription $activeSubscription = null,
        ?Order $latestOneTimeOrder = null,
    ): CheckoutEligibility {
        $activeSubscription ??= $user->activeSubscription();

        // Active/trialing subscriptions are managed via change-plan, not fresh checkout.
        if ($activeSubscription) {
            return CheckoutEligibility::deny(
                'BILLING_CHECKOUT_BLOCKED_ACTIVE_SUBSCRIPTION',
                __('You already have an active subscription. Use billing to change your plan.')
            );
        }

        $latestOneTimeOrder ??= $this->latestPaidOneTimeOrder($user);

        // First-time checkout.
        if (! $latestOneTimeOrder) {
            return CheckoutEligibility::allow();
        }

        $targetMode = $targetPrice->mode();

        // One-time customer converting to subscription is allowed.
        if ($targetMode === PaymentMode::Subscription) {
            return CheckoutEligibility::allow(isUpgrade: true);
        }

        $targetCurrency = strtoupper((string) $targetPrice->currency);
        $currentCurrency = strtoupper((string) ($latestOneTimeOrder->currency ?? ''));

        if ($targetCurrency !== '' && $currentCurrency !== '' && $targetCurrency !== $currentCurrency) {
            return CheckoutEligibility::deny(
                'BILLING_ONE_TIME_UPGRADE_CURRENCY_MISMATCH',
                __('One-time upgrades require the same billing currency. Please contact support for manual assistance.')
            );
        }

        $currentPriceKey = (string) data_get($latestOneTimeOrder->metadata, 'price_key', '');
        if ($latestOneTimeOrder->plan_key === $targetPlan->key
            && ($currentPriceKey === '' || $currentPriceKey === $targetPrice->key)
        ) {
            return CheckoutEligibility::deny(
                'BILLING_ONE_TIME_ALREADY_PURCHASED',
                __('You already own this one-time plan.')
            );
        }

        $targetAmount = (int) round((float) $targetPrice->amount);
        $currentAmount = (int) ($latestOneTimeOrder->amount ?? 0);

        if ($targetAmount < $currentAmount) {
            return CheckoutEligibility::deny(
                'BILLING_ONE_TIME_DOWNGRADE_UNSUPPORTED',
                __('One-time downgrades are not available in self-serve checkout. Please contact support.')
            );
        }

        if ($targetAmount <= $currentAmount) {
            return CheckoutEligibility::deny(
                'BILLING_ONE_TIME_UPGRADE_ONLY',
                __('You can only upgrade to a higher one-time plan.')
            );
        }

        return CheckoutEligibility::allow(isUpgrade: true);
    }

    /**
     * Create a Paddle transaction and return the transaction ID.
     *
     * @return string|RedirectResponse Transaction ID on success, RedirectResponse on failure
     */
    public function createPaddleTransaction(
        ?User $user,
        string $planKey,
        string $priceKey,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        ?Discount $discount = null,
        array $extraCustomData = [],
        ?string $customerEmail = null,
    ): TransactionDTO|RedirectResponse {
        try {
            /** @var PaddleAdapter $adapter */
            $adapter = $this->providerManager->runtime(BillingProvider::Paddle->value);

            $dto = $adapter->createTransactionId(
                $user,
                $planKey,
                $priceKey,
                $quantity,
                $successUrl,
                $cancelUrl,
                $discount,
                $extraCustomData,
                $customerEmail,
            );

            return $dto;
        } catch (Throwable $exception) {
            report($exception);

            return $this->handleCheckoutError($exception);
        }
    }

    /**
     * Build session data for Paddle inline checkout.
     *
     * @return array<string, mixed>
     */
    public function buildPaddleSessionData(
        string $provider,
        string $planKey,
        string $priceKey,
        Plan $plan,
        Price $price,
        ?string $priceCurrency,
        int $quantity,
        string $providerPriceId,
        string $transactionId,
        ?User $user = null,
        ?Discount $discount = null,
    ): array {
        $data = [
            'mode' => 'inline',
            'provider' => $provider,
            'plan_key' => $planKey,
            'plan_name' => $plan->name ?: $planKey,
            'price_key' => $priceKey,
            'price_label' => $price->label ?: ucfirst($priceKey),
            'amount' => $price->amount,
            'amount_is_minor' => $price->amountIsMinor,
            'currency' => $priceCurrency,
            'interval' => $price->interval ?: null,
            'interval_count' => $price->intervalCount,
            'quantity' => $quantity,
            'price_id' => $providerPriceId,
            'transaction_id' => $transactionId,
        ];

        // Add user info for authenticated checkouts
        if ($user) {
            $data['email'] = $user->email;
            $data['name'] = $user->name;
        }

        // Add discount info
        if ($discount) {
            $data['discount_id'] = $discount->provider_id;
            $data['discount_code'] = $discount->code;
        }

        // Add custom data for webhook handling
        if ($user) {
            $data['custom_data'] = array_filter([
                'user_id' => $user->id,
                'plan_key' => $planKey,
                'price_key' => $priceKey,
                'discount_id' => $discount?->id,
                'discount_code' => $discount?->code,
            ]);
        }

        return $data;
    }

    /**
     * Create a checkout session for tracking the checkout flow.
     */
    public function createCheckoutSession(
        User $user,
        string $provider,
        string $planKey,
        string $priceKey,
        int $quantity,
        ?string $providerSessionId = null
    ): CheckoutSession {
        return CheckoutSession::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_session_id' => $providerSessionId,
            'plan_key' => $planKey,
            'price_key' => $priceKey,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Build checkout URLs with session UUID for secure auth restoration.
     *
     * @return array{success: string, cancel: string}
     */
    public function buildCheckoutUrls(string $provider, CheckoutSession $session): array
    {
        $successUrl = config('saas.billing.success_url');
        $cancelUrl = config('saas.billing.cancel_url');

        if (! $successUrl) {
            $successUrl = route('billing.processing', [
                'session' => $session->uuid,
                'sig' => $this->buildCheckoutSessionSignature($session),
            ], true);

            if ($provider === BillingProvider::Stripe->value) {
                $successUrl .= '&stripe_session={CHECKOUT_SESSION_ID}';
            }
        }

        if (! $cancelUrl) {
            $cancelUrl = route('pricing', [], true);
        }

        return ['success' => $successUrl, 'cancel' => $cancelUrl];
    }

    public function verifyUserAfterPayment(int $userId): void
    {
        $user = User::find($userId);

        if (! $user || $user->email_verified_at) {
            return;
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'onboarding_completed_at' => $user->onboarding_completed_at ?? now(),
        ])->save();

        // Send a password reset link so the user can set their password.
        Password::sendResetLink(['email' => $user->email]);
    }

    public function isValidCheckoutSessionSignature(CheckoutSession $session, ?string $signature): bool
    {
        if (! $signature) {
            return false;
        }

        $expected = $this->buildCheckoutSessionSignature($session);

        return hash_equals($expected, $signature);
    }

    private function buildCheckoutSessionSignature(CheckoutSession $session): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7)) ?: '';
        }

        $payload = implode('|', [
            $session->uuid,
            $session->user_id,
            $session->expires_at?->timestamp ?? 0,
        ]);

        return hash_hmac('sha256', $payload, $key);
    }

    /**
     * Calculate quantity for checkout.
     */
    public function calculateQuantity(\App\Domain\Billing\Data\Plan $plan): int
    {
        return 1;
    }

    /**
     * Handle checkout errors with user-friendly messages.
     */
    public function handleCheckoutError(Throwable $exception): RedirectResponse
    {
        $message = $this->formatBillingError($exception->getMessage());

        return back()
            ->withErrors(['billing' => $message])
            ->withInput();
    }

    /**
     * Format billing error messages for user display.
     */
    public function formatBillingError(string $message): string
    {
        // Paddle-specific error translations
        $errorMappings = [
            'transaction_checkout_not_enabled' => 'Payment provider (Paddle) is not fully enabled. Please check your Paddle Dashboard > Verify Account.',
            'forbidden' => 'Paddle Authentication Failed. You might be using a Live API Key in Sandbox mode (or vice versa), or the Key lacks permissions. Please check your .env credentials.',
            'transaction_default_checkout_url_not_set' => 'Paddle Checkout Error: You must set a "Default Payment Link" in your Paddle Dashboard. Go to Checkout > Checkout Settings > Default Payment Link.',
        ];

        foreach ($errorMappings as $pattern => $userMessage) {
            if (str_contains($message, $pattern)) {
                $message = $userMessage;
                break;
            }
        }

        // Only show detailed errors in local environment
        if (! app()->environment('local')) {
            $message = 'Checkout failed. Please try again or contact support.';
        }

        return $message;
    }

    /**
     * Generate a name from email address.
     */
    private function nameFromEmail(string $email): string
    {
        $local = trim(strstr($email, '@', true) ?: $email);
        $name = str_replace(['.', '_', '-'], ' ', $local);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        return $name !== '' ? ucwords($name) : 'Customer';
    }

    private function isOneTimeOrder(Order $order): bool
    {
        $metadata = $order->metadata ?? [];
        $subscriptionId = data_get($metadata, 'subscription_id')
            ?? data_get($metadata, 'provider_subscription_id');

        return empty($subscriptionId);
    }
}
