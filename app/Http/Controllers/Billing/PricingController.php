<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Services\BillingPlanService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingController
{
    public function __invoke(BillingPlanService $plans): View
    {
        return view('billing.pricing', [
            'plans' => $plans->plans(),
        ]);
    }
}
