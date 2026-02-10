<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Adapters\Paddle\PaddleProviderClient;
use App\Domain\Billing\Adapters\PaddleAdapter;
use App\Domain\Billing\Adapters\Stripe\StripeProviderClient;
use App\Domain\Billing\Adapters\StripeAdapter;
use App\Domain\Billing\Contracts\BillingCatalogProvider;
use App\Domain\Billing\Contracts\BillingRuntimeProvider;
use App\Domain\Billing\Models\PaymentProvider;
use App\Enums\BillingProvider;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BillingProviderManager
{
    public function __construct(
        protected BillingPlanService $planService
    ) {}

    public function runtime(string $slug): BillingRuntimeProvider
    {
        $config = $this->getProviderConfig($slug);

        return match (strtolower($slug)) {
            BillingProvider::Stripe->value => new StripeAdapter($config, $this->planService),
            BillingProvider::Paddle->value => new PaddleAdapter($config, $this->planService),
            default => throw new RuntimeException("Unsupported billing runtime provider [{$slug}]."),
        };
    }

    public function adapter(string $slug): BillingRuntimeProvider
    {
        return $this->runtime($slug);
    }

    public function catalog(string $slug): BillingCatalogProvider
    {
        $config = $this->getProviderConfig($slug);

        return match (strtolower($slug)) {
            BillingProvider::Stripe->value => new StripeProviderClient($config),
            BillingProvider::Paddle->value => new PaddleProviderClient($config),
            default => throw new RuntimeException("Unsupported billing catalog provider [{$slug}]."),
        };
    }

    private function getProviderConfig(string $slug): array
    {
        $slug = strtolower($slug);

        if (! Schema::hasTable('payment_providers')) {
            return $this->withFallbackDefaults($slug, []);
        }

        $provider = PaymentProvider::query()
            ->where('slug', $slug)
            ->first();

        if (! $provider || ! $provider->is_active) {
            throw new RuntimeException("Billing provider [{$slug}] is not enabled or configured.");
        }

        $config = $provider->configuration ?? [];
        $connection = $provider->connection_settings ?? [];
        $merged = array_merge($config, $connection);

        return $this->withFallbackDefaults($slug, $merged);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function withFallbackDefaults(string $slug, array $config): array
    {
        if ($slug === BillingProvider::Stripe->value) {
            $config['secret_key'] ??= config('services.stripe.secret');
            $config['publishable_key'] ??= config('services.stripe.key');
            $config['public_key'] ??= $config['publishable_key'];
            $config['webhook_secret'] ??= config('services.stripe.webhook_secret');
            $config['timeout'] ??= (int) config('saas.billing.provider_api.timeouts.stripe', 15);
            $config['connect_timeout'] ??= (int) config('saas.billing.provider_api.connect_timeouts.stripe', 5);
            $config['retries'] ??= (int) config('saas.billing.provider_api.retries.stripe', 2);
            $config['retry_delay_ms'] ??= (int) config('saas.billing.provider_api.retry_delay_ms', 500);

            return $config;
        }

        if ($slug === BillingProvider::Paddle->value) {
            $config['api_key'] ??= config('services.paddle.api_key');
            $config['client_side_token'] ??= config('services.paddle.client_side_token');
            $config['environment'] ??= config('services.paddle.environment', 'production');
            $config['webhook_secret'] ??= config('services.paddle.webhook_secret');
            $config['timeout'] ??= (int) config('saas.billing.provider_api.timeouts.paddle', 15);
            $config['connect_timeout'] ??= (int) config('saas.billing.provider_api.connect_timeouts.paddle', 5);
            $config['retries'] ??= (int) config('saas.billing.provider_api.retries.paddle', 2);
            $config['retry_delay_ms'] ??= (int) config('saas.billing.provider_api.retry_delay_ms', 500);
            $config['webhook_tolerance_seconds'] ??= (int) env('PADDLE_WEBHOOK_TOLERANCE_SECONDS', 300);

            return $config;
        }

        throw new RuntimeException("Unsupported billing provider [{$slug}].");
    }
}
