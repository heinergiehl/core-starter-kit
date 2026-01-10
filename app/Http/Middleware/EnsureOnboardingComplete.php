<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures users have completed onboarding before accessing the app.
 */
class EnsureOnboardingComplete
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Skip if onboarding is completed
        if ($user->onboarding_completed_at) {
            return $next($request);
        }

        // Skip for certain routes
        $excludedRoutes = [
            'onboarding.*',
            'logout',
            'locale.update',
        ];

        foreach ($excludedRoutes as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        // Redirect to onboarding
        return redirect()->route('onboarding.show');
    }
}
