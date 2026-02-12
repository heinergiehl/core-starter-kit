<?php

namespace Tests\Unit\Domain\Billing\Adapters;

use App\Domain\Billing\Adapters\Paddle\PaddleProviderClient;
use App\Domain\Billing\Exceptions\BillingException;
use Tests\TestCase;

class PaddleProviderClientTest extends TestCase
{
    public function test_constructor_requires_api_key(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('paddle api_key is not configured.');

        new PaddleProviderClient([]);
    }
}
