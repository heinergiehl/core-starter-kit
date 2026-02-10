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
            return redirect()->route('billing.index')
                ->with('info', __('You already have an active subscription. Use billing to change your plan.'));
        }

        return $next($request);
    }
}
