<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Contracts\BillingCatalogProvider;
use App\Domain\Billing\Contracts\BillingRuntimeProvider;
use App\Domain\Billing\Adapters\PaddleAdapter;
use App\Domain\Billing\Adapters\StripeAdapter;
use App\Domain\Billing\Adapters\Stripe\StripeProviderClient;
use App\Domain\Billing\Adapters\Paddle\PaddleProviderClient;
use App\Domain\Billing\Models\PaymentProvider;
use App\Domain\Billing\Services\BillingPlanService;
use App\Enums\BillingProvider;
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
        $provider = PaymentProvider::query()
            ->where('slug', $slug)
            ->first();

        if (! $provider || ! $provider->is_active) {
            throw new RuntimeException("Billing provider [{$slug}] is not enabled or configured.");
        }

        $config = $provider->configuration ?? [];
        $connection = $provider->connection_settings ?? [];
        $merged = array_merge($config, $connection);

        // Add defaults from system config if missing
        if ($slug === BillingProvider::Stripe->value) {
            $merged['secret_key'] ??= config('services.stripe.secret');
            $merged['public_key'] ??= config('services.stripe.key');
            $merged['webhook_secret'] ??= config('services.stripe.webhook.secret');
        } elseif ($slug === BillingProvider::Paddle->value) {
            $merged['api_key'] ??= config('services.paddle.api_key');
            $merged['client_side_token'] ??= config('services.paddle.client_side_token');
            $merged['environment'] ??= config('services.paddle.environment', 'production');
            $merged['webhook_secret'] ??= config('services.paddle.webhook_secret');
        }

        return $merged;
    }
}
