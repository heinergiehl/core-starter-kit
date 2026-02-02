<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Services\BillingPlanService;
use Tests\TestCase;

class BillingPlanServiceTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private BillingPlanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BillingPlanService;

        \App\Domain\Billing\Models\PaymentProvider::create(['name' => 'Stripe', 'slug' => 'stripe', 'is_active' => true]);
        \App\Domain\Billing\Models\PaymentProvider::create(['name' => 'Paddle', 'slug' => 'paddle', 'is_active' => true]);
    }

    public function test_providers_returns_array_of_strings(): void
    {
        config(['saas.billing.providers' => ['stripe', 'paddle']]);

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
        $product = \App\Domain\Billing\Models\Product::factory()->create([
            'key' => 'starter',
            'name' => 'Starter',
            'is_active' => true,
        ]);
        
        $price = \App\Domain\Billing\Models\Price::factory()->create([
            'product_id' => $product->id,
            'key' => 'monthly',
            'amount' => 1000,
            'currency' => 'USD',
            'interval' => 'month',
            'is_active' => true,
        ]);

        config(['saas.billing.pricing.shown_plans' => ['starter']]);

        $plans = $this->service->plans();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $plans);
        $this->assertNotEmpty($plans);
        $this->assertEquals('starter', $plans->first()->key);
    }

    public function test_plan_throws_exception_for_unknown_plan(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown plan [nonexistent]');

        $this->service->plan('nonexistent');
    }

    public function test_price_throws_exception_for_unknown_price(): void
    {
        $product = \App\Domain\Billing\Models\Product::factory()->create([
            'key' => 'starter',
            'name' => 'Starter',
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown price [monthly] for plan [starter]');

        $this->service->price('starter', 'monthly');
    }
}
