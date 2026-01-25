<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Services\BillingPlanService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingController
{
    public function __invoke(Request $request, BillingPlanService $plans): View
    {
        $providers = $plans->providers();
        $defaultProvider = $plans->defaultProvider();
        if (! in_array($defaultProvider, $providers, true)) {
            $defaultProvider = $providers[0] ?? 'stripe';
        }

        $requestedProvider = strtolower((string) $request->query('provider', $defaultProvider));
        $provider = in_array($requestedProvider, $providers, true) ? $requestedProvider : $defaultProvider;

        return view('billing.pricing', [
            'plans' => $plans->plansForProvider($provider),
            'providers' => $providers,
            'provider' => $provider,
        ]);
    }
}
