<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\PaymentProvider;
use App\Enums\BillingProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BillingCheckReadiness extends Command
{
    protected $signature = 'billing:check-readiness {--strict : Treat warnings as failures}';

    protected $description = 'Validate billing configuration and operational readiness for staging/production.';

    /**
     * @var array<string, array{is_active:bool,configuration:array<string,mixed>,connection_settings:array<string,mixed>}>
     */
    private array $providerRegistry = [];

    private bool $providerRegistryLoaded = false;

    private bool $providerTablePresent = false;

    public function handle(): int
    {
        $checks = [];
        $environment = strtolower((string) config('app.env', app()->environment()));
        $isProduction = $environment === 'production';
        $strict = (bool) $this->option('strict');

        $this->checkAppEnvironment($checks, $environment);
        $this->checkAppUrl($checks, $isProduction);
        $this->checkAppKey($checks);
        $this->checkQueue($checks);
        $this->checkDebugFlag($checks, $isProduction);
        $this->checkWebhookRoute($checks);

        $providers = $this->activeProviders($checks);
        if ($providers === [] && ! $this->providerTablePresent) {
            $checks[] = $this->warnCheck(
                'Active billing providers',
                'No active providers found in database or config.'
            );
        }

        $supportedProviders = $this->supportedProviders();

        foreach ($providers as $provider) {
            if (! in_array($provider, $supportedProviders, true)) {
                $checks[] = $this->failCheck(
                    'Supported billing provider',
                    "Unsupported active provider [{$provider}]. Supported providers: ".implode(', ', $supportedProviders).'.'
                );

                continue;
            }

            $this->checkWebhookUrl($checks, $provider, $isProduction);
            $this->checkProviderSecrets($checks, $provider, $isProduction);
        }

        $rows = array_map(
            static fn (array $check): array => [
                strtoupper($check['status']),
                $check['check'],
                $check['details'],
            ],
            $checks
        );

        $this->table(['Status', 'Check', 'Details'], $rows);

        $failures = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'fail'));
        $warnings = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'warn'));
        $passes = count($checks) - $failures - $warnings;

        $summary = "Billing readiness summary: {$passes} pass, {$warnings} warning(s), {$failures} failure(s).";

        if ($failures > 0 || ($strict && $warnings > 0)) {
            $this->error($summary);

            return self::FAILURE;
        }

        if ($warnings > 0) {
            $this->warn($summary);
        } else {
            $this->info($summary);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkAppEnvironment(array &$checks, string $environment): void
    {
        if (in_array($environment, ['local', 'testing'], true)) {
            $checks[] = $this->warnCheck(
                'APP_ENV target',
                "Current APP_ENV is [{$environment}]. Run final readiness checks with production-like env values."
            );

            return;
        }

        $checks[] = $this->passCheck('APP_ENV target', $environment);
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkAppUrl(array &$checks, bool $isProduction): void
    {
        $appUrl = trim((string) config('app.url', ''));
        if ($appUrl === '') {
            $checks[] = $this->failCheck('APP_URL configured', 'APP_URL is empty.');

            return;
        }

        $isHttps = str_starts_with(strtolower($appUrl), 'https://');
        if ($isProduction && ! $isHttps) {
            $checks[] = $this->failCheck('APP_URL uses HTTPS', "APP_URL is not HTTPS: {$appUrl}");

            return;
        }

        if (! $isHttps) {
            $checks[] = $this->warnCheck('APP_URL uses HTTPS', "APP_URL is not HTTPS: {$appUrl}");

            return;
        }

        $checks[] = $this->passCheck('APP_URL configured', $appUrl);
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkAppKey(array &$checks): void
    {
        $key = trim((string) config('app.key', ''));

        if ($key === '' || $key === 'base64:') {
            $checks[] = $this->failCheck('APP_KEY configured', 'APP_KEY is missing. Run `php artisan key:generate`.');

            return;
        }

        $checks[] = $this->passCheck('APP_KEY configured', 'present');
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkQueue(array &$checks): void
    {
        $queueConnection = (string) config('queue.default', 'sync');
        $failedDriver = (string) config('queue.failed.driver', 'null');

        if ($queueConnection === 'sync') {
            $checks[] = $this->warnCheck(
                'Queue connection for webhooks',
                'QUEUE_CONNECTION=sync. Use database/redis and run workers in staging/production.'
            );
        } else {
            $checks[] = $this->passCheck('Queue connection for webhooks', $queueConnection);
        }

        if ($queueConnection !== 'sync' && $failedDriver === 'null') {
            $checks[] = $this->warnCheck(
                'Failed job storage',
                'QUEUE_FAILED_DRIVER is null; failed webhook jobs will not be persisted.'
            );

            return;
        }

        $checks[] = $this->passCheck('Failed job storage', $failedDriver);
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkDebugFlag(array &$checks, bool $isProduction): void
    {
        $debug = (bool) config('app.debug', false);

        if ($isProduction && $debug) {
            $checks[] = $this->failCheck('APP_DEBUG in production', 'APP_DEBUG=true in production.');

            return;
        }

        $checks[] = $this->passCheck('APP_DEBUG safety', $debug ? 'enabled (non-production)' : 'disabled');
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkWebhookRoute(array &$checks): void
    {
        if (! Route::has('webhooks.handle')) {
            $checks[] = $this->failCheck('Webhook route', 'Route [webhooks.handle] is missing.');

            return;
        }

        $checks[] = $this->passCheck('Webhook route', 'Route [webhooks.handle] is registered.');
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkWebhookUrl(array &$checks, string $provider, bool $isProduction): void
    {
        $url = route('webhooks.handle', ['provider' => $provider], true);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1'], true);

        if ($isProduction && $isLocalHost) {
            $checks[] = $this->failCheck("Webhook URL ({$provider})", "Points to localhost: {$url}");

            return;
        }

        if (! str_starts_with(strtolower($url), 'https://')) {
            $checks[] = $isProduction
                ? $this->failCheck("Webhook URL ({$provider})", "Webhook URL must use HTTPS in production: {$url}")
                : $this->warnCheck("Webhook URL ({$provider})", $url);

            return;
        }

        $checks[] = $this->passCheck("Webhook URL ({$provider})", $url);
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkProviderSecrets(array &$checks, string $provider, bool $isProduction): void
    {
        if ($provider === 'stripe') {
            $providerConfig = $this->resolvedProviderConfig($checks, $provider);

            $secret = trim((string) ($providerConfig['secret_key'] ?? ''));
            $publishable = trim((string) ($providerConfig['publishable_key'] ?? $providerConfig['public_key'] ?? ''));
            $webhook = trim((string) ($providerConfig['webhook_secret'] ?? ''));

            $checks[] = $secret !== ''
                ? $this->passCheck('Stripe secret key configured', 'present')
                : $this->failCheck('Stripe secret key configured', 'Missing STRIPE_SECRET.');
            $checks[] = $publishable !== ''
                ? $this->passCheck('Stripe publishable key configured', 'present')
                : $this->warnCheck('Stripe publishable key configured', 'Missing STRIPE_KEY.');
            $checks[] = $webhook !== ''
                ? $this->passCheck('Stripe webhook secret configured', 'present')
                : $this->failCheck('Stripe webhook secret configured', 'Missing STRIPE_WEBHOOK_SECRET.');

            if ($isProduction && (str_starts_with($secret, 'sk_test_') || str_starts_with($publishable, 'pk_test_'))) {
                $checks[] = $this->warnCheck('Stripe key mode', 'Test keys detected in production configuration.');
            } else {
                $checks[] = $this->passCheck('Stripe key mode', 'Live/unknown key mode');
            }

            return;
        }

        if ($provider === 'paddle') {
            $providerConfig = $this->resolvedProviderConfig($checks, $provider);

            $vendorId = trim((string) ($providerConfig['vendor_id'] ?? ''));
            $apiKey = trim((string) ($providerConfig['api_key'] ?? ''));
            $clientToken = trim((string) ($providerConfig['client_side_token'] ?? ''));
            $webhook = trim((string) ($providerConfig['webhook_secret'] ?? ''));
            $environment = strtolower(trim((string) ($providerConfig['environment'] ?? 'production')));
            $tolerance = (int) ($providerConfig['webhook_tolerance_seconds'] ?? 300);

            $checks[] = $vendorId !== ''
                ? $this->passCheck('Paddle vendor id configured', 'present')
                : $this->failCheck('Paddle vendor id configured', 'Missing PADDLE_VENDOR_ID.');
            $checks[] = $apiKey !== ''
                ? $this->passCheck('Paddle API key configured', 'present')
                : $this->failCheck('Paddle API key configured', 'Missing PADDLE_API_KEY.');
            $checks[] = $clientToken !== ''
                ? $this->passCheck('Paddle client token configured', 'present')
                : $this->passCheck('Paddle client token configured', 'not set (optional)');
            $checks[] = $webhook !== ''
                ? $this->passCheck('Paddle webhook secret configured', 'present')
                : $this->failCheck('Paddle webhook secret configured', 'Missing PADDLE_WEBHOOK_SECRET.');

            if (! in_array($environment, ['sandbox', 'production'], true)) {
                $checks[] = $this->failCheck('Paddle environment', "Unsupported value [{$environment}].");
            } elseif ($isProduction && $environment === 'sandbox') {
                $checks[] = $this->warnCheck('Paddle environment', 'Sandbox mode is enabled in production.');
            } else {
                $checks[] = $this->passCheck('Paddle environment', $environment);
            }

            if ($tolerance < 0) {
                $checks[] = $this->failCheck('Paddle webhook tolerance', 'PADDLE_WEBHOOK_TOLERANCE_SECONDS must be >= 0.');
            } else {
                $checks[] = $this->passCheck('Paddle webhook tolerance', "{$tolerance}s");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function activeProviders(array &$checks): array
    {
        $this->loadProviderRegistry($checks);

        if ($this->providerTablePresent) {
            $fromDb = collect($this->providerRegistry)
                ->filter(fn (array $provider): bool => $provider['is_active'] === true)
                ->keys()
                ->values()
                ->all();

            if ($fromDb === []) {
                $checks[] = $this->failCheck(
                    'Active billing providers',
                    'payment_providers table exists but has no active providers. Enable at least one provider before deploy.'
                );

                return [];
            }

            return $fromDb;
        }

        return collect(config('saas.billing.providers', []))
            ->map(fn ($provider): string => strtolower(trim((string) $provider)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     * @return array<string, mixed>
     */
    private function resolvedProviderConfig(array &$checks, string $provider): array
    {
        $provider = strtolower(trim($provider));
        $this->loadProviderRegistry($checks);

        $config = [];
        if (isset($this->providerRegistry[$provider])) {
            $providerRecord = $this->providerRegistry[$provider];
            $config = array_merge($providerRecord['configuration'], $providerRecord['connection_settings']);
        }

        return $this->withFallbackDefaults($provider, $config);
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function loadProviderRegistry(array &$checks): void
    {
        if ($this->providerRegistryLoaded) {
            return;
        }

        $this->providerRegistryLoaded = true;

        try {
            if (! Schema::hasTable('payment_providers')) {
                return;
            }

            $this->providerTablePresent = true;

            $providers = PaymentProvider::query()->get();

            foreach ($providers as $provider) {
                $slug = strtolower(trim((string) $provider->slug));
                if ($slug === '') {
                    continue;
                }

                $configuration = $provider->configuration;
                $connectionSettings = $provider->connection_settings;

                $this->providerRegistry[$slug] = [
                    'is_active' => (bool) $provider->is_active,
                    'configuration' => is_array($configuration) ? $configuration : [],
                    'connection_settings' => is_array($connectionSettings) ? $connectionSettings : [],
                ];
            }
        } catch (Throwable $exception) {
            $checks[] = $this->failCheck(
                'Payment provider registry',
                'Unable to read payment providers from database: '.$exception->getMessage()
            );
        }
    }

    /**
     * @return list<string>
     */
    private function supportedProviders(): array
    {
        return collect(BillingProvider::cases())
            ->map(fn (BillingProvider $provider): string => $provider->value)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function withFallbackDefaults(string $provider, array $config): array
    {
        if ($provider === BillingProvider::Stripe->value) {
            $config['secret_key'] ??= config('services.stripe.secret');
            $config['publishable_key'] ??= config('services.stripe.key');
            $config['public_key'] ??= $config['publishable_key'];
            $config['webhook_secret'] ??= config('services.stripe.webhook_secret');

            return $config;
        }

        if ($provider === BillingProvider::Paddle->value) {
            $config['vendor_id'] ??= config('services.paddle.vendor_id');
            $config['api_key'] ??= config('services.paddle.api_key');
            $config['client_side_token'] ??= config('services.paddle.client_side_token');
            $config['environment'] ??= config('services.paddle.environment', 'production');
            $config['webhook_secret'] ??= config('services.paddle.webhook_secret');
            $config['webhook_tolerance_seconds'] ??= config('services.paddle.webhook_tolerance_seconds', 300);

            return $config;
        }

        return $config;
    }

    /**
     * @return array{status:string,check:string,details:string}
     */
    private function passCheck(string $check, string $details): array
    {
        return ['status' => 'pass', 'check' => $check, 'details' => $details];
    }

    /**
     * @return array{status:string,check:string,details:string}
     */
    private function warnCheck(string $check, string $details): array
    {
        return ['status' => 'warn', 'check' => $check, 'details' => $details];
    }

    /**
     * @return array{status:string,check:string,details:string}
     */
    private function failCheck(string $check, string $details): array
    {
        return ['status' => 'fail', 'check' => $check, 'details' => $details];
    }
}
