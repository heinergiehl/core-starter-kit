<?php

namespace App\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingProcessingController
{
    public function __invoke(Request $request): View
    {
        $rawProvider = (string) $request->query('provider', '');
        $sessionId = (string) $request->query('session_id', '');
        $provider = $rawProvider !== '' ? $rawProvider : null;

        if ($rawProvider !== '' && str_contains($rawProvider, '?')) {
            [$providerValue, $queryString] = explode('?', $rawProvider, 2);
            $provider = $providerValue !== '' ? $providerValue : null;

            if (!$sessionId && $queryString) {
                parse_str($queryString, $params);
                $sessionId = (string) ($params['session_id'] ?? '');
            }
        }

        return view('billing.processing', [
            'provider' => $provider,
            'session_id' => $sessionId !== '' ? $sessionId : null,
        ]);
    }
}
