<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Contracts\BillingOwnerResolver;
use App\Domain\Billing\Services\BillingAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscription
{
    public function __construct(
        protected BillingAccessService $billingAccessService,
        protected BillingOwnerResolver $billingOwnerResolver,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }
        $billingOwner = $this->billingOwnerResolver->forUser($user);

        if (! $billingOwner || ! $this->billingAccessService->hasBillingAccessForOwner($billingOwner)) {
            return redirect()
                ->route('pricing')
                ->with('warning', __('Please complete your subscription to access the app.'));
        }

        return $next($request);
    }
}
