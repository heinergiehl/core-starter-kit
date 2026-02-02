<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Exceptions\BillingException;
use PHPUnit\Framework\TestCase;

class BillingExceptionTest extends TestCase
{
    public function test_unknown_plan_creates_correct_exception(): void
    {
        $exception = BillingException::unknownPlan('premium');

        $this->assertInstanceOf(BillingException::class, $exception);
        $this->assertEquals('BILLING_UNKNOWN_PLAN', $exception->getErrorCode());
        $this->assertStringContainsString('premium', $exception->getMessage());
    }

    public function test_unknown_price_creates_correct_exception(): void
    {
        $exception = BillingException::unknownPrice('starter', 'quarterly');

        $this->assertEquals('BILLING_UNKNOWN_PRICE', $exception->getErrorCode());
        $this->assertStringContainsString('starter', $exception->getMessage());
        $this->assertStringContainsString('quarterly', $exception->getMessage());
    }

    public function test_provider_error_includes_previous_exception(): void
    {
        $previous = new \RuntimeException('API error');
        $exception = BillingException::providerError('stripe', 'Connection failed', $previous);

        $this->assertEquals('BILLING_PROVIDER_ERROR', $exception->getErrorCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertStringContainsString('stripe', $exception->getMessage());
    }

    public function test_missing_configuration_creates_correct_exception(): void
    {
        $exception = BillingException::missingConfiguration('Stripe', 'API key');

        $this->assertEquals('BILLING_MISSING_CONFIG', $exception->getErrorCode());
        $this->assertStringContainsString('Stripe', $exception->getMessage());
        $this->assertStringContainsString('API key', $exception->getMessage());
    }

    public function test_webhook_validation_failed_creates_correct_exception(): void
    {
        $exception = BillingException::webhookValidationFailed('Paddle', 'Invalid signature');

        $this->assertEquals('BILLING_WEBHOOK_INVALID', $exception->getErrorCode());
        $this->assertStringContainsString('Paddle', $exception->getMessage());
    }

    public function test_checkout_failed_creates_correct_exception(): void
    {
        $exception = BillingException::checkoutFailed('Paddle', 'Session expired');

        $this->assertEquals('BILLING_CHECKOUT_FAILED', $exception->getErrorCode());
        $this->assertStringContainsString('Paddle', $exception->getMessage());
    }

    public function test_seat_sync_failed_creates_correct_exception(): void
    {
        $exception = BillingException::seatSyncFailed('Stripe', 'Subscription not found');

        $this->assertEquals('BILLING_SEAT_SYNC_FAILED', $exception->getErrorCode());
        $this->assertStringContainsString('Stripe', $exception->getMessage());
    }
}
