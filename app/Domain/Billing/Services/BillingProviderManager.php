<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Adapters\BillingProviderAdapter;
use App\Domain\Billing\Adapters\LemonSqueezyAdapter;
use App\Domain\Billing\Adapters\PaddleAdapter;
use App\Domain\Billing\Adapters\StripeAdapter;
use RuntimeException;

class BillingProviderManager
{
    public function adapter(string $provider): BillingProviderAdapter
    {
        $enabled = config('saas.billing.providers', []);

        if (!in_array(strtolower($provider), $enabled, true)) {
            throw new RuntimeException("Billing provider [{$provider}] is not enabled.");
        }

        return match (strtolower($provider)) {
            'stripe' => app(StripeAdapter::class),
            'paddle' => app(PaddleAdapter::class),
            'lemonsqueezy' => app(LemonSqueezyAdapter::class),
            default => throw new RuntimeException("Unsupported billing provider [{$provider}]."),
        };
    }
}
