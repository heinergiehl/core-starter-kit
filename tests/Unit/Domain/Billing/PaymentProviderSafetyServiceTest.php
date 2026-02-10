<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Models\PaymentProvider;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\PaymentProviderSafetyService;
use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentProviderSafetyServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentProviderSafetyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PaymentProviderSafetyService::class);
    }

    public function test_missing_supported_provider_options_excludes_already_configured_providers(): void
    {
        PaymentProvider::create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'is_active' => true,
        ]);

        $options = $this->service->missingSupportedProviderOptions();

        $this->assertArrayNotHasKey('stripe', $options);
        $this->assertArrayHasKey('paddle', $options);
    }

    public function test_add_supported_provider_creates_provider_with_default_configuration(): void
    {
        config([
            'services.paddle.vendor_id' => 'vendor_test',
            'services.paddle.api_key' => 'api_test',
            'services.paddle.environment' => 'sandbox',
            'services.paddle.webhook_secret' => 'whsec_test',
        ]);

        $provider = $this->service->addSupportedProvider('paddle');

        $this->assertSame('paddle', $provider->slug);
        $this->assertSame('Paddle', $provider->name);
        $this->assertFalse($provider->is_active);
        $this->assertSame('vendor_test', data_get($provider->configuration, 'vendor_id'));
        $this->assertSame('api_test', data_get($provider->configuration, 'api_key'));
        $this->assertSame('sandbox', data_get($provider->configuration, 'environment'));
        $this->assertSame('whsec_test', data_get($provider->configuration, 'webhook_secret'));
    }

    public function test_add_supported_provider_throws_for_unknown_provider(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->addSupportedProvider('paypal');
    }

    public function test_disable_guard_blocks_provider_with_active_or_trialing_subscriptions(): void
    {
        config(['saas.billing.default_provider' => 'paddle']);

        $stripe = PaymentProvider::create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'is_active' => true,
        ]);

        PaymentProvider::create([
            'name' => 'Paddle',
            'slug' => 'paddle',
            'is_active' => true,
        ]);

        Subscription::factory()->create([
            'provider' => 'stripe',
            'status' => SubscriptionStatus::Active,
        ]);

        $reason = $this->service->disableGuardReason($stripe);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('active or trialing', $reason);
        $this->assertFalse($this->service->canDisable($stripe));
    }

    public function test_disable_guard_blocks_default_provider(): void
    {
        config(['saas.billing.default_provider' => 'stripe']);

        $stripe = PaymentProvider::create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'is_active' => true,
        ]);

        PaymentProvider::create([
            'name' => 'Paddle',
            'slug' => 'paddle',
            'is_active' => true,
        ]);

        $reason = $this->service->disableGuardReason($stripe);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('default billing provider', $reason);
    }

    public function test_disable_guard_blocks_last_active_provider(): void
    {
        config(['saas.billing.default_provider' => 'paddle']);

        $stripe = PaymentProvider::create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'is_active' => true,
        ]);

        $reason = $this->service->disableGuardReason($stripe);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('last active billing provider', $reason);
    }

    public function test_disable_guard_allows_non_default_provider_when_others_are_active_and_no_active_subscriptions(): void
    {
        config(['saas.billing.default_provider' => 'stripe']);

        PaymentProvider::create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'is_active' => true,
        ]);

        $paddle = PaymentProvider::create([
            'name' => 'Paddle',
            'slug' => 'paddle',
            'is_active' => true,
        ]);

        $this->assertNull($this->service->disableGuardReason($paddle));
        $this->assertTrue($this->service->canDisable($paddle));
    }
}
