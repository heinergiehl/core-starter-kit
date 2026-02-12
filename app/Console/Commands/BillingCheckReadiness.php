<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\PaymentProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class BillingCheckReadiness extends Command
{
    protected $signature = 'billing:check-readiness {--strict : Treat warnings as failures}';

    protected $description = 'Validate billing configuration and operational readiness for staging/production.';

    public function handle(): int
    {
        $checks = [];
        $isProduction = app()->isProduction();
        $environment = strtolower((string) app()->environment());
        $strict = (bool) $this->option('strict');

        $this->checkAppEnvironment($checks, $environment);
        $this->checkAppUrl($checks, $isProduction);
        $this->checkAppKey($checks);
        $this->checkQueue($checks);
        $this->checkDebugFlag($checks, $isProduction);
        $this->checkWebhookRoute($checks);

        $providers = $this->activeProviders();
        if ($providers === []) {
            $checks[] = $this->warnCheck(
                'Active billing providers',
                'No active providers found in database; using config-only fallback.'
            );
        }

        foreach ($providers as $provider) {
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
            $secret = trim((string) config('services.stripe.secret', ''));
            $publishable = trim((string) config('services.stripe.key', ''));
            $webhook = trim((string) config('services.stripe.webhook_secret', ''));

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
            $vendorId = trim((string) config('services.paddle.vendor_id', ''));
            $apiKey = trim((string) config('services.paddle.api_key', ''));
            $clientToken = trim((string) config('services.paddle.client_side_token', ''));
            $webhook = trim((string) config('services.paddle.webhook_secret', ''));
            $environment = strtolower(trim((string) config('services.paddle.environment', 'production')));
            $tolerance = (int) config('services.paddle.webhook_tolerance_seconds', 300);

            $checks[] = $vendorId !== ''
                ? $this->passCheck('Paddle vendor id configured', 'present')
                : $this->failCheck('Paddle vendor id configured', 'Missing PADDLE_VENDOR_ID.');
            $checks[] = $apiKey !== ''
                ? $this->passCheck('Paddle API key configured', 'present')
                : $this->failCheck('Paddle API key configured', 'Missing PADDLE_API_KEY.');
            $checks[] = $clientToken !== ''
                ? $this->passCheck('Paddle client token configured', 'present')
                : $this->warnCheck('Paddle client token configured', 'Missing PADDLE_CLIENT_SIDE_TOKEN.');
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
    private function activeProviders(): array
    {
        if (Schema::hasTable('payment_providers')) {
            $fromDb = PaymentProvider::query()
                ->where('is_active', true)
                ->pluck('slug')
                ->map(fn ($slug): string => strtolower((string) $slug))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($fromDb !== []) {
                return $fromDb;
            }
        }

        return collect(config('saas.billing.providers', []))
            ->map(fn ($provider): string => strtolower((string) $provider))
            ->filter()
            ->unique()
            ->values()
            ->all();
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
