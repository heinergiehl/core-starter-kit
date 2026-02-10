<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\PaymentProvider;
use App\Domain\Billing\Models\Subscription;
use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
use RuntimeException;

class PaymentProviderSafetyService
{
    /**
     * @return array<string, string>
     */
    public function supportedProviderOptions(): array
    {
        $options = [];

        foreach (BillingProvider::cases() as $provider) {
            $options[$provider->value] = $provider->getLabel() ?? ucfirst($provider->value);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function missingSupportedProviderOptions(): array
    {
        $existingSlugs = PaymentProvider::query()
            ->pluck('slug')
            ->map(fn ($slug): string => strtolower((string) $slug))
            ->all();

        $existingLookup = array_fill_keys($existingSlugs, true);

        return collect($this->supportedProviderOptions())
            ->reject(fn (string $label, string $slug): bool => isset($existingLookup[$slug]))
            ->all();
    }

    public function addSupportedProvider(string $slug): PaymentProvider
    {
        $slug = strtolower(trim($slug));
        $supported = $this->supportedProviderOptions();

        if (! array_key_exists($slug, $supported)) {
            throw new RuntimeException("Billing provider [{$slug}] is not supported.");
        }

        return PaymentProvider::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $supported[$slug],
                'is_active' => false,
                'configuration' => $this->defaultConfigurationFor($slug),
            ],
        );
    }

    public function canDisable(PaymentProvider $provider): bool
    {
        return $this->disableGuardReason($provider) === null;
    }

    public function disableGuardReason(PaymentProvider $provider): ?string
    {
        if (! $provider->is_active) {
            return null;
        }

        $slug = strtolower((string) $provider->slug);

        $activeOrTrialingSubscriptions = Subscription::query()
            ->where('provider', $slug)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trialing->value,
            ])
            ->count();

        if ($activeOrTrialingSubscriptions > 0) {
            return trans_choice(
                'Cannot disable this provider because :count active or trialing subscription still uses it.|Cannot disable this provider because :count active or trialing subscriptions still use it.',
                $activeOrTrialingSubscriptions,
                ['count' => $activeOrTrialingSubscriptions],
            );
        }

        $defaultProvider = strtolower((string) config('saas.billing.default_provider', BillingProvider::Stripe->value));

        if ($slug === $defaultProvider) {
            return 'Cannot disable the default billing provider. Change BILLING_DEFAULT_PROVIDER first.';
        }

        $activeProviderCount = PaymentProvider::query()
            ->where('is_active', true)
            ->count();

        if ($activeProviderCount <= 1) {
            return 'Cannot disable the last active billing provider.';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultConfigurationFor(string $slug): array
    {
        return match ($slug) {
            BillingProvider::Stripe->value => array_filter([
                'secret_key' => config('services.stripe.secret'),
                'publishable_key' => config('services.stripe.key'),
                'webhook_secret' => config('services.stripe.webhook_secret'),
            ], fn ($value): bool => filled($value)),
            BillingProvider::Paddle->value => array_filter([
                'vendor_id' => config('services.paddle.vendor_id'),
                'api_key' => config('services.paddle.api_key'),
                'environment' => config('services.paddle.environment', 'production'),
                'webhook_secret' => config('services.paddle.webhook_secret'),
            ], fn ($value): bool => filled($value)),
            default => [],
        };
    }
}
