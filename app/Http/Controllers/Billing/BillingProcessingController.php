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
        $sessionUuid = trim((string) $request->query('session', ''));

        if ($sessionUuid === '') {
            $sessionUuid = trim((string) $request->session()->pull('checkout_session_uuid', ''));
        }

        return view('billing.processing', [
            'session_uuid' => $sessionUuid !== '' ? $sessionUuid : null,
        ]);
    }
}
