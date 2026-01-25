<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Services\CheckoutService;
use App\Models\User;
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
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        $sessionUuid = $request->query('session');
        $signature = $request->query('sig');

        if (! $sessionUuid) {
            return $next($request);
        }

        $checkoutSession = CheckoutSession::query()
            ->where('uuid', $sessionUuid)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (! $checkoutSession) {
            return $next($request);
        }

        if (! app(CheckoutService::class)->isValidCheckoutSessionSignature($checkoutSession, (string) $signature)) {
            \Log::warning('Invalid checkout session signature', [
                'uuid' => $sessionUuid,
            ]);

            return $next($request);
        }

        // ATOMIC UPDATE: Prevents race condition if same URL is opened in multiple tabs
        // Only ONE request will successfully update the status from 'pending' to 'completed'
        $affected = CheckoutSession::query()
            ->where('id', $checkoutSession->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

        if ($affected === 0) {
            return $next($request);
        }

        if (! $checkoutSession) {
            // This shouldn't happen, but handle gracefully
            \Log::warning('Checkout session disappeared after atomic update', [
                'uuid' => $sessionUuid,
            ]);

            return $next($request);
        }

        // Verify user still exists (edge case: deleted between checkout and redirect)
        $user = $checkoutSession->user;
        if (! $user) {
            \Log::warning('Checkout session user was deleted', [
                'session_id' => $checkoutSession->id,
                'user_id' => $checkoutSession->user_id,
            ]);

            return $next($request);
        }

        // Log in the user
        Auth::login($user);

        return $next($request);
    }
}
