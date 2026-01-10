<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Services\BillingPlanService;
use Tests\TestCase;

class BillingPlanServiceTest extends TestCase
{
    private BillingPlanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BillingPlanService();
    }

    public function test_providers_returns_array_of_strings(): void
    {
        config(['saas.billing.providers' => ['stripe', 'paddle', 'lemonsqueezy']]);

        $providers = $this->service->providers();

        $this->assertIsArray($providers);
        $this->assertContains('stripe', $providers);
    }

    public function test_default_provider_returns_stripe_when_not_configured(): void
    {
        config(['saas.billing.default_provider' => null]);

        $provider = $this->service->defaultProvider();

        $this->assertEquals('stripe', $provider);
    }

    public function test_default_provider_returns_configured_value(): void
    {
        config(['saas.billing.default_provider' => 'paddle']);

        $provider = $this->service->defaultProvider();

        $this->assertEquals('paddle', $provider);
    }

    public function test_plans_returns_normalized_array(): void
    {
        config(['saas.billing.plans' => [
            'starter' => [
                'name' => 'Starter',
                'type' => 'subscription',
            ],
        ]]);
        config(['saas.billing.catalog' => 'config']);

        $plans = $this->service->plans();

        $this->assertIsArray($plans);
        $this->assertNotEmpty($plans);
        $this->assertEquals('starter', $plans[0]['key'] ?? null);
    }

    public function test_plan_throws_exception_for_unknown_plan(): void
    {
        config(['saas.billing.plans' => []]);
        config(['saas.billing.catalog' => 'config']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown plan [nonexistent]');

        $this->service->plan('nonexistent');
    }

    public function test_price_throws_exception_for_unknown_price(): void
    {
        config(['saas.billing.plans' => [
            'starter' => [
                'name' => 'Starter',
                'prices' => [],
            ],
        ]]);
        config(['saas.billing.catalog' => 'config']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown price [monthly] for plan [starter]');

        $this->service->price('starter', 'monthly');
    }
}
