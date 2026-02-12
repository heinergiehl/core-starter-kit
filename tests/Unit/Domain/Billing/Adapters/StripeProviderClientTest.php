<?php

namespace Tests\Unit\Domain\Billing\Adapters;

use App\Domain\Billing\Adapters\Stripe\StripeProviderClient;
use App\Domain\Billing\Exceptions\BillingException;
use Tests\TestCase;

class StripeProviderClientTest extends TestCase
{
    public function test_constructor_requires_secret_key(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('stripe secret is not configured.');

        new StripeProviderClient([]);
    }
}
