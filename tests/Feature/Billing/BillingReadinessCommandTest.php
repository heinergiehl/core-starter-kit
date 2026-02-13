<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\PaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_command_fails_when_active_provider_is_missing_required_secrets(): void
    {
        PaymentProvider::query()->create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'is_active' => true,
            'configuration' => [],
        ]);

        config([
            'services.stripe.secret' => null,
            'services.stripe.key' => null,
            'services.stripe.webhook_secret' => null,
        ]);

        $this->artisan('billing:check-readiness')
            ->expectsOutputToContain('Stripe secret key configured')
            ->assertExitCode(1);
    }

    public function test_readiness_command_passes_with_required_stripe_configuration(): void
    {
        PaymentProvider::query()->create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'is_active' => true,
            'configuration' => [],
        ]);

        config([
            'app.url' => 'https://billing.example.com',
            'services.stripe.secret' => 'sk_live_test_value',
            'services.stripe.key' => 'pk_live_test_value',
            'services.stripe.webhook_secret' => 'whsec_test_value',
        ]);

        $this->artisan('billing:check-readiness')
            ->expectsOutputToContain('Billing readiness summary')
            ->assertExitCode(0);
    }

    public function test_readiness_command_strict_mode_fails_on_warnings(): void
    {
        PaymentProvider::query()->create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'is_active' => true,
            'configuration' => [],
        ]);

        config([
            'app.url' => 'http://localhost',
            'queue.default' => 'sync',
            'services.stripe.secret' => 'sk_live_test_value',
            'services.stripe.key' => 'pk_live_test_value',
            'services.stripe.webhook_secret' => 'whsec_test_value',
        ]);

        $this->artisan('billing:check-readiness --strict')
            ->expectsOutputToContain('warning')
            ->assertExitCode(1);
    }

    public function test_readiness_command_uses_database_provider_configuration_when_env_secrets_are_missing(): void
    {
        PaymentProvider::query()->create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'is_active' => true,
            'configuration' => [
                'secret_key' => 'sk_live_from_db',
                'publishable_key' => 'pk_live_from_db',
                'webhook_secret' => 'whsec_from_db',
            ],
        ]);

        config([
            'app.url' => 'https://billing.example.com',
            'services.stripe.secret' => null,
            'services.stripe.key' => null,
            'services.stripe.webhook_secret' => null,
        ]);

        $this->artisan('billing:check-readiness')
            ->expectsOutputToContain('Stripe secret key configured')
            ->assertExitCode(0);
    }

    public function test_readiness_command_fails_when_active_provider_slug_is_unsupported(): void
    {
        PaymentProvider::query()->create([
            'name' => 'Legacy Gateway',
            'slug' => 'legacy-gateway',
            'is_active' => true,
            'configuration' => [],
        ]);

        config([
            'app.url' => 'https://billing.example.com',
        ]);

        $this->artisan('billing:check-readiness')
            ->expectsOutputToContain('Unsupported active provider [legacy-gateway]')
            ->assertExitCode(1);
    }

    public function test_readiness_command_fails_gracefully_when_provider_registry_cannot_be_read(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('payment_providers')
            ->andThrow(new \RuntimeException('database unavailable'));

        config([
            'app.url' => 'https://billing.example.com',
            'services.stripe.secret' => 'sk_live_test_value',
            'services.stripe.key' => 'pk_live_test_value',
            'services.stripe.webhook_secret' => 'whsec_test_value',
            'services.paddle.vendor_id' => 'vendor_123',
            'services.paddle.api_key' => 'api_123',
            'services.paddle.webhook_secret' => 'whsec_paddle',
            'services.paddle.environment' => 'production',
        ]);

        $this->artisan('billing:check-readiness')
            ->expectsOutputToContain('Payment provider registry')
            ->assertExitCode(1);
    }

    public function test_readiness_command_fails_when_provider_table_has_no_active_providers(): void
    {
        config([
            'app.url' => 'https://billing.example.com',
            'saas.billing.providers' => ['stripe', 'paddle'],
            'services.stripe.secret' => 'sk_live_test_value',
            'services.stripe.key' => 'pk_live_test_value',
            'services.stripe.webhook_secret' => 'whsec_test_value',
        ]);

        $this->artisan('billing:check-readiness')
            ->expectsOutputToContain('payment_providers table exists but has no active providers')
            ->assertExitCode(1);
    }
}
