<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Services\CheckoutService;
use App\Enums\CheckoutStatus;
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

        $checkoutSession = CheckoutSession::query()
            ->valid()
            ->where('uuid', $sessionUuid)
            ->first();

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

        // ATOMIC UPDATE: Prevents race condition if same URL is opened in multiple tabs
        // Only ONE request will successfully update the status from 'pending' to 'completed'
        $affected = CheckoutSession::query()
            ->where('id', $checkoutSession->id)
            ->where('status', CheckoutStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->update([
                'status' => CheckoutStatus::Completed->value,
                'completed_at' => now(),
            ]);

        if ($affected === 0) {
            return $next($request);
        }

        if (! Auth::check()) {
            // Log in the guest user and clear session fixation.
            Auth::login($user);
            $request->session()->regenerate();
        }

        $request->session()->put('checkout_session_uuid', $sessionUuid);

        return redirect()->route('billing.processing');
    }
}
