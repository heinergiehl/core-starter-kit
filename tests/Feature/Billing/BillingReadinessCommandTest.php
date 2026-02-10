<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\PaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
