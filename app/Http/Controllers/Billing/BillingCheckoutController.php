<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Adapters\PaddleAdapter;
use App\Domain\Billing\Models\CheckoutIntent;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Organization\Services\TeamProvisioner;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class BillingCheckoutController
{
    public function store(
        Request $request,
        BillingPlanService $plans,
        BillingProviderManager $providers,
        EntitlementService $entitlements,
        DiscountService $discounts,
        TeamProvisioner $teams
    ): RedirectResponse {
        $data = $request->validate([
            'plan' => ['required', 'string'],
            'price' => ['required', 'string'],
            'provider' => ['required', 'string'],
            'coupon' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $team = $user?->currentTeam;
        $provider = strtolower($data['provider'] ?? $plans->defaultProvider());

        $plan = $plans->plan($data['plan']);
        $price = $plans->price($data['plan'], $data['price']);
        $priceCurrency = $price['currency'] ?? ($price['currencies'][$provider] ?? null);

        $successUrl = config('saas.billing.success_url');
        $cancelUrl = config('saas.billing.cancel_url');

        if (!$successUrl) {
            $successUrl = route('billing.processing', ['provider' => $provider], true);

            if ($provider === 'stripe') {
                $delimiter = str_contains($successUrl, '?') ? '&' : '?';
                $successUrl .= $delimiter . 'session_id={CHECKOUT_SESSION_ID}';
            }
        }

        if (!$cancelUrl) {
            $cancelUrl = route('pricing', [], true);
        }

        $quantity = 1;

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

        if ($provider === 'paddle' && !$user) {
            $intent = CheckoutIntent::create([
                'provider' => $provider,
                'plan_key' => $data['plan'],
                'price_key' => $data['price'],
                'quantity' => $quantity,
                'currency' => $priceCurrency,
                'amount' => $price['amount'] ?? null,
                'amount_is_minor' => $price['amount_is_minor'] ?? true,
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

            try {
                $transactionId = app(PaddleAdapter::class)->createTransactionId(
                    null,
                    null,
                    $data['plan'],
                    $data['price'],
                    $quantity,
                    $successUrl,
                    $cancelUrl,
                    $discount,
                    [
                        'checkout_intent_id' => $intent->id,
                    ]
                );
            } catch (\Throwable $exception) {
                report($exception);

                $message = $exception->getMessage();

                if (str_contains($message, 'transaction_checkout_not_enabled')) {
                    $message = 'Payment provider (Paddle) is not fully enabled. Please check your Paddle Dashboard > Verify Account.';
                }

                if (str_contains($message, 'forbidden')) {
                    $message = 'Paddle Authentication Failed. You might be using a Live API Key in Sandbox mode (or vice versa), or the Key lacks permissions. Please check your .env credentials.';
                }

                if (str_contains($message, 'transaction_default_checkout_url_not_set')) {
                    $message = 'Paddle Checkout Error: You must set a "Default Payment Link" in your Paddle Dashboard. Go to Checkout > Checkout Settings > Default Payment Link.';
                }

                $message = app()->environment('local')
                    ? $message
                    : 'Checkout failed. Please try again or contact support.';

                return back()
                    ->withErrors(['billing' => $message])
                    ->withInput();
            }

            $intent->update([
                'provider_transaction_id' => $transactionId,
            ]);

            $request->session()->put('paddle_checkout', [
                'mode' => 'inline',
                'provider' => $provider,
                'plan_key' => $data['plan'],
                'plan_name' => $plan['name'] ?? $data['plan'],
                'price_key' => $data['price'],
                'price_label' => $price['label'] ?? ucfirst($data['price']),
                'amount' => $price['amount'] ?? null,
                'amount_is_minor' => $price['amount_is_minor'] ?? true,
                'currency' => $priceCurrency,
                'interval' => $price['interval'] ?? null,
                'interval_count' => $price['interval_count'] ?? 1,
                'quantity' => $quantity,
                'price_id' => $providerPriceId,
                'transaction_id' => $transactionId,
            ]);

            return redirect()->route('paddle.checkout', ['_ptxn' => $transactionId]);
        }

        if (!$user) {
            $request->validate([
                'email' => ['required', 'email'],
            ]);

            $existingUser = User::query()->where('email', $data['email'])->first();
            if ($existingUser) {
                return back()
                    ->withErrors(['billing' => __('An account with this email already exists. Please log in.')])
                    ->withInput();
            }

            $user = User::create([
                'name' => !empty($data['name']) ? $data['name'] : $this->nameFromEmail($data['email']),
                'email' => $data['email'],
                'password' => Str::random(32),
            ]);

            $team = $teams->createDefaultTeam($user);

            event(new Registered($user));
            Password::sendResetLink(['email' => $user->email]);
        }

        if (!$team) {
            $teamIds = $user->teams()->pluck('teams.id');
            if ($teamIds->count() === 1) {
                $user->update(['current_team_id' => $teamIds->first()]);
                $team = $user->currentTeam;
            } else {
                return redirect()->route('teams.select');
            }
        }

        $user->can('billing', $team) || abort(403);

        // Prevent duplicate subscription purchase
        $hasActiveSubscription = Subscription::query()
            ->where('team_id', $team->id)
            ->whereIn('status', ['active', 'trialing'])
            ->exists();

        if ($hasActiveSubscription) {
            return redirect()->route('billing.portal')
                ->with('info', __('You already have an active subscription. Manage it in the billing portal.'));
        }

        if (!empty($plan['seat_based'])) {
            $quantity = max(1, $entitlements->seatsInUse($team));
        }

        if ($provider === 'paddle') {
            try {
                $transactionId = app(PaddleAdapter::class)->createTransactionId(
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
            } catch (\Throwable $exception) {
                report($exception);

                $message = $exception->getMessage();

                if (str_contains($message, 'transaction_checkout_not_enabled')) {
                    $message = 'Payment provider (Paddle) is not fully enabled. Please check your Paddle Dashboard > Verify Account.';
                }

                if (str_contains($message, 'forbidden')) {
                    $message = 'Paddle Authentication Failed. You might be using a Live API Key in Sandbox mode (or vice versa), or the Key lacks permissions. Please check your .env credentials.';
                }

                if (str_contains($message, 'transaction_default_checkout_url_not_set')) {
                    $message = 'Paddle Checkout Error: You must set a "Default Payment Link" in your Paddle Dashboard. Go to Checkout > Checkout Settings > Default Payment Link.';
                }

                $message = app()->environment('local')
                    ? $message
                    : 'Checkout failed. Please try again or contact support.';

                return back()
                    ->withErrors(['billing' => $message])
                    ->withInput();
            }

            $request->session()->put('paddle_checkout', [
                'mode' => 'inline',
                'provider' => $provider,
                'plan_key' => $data['plan'],
                'plan_name' => $plan['name'] ?? $data['plan'],
                'price_key' => $data['price'],
                'price_label' => $price['label'] ?? ucfirst($data['price']),
                'amount' => $price['amount'] ?? null,
                'amount_is_minor' => $price['amount_is_minor'] ?? true,
                'currency' => $priceCurrency,
                'interval' => $price['interval'] ?? null,
                'interval_count' => $price['interval_count'] ?? 1,
                'quantity' => $quantity,
                'email' => $user->email,
                'name' => $user->name,
                'price_id' => $providerPriceId,
                'transaction_id' => $transactionId,
                'discount_id' => $discount?->provider_id,
                'discount_code' => $discount?->code,
                'custom_data' => [
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'plan_key' => $data['plan'],
                    'price_key' => $data['price'],
                    'discount_id' => $discount?->id,
                    'discount_code' => $discount?->code,
                ],
            ]);

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
        } catch (\Throwable $exception) {
            report($exception);

            $message = $exception->getMessage();

            // Detect Paddle onboarding issue
            if (str_contains($message, 'transaction_checkout_not_enabled')) {
                $message = 'Payment provider (Paddle) is not fully enabled. Please check your Paddle Dashboard > Verify Account.';
            }

            // Detect Environment/Key Mismatch
            if (str_contains($message, 'forbidden')) {
                $message = 'Paddle Authentication Failed. You might be using a Live API Key in Sandbox mode (or vice versa), or the Key lacks permissions. Please check your .env credentials.';
            }

            // Detect Missing Default Payment Link
            if (str_contains($message, 'transaction_default_checkout_url_not_set')) {
                $message = 'Paddle Checkout Error: You must set a "Default Payment Link" in your Paddle Dashboard. Go to Checkout > Checkout Settings > Default Payment Link.';
            }

            $message = app()->environment('local')
                ? $message
                : 'Checkout failed. Please try again or contact support.';

            return back()
                ->withErrors(['billing' => $message])
                ->withInput();
        }

        return redirect()->away($checkoutUrl);
    }

    private function nameFromEmail(string $email): string
    {
        $local = trim(strstr($email, '@', true) ?: $email);
        $name = str_replace(['.', '_', '-'], ' ', $local);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        return $name !== '' ? ucwords($name) : 'Customer';
    }
}
