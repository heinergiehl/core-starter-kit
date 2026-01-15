<?php

namespace App\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Illuminate\View\View;

class PaddleCheckoutController
{
    public function __invoke(Request $request): View
    {
        $checkout = $request->session()->get('paddle_checkout', []);
        $transactionId = (string) $request->query('_ptxn', $request->query('ptxn', $checkout['transaction_id'] ?? ''));
        $socialProviders = config('saas.auth.social_providers', ['google', 'github', 'linkedin']);
        $inlineItems = [];

        if ($transactionId === '' && !empty($checkout['price_id'])) {
            $inlineItems[] = [
                'priceId' => $checkout['price_id'],
                'quantity' => (int) ($checkout['quantity'] ?? 1),
            ];
        }

        return view('billing.paddle-checkout', [
            'transaction_id' => $transactionId !== '' ? $transactionId : null,
            'environment' => config('services.paddle.environment', 'production'),
            'vendor_id' => config('services.paddle.vendor_id'),
            'checkout' => $checkout,
            'social_providers' => $socialProviders,
            'inline_items' => $inlineItems,
        ]);
    }
}
