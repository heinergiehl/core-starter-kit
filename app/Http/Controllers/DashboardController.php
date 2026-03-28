<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Contracts\BillingOwnerResolver;
use App\Domain\Billing\Services\BillingAccessService;
use App\Domain\Billing\Services\EntitlementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Dashboard controller.
 *
 * Displays the main dashboard with user context and entitlements.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
        protected BillingAccessService $billingAccessService,
        protected BillingOwnerResolver $billingOwnerResolver,
    ) {}

    /**
     * Display the dashboard.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $billingOwner = $this->billingOwnerResolver->forUser($user);

        return view('dashboard', [
            'user' => $user,
            'subscription' => $billingOwner ? $this->billingAccessService->activeSubscriptionForOwner($billingOwner) : null,
            'entitlements' => $billingOwner ? $this->entitlementService->forOwner($billingOwner) : null,
        ]);
    }
}
