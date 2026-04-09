<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\CheckoutService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfSubscribed
{
    public function __construct(
        protected CheckoutService $checkoutService,
        protected BillingPlanService $planService,
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
            // Pay-what-you-want prices are always accessible — subscribed users
            // can still make a one-time contribution without being bounced away.
            if ($this->requestTargetsPwywPrice($request)) {
                return $next($request);
            }

            return redirect()->route('billing.index')
                ->with('info', __('You already have an active plan. Use billing to manage your account.'));
        }

        return $next($request);
    }

    private function requestTargetsPwywPrice(Request $request): bool
    {
        $planKey = (string) ($request->query('plan') ?? $request->input('plan', ''));
        $priceKey = (string) ($request->query('price') ?? $request->input('price', ''));

        if ($planKey === '' || $priceKey === '') {
            return false;
        }

        try {
            return $this->planService->price($planKey, $priceKey)->supportsCustomAmount();
        } catch (\Throwable) {
            return false;
        }
    }
}
