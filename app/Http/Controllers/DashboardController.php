<?php

namespace App\Http\Controllers;

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
    ) {}

    /**
     * Display the dashboard.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'subscription' => $user?->activeSubscription(),
            'entitlements' => $user ? $this->entitlementService->forUser($user) : null,
        ]);
    }
}
