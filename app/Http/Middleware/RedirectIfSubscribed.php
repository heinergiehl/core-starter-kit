<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Services\CheckoutService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfSubscribed
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $this->checkoutService->hasActiveSubscription($user)) {
            $planType = $request->input('type');
            $planKey = $request->input('plan');

            // Exception: Allow One-Time Purchases (if applicable) even if subscribed?
            // For now, based on user request, we block checkout completely if subscribed.
            // But usually one-time addons are allowed.
            // Let's stick to the strict "No checkout if subscribed" for now as requested.

            return redirect()->route('billing.index')
                ->with('info', __('You already have an active subscription. Manage it here.'));
        }

        return $next($request);
    }
}
