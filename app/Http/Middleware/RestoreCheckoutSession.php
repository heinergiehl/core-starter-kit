<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Services\CheckoutService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restore user session after checkout redirect using checkout session UUID.
 *
 * This middleware allows users to be re-authenticated after being redirected
 * from external payment providers (Paddle, Stripe, etc.) using a one-time
 * checkout session stored in the database.
 */
class RestoreCheckoutSession
{
    public function __construct(
        private readonly CheckoutService $checkoutService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionUuid = $request->query('session');
        $signature = $request->query('sig');

        if (! $sessionUuid) {
            return $next($request);
        }

        $checkoutSession = $this->checkoutService->findCheckoutSession($sessionUuid);

        if (! $checkoutSession) {
            return $next($request);
        }

        if (! $this->checkoutService->isValidCheckoutSessionSignature($checkoutSession, (string) $signature)) {
            \Log::warning('Invalid checkout session signature', [
                'uuid' => $sessionUuid,
            ]);

            return $next($request);
        }

        $user = $checkoutSession->user;
        if (! $user) {
            \Log::warning('Checkout session user was deleted', [
                'session_id' => $checkoutSession->id,
                'user_id' => $checkoutSession->user_id,
            ]);

            return $next($request);
        }

        if (Auth::check() && Auth::id() !== $user->id) {
            \Log::warning('Checkout session owner mismatch for authenticated user', [
                'session_id' => $checkoutSession->id,
                'session_user_id' => $checkoutSession->user_id,
                'authenticated_user_id' => Auth::id(),
            ]);

            return redirect()->route('billing.processing');
        }

        if (! $this->checkoutService->resolveSuccessfulCheckoutOutcome($checkoutSession)) {
            return $next($request);
        }

        if (! $this->checkoutService->markCheckoutSessionCompleted($checkoutSession)) {
            return $next($request);
        }

        if (! Auth::check()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        $request->session()->put('checkout_session_uuid', $sessionUuid);

        return redirect()->route('billing.processing');
    }
}
