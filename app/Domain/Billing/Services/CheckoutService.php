<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Adapters\PaddleAdapter;
use App\Domain\Billing\Models\CheckoutIntent;
use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Organization\Models\Team;
use App\Domain\Organization\Services\TeamProvisioner;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orchestrates the checkout flow for all billing providers.
 *
 * Responsibilities:
 * - User and team provisioning for guest checkouts
 * - Paddle transaction creation with error handling
 * - Session data management for inline checkouts
 */
class CheckoutService
{
    public function __construct(
        private readonly BillingPlanService $plans,
        private readonly BillingProviderManager $providers,
        private readonly EntitlementService $entitlements,
        private readonly DiscountService $discounts,
        private readonly TeamProvisioner $teamProvisioner,
        private readonly PaddleAdapter $paddleAdapter,
    ) {}

    /**
     * Resolve or create a user for checkout.
     *
     * @return array{user: User, team: Team|null, created: bool}|RedirectResponse
     */
    public function resolveOrCreateUser(Request $request, ?string $email, ?string $name): array|RedirectResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam;

        if ($user) {
            return ['user' => $user, 'team' => $team, 'created' => false];
        }

        if (!$email) {
            // Don't use back() - it goes to pricing page
            return redirect()->route('checkout.start', $request->only(['provider', 'plan', 'price']))
                ->withErrors(['email' => __('Email is required.')])
                ->withInput();
        }

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($email, $name) {
                $user = User::create([
                    'name' => !empty($name) ? $name : $this->nameFromEmail($email),
                    'email' => $email,
                    'password' => Str::random(32),
                    // Mark email as verified for checkout users
                    // They'll prove ownership by completing payment
                    'email_verified_at' => now(),
                    // Mark onboarding as complete for checkout users
                    // They should go straight to dashboard after payment
                    'onboarding_completed_at' => now(),
                ]);

                $team = $this->teamProvisioner->createDefaultTeam($user);

                // Log in the user so they're authenticated when checkout completes
                \Illuminate\Support\Facades\Auth::login($user);

                // Send password reset link so they can set their password
                // Don't fire Registered event - it triggers verification email
                Password::sendResetLink(['email' => $user->email]);

                return ['user' => $user, 'team' => $team, 'created' => true];
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // User already exists - redirect to login
            return redirect()->route('login')
                ->with('info', __('An account with this email already exists. Please log in to continue.'))
                ->with('intended', $request->fullUrl());
        } catch (\Throwable $e) {
             // Fallback for other potential race conditions effectively caught by Unique key
             if (Str::contains($e->getMessage(), 'Integrity constraint violation')) {
                return redirect()->route('login')
                    ->with('info', __('An account with this email already exists. Please log in to continue.'))
                    ->with('intended', $request->fullUrl());
             }
             throw $e;
        }
    }

    /**
     * Resolve the team for checkout, auto-selecting if only one.
     *
     * @return Team|RedirectResponse
     */
    public function resolveTeam(User $user, ?Team $team): Team|RedirectResponse
    {
        if ($team) {
            return $team;
        }

        $teamIds = $user->teams()->pluck('teams.id');

        if ($teamIds->count() === 1) {
            $user->update(['current_team_id' => $teamIds->first()]);
            return $user->fresh()->currentTeam;
        }

        return redirect()->route('teams.select');
    }

    /**
     * Check if team already has an active subscription.
     */
    public function hasActiveSubscription(Team $team): bool
    {
        return Subscription::query()
            ->where('team_id', $team->id)
            ->whereIn('status', ['active', 'trialing'])
            ->exists();
    }

    /**
     * Create a Paddle transaction and return the transaction ID.
     *
     * @return string|RedirectResponse Transaction ID on success, RedirectResponse on failure
     */
    public function createPaddleTransaction(
        ?Team $team,
        ?User $user,
        string $planKey,
        string $priceKey,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        ?Discount $discount = null,
        array $extraCustomData = [],
        ?string $customerEmail = null,
    ): string|RedirectResponse {
        try {
            return $this->paddleAdapter->createTransactionId(
                $team,
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
        array $plan,
        array $price,
        ?string $priceCurrency,
        int $quantity,
        string $providerPriceId,
        string $transactionId,
        ?User $user = null,
        ?Team $team = null,
        ?Discount $discount = null,
    ): array {
        $data = [
            'mode' => 'inline',
            'provider' => $provider,
            'plan_key' => $planKey,
            'plan_name' => $plan['name'] ?? $planKey,
            'price_key' => $priceKey,
            'price_label' => $price['label'] ?? ucfirst($priceKey),
            'amount' => $price['amount'] ?? null,
            'amount_is_minor' => $price['amount_is_minor'] ?? true,
            'currency' => $priceCurrency,
            'interval' => $price['interval'] ?? null,
            'interval_count' => $price['interval_count'] ?? 1,
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
        if ($team && $user) {
            $data['custom_data'] = array_filter([
                'team_id' => $team->id,
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
     * Create a CheckoutIntent for guest Paddle checkouts.
     */
    public function createCheckoutIntent(
        Request $request,
        string $provider,
        string $planKey,
        string $priceKey,
        int $quantity,
        ?string $priceCurrency,
        ?int $amount,
        ?Discount $discount,
    ): CheckoutIntent {
        return CheckoutIntent::create([
            'provider' => $provider,
            'plan_key' => $planKey,
            'price_key' => $priceKey,
            'quantity' => $quantity,
            'currency' => $priceCurrency,
            'amount' => $amount,
            'amount_is_minor' => true,
            'status' => 'pending',
            'discount_id' => $discount?->id,
            'discount_code' => $discount?->code,
            'metadata' => [
                'host' => $request->getHost(),
                'locale' => app()->getLocale(),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'referrer' => $request->headers->get('referer'),
            ],
        ]);
    }

    /**
     * Create a checkout session for tracking the checkout flow.
     */
    public function createCheckoutSession(
        User $user,
        Team $team,
        string $provider,
        string $planKey,
        string $priceKey,
        int $quantity,
        ?string $providerSessionId = null
    ): CheckoutSession {
        return CheckoutSession::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
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

        if (!$successUrl) {
            $successUrl = route('billing.processing', [
                'session' => $session->uuid,
            ], true);

            // Stripe needs session_id placeholder
            if ($provider === 'stripe') {
                $successUrl .= '&stripe_session={CHECKOUT_SESSION_ID}';
            }
        }

        if (!$cancelUrl) {
            $cancelUrl = route('pricing', [], true);
        }

        return ['success' => $successUrl, 'cancel' => $cancelUrl];
    }

    /**
     * Generate a signed auth token for session recovery.
     */
    public function generateAuthToken(User $user): string
    {
        $data = [
            'user_id' => $user->id,
            'expires' => now()->addHours(1)->timestamp,
        ];
        
        $payload = base64_encode(json_encode($data));
        $signature = hash_hmac('sha256', $payload, config('app.key'));
        
        return $payload . '.' . $signature;
    }

    /**
     * Verify and decode an auth token.
     * 
     * @return User|null
     */
    public function verifyAuthToken(?string $token): ?User
    {
        if (!$token || !str_contains($token, '.')) {
            return null;
        }
        
        [$payload, $signature] = explode('.', $token, 2);
        $expectedSignature = hash_hmac('sha256', $payload, config('app.key'));
        
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }
        
        $data = json_decode(base64_decode($payload), true);
        
        if (!$data || ($data['expires'] ?? 0) < now()->timestamp) {
            return null;
        }
        
        return User::find($data['user_id'] ?? 0);
    }

    /**
     * Calculate quantity for checkout, considering seat-based plans.
     */
    public function calculateQuantity(array $plan, Team $team): int
    {
        if (!empty($plan['seat_based'])) {
            return max(1, $this->entitlements->seatsInUse($team));
        }

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
            'transaction_checkout_not_enabled' =>
                'Payment provider (Paddle) is not fully enabled. Please check your Paddle Dashboard > Verify Account.',
            'forbidden' =>
                'Paddle Authentication Failed. You might be using a Live API Key in Sandbox mode (or vice versa), or the Key lacks permissions. Please check your .env credentials.',
            'transaction_default_checkout_url_not_set' =>
                'Paddle Checkout Error: You must set a "Default Payment Link" in your Paddle Dashboard. Go to Checkout > Checkout Settings > Default Payment Link.',
        ];

        foreach ($errorMappings as $pattern => $userMessage) {
            if (str_contains($message, $pattern)) {
                $message = $userMessage;
                break;
            }
        }

        // Only show detailed errors in local environment
        if (!app()->environment('local')) {
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
}
