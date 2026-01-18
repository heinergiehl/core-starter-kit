<?php

namespace App\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handle the billing processing page after checkout redirect.
 * 
 * Session restoration is handled by RestoreCheckoutSession middleware.
 */
class BillingProcessingController
{
    public function __invoke(Request $request): View
    {
        $sessionUuid = (string) $request->query('session', '');
        
        return view('billing.processing', [
            'session_uuid' => $sessionUuid !== '' ? $sessionUuid : null,
        ]);
    }
}



