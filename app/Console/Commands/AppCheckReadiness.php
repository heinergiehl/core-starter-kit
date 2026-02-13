<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AppCheckReadiness extends Command
{
    protected $signature = 'app:check-readiness {--strict : Treat warnings as failures}';

    protected $description = 'Validate core runtime configuration for staging/production readiness.';

    public function handle(): int
    {
        $checks = [];
        $strict = (bool) $this->option('strict');

        $environment = strtolower((string) config('app.env', app()->environment()));
        $isProduction = $environment === 'production';

        $this->checkAppEnvironment($checks, $environment);
        $this->checkAppUrl($checks, $isProduction);
        $this->checkAppKey($checks);
        $this->checkDebugFlag($checks, $isProduction);
        $this->checkSessionSecurity($checks, $isProduction);
        $this->checkSessionDriver($checks, $isProduction);
        $this->checkCacheStore($checks, $isProduction);
        $this->checkQueueConnection($checks, $isProduction);
        $this->checkMailDriver($checks, $isProduction);
        $this->checkFilesystemDisk($checks, $isProduction);
        $this->checkCspUnsafeEval($checks, $isProduction);

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

        $summary = "App readiness summary: {$passes} pass, {$warnings} warning(s), {$failures} failure(s).";

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
                "Current APP_ENV is [{$environment}]. Run final readiness checks with staging/production values."
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

        $host = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1'], true);
        $isHttps = str_starts_with(strtolower($appUrl), 'https://');

        if ($isProduction && $isLocalHost) {
            $checks[] = $this->failCheck('APP_URL host', "APP_URL points to localhost in production: {$appUrl}");

            return;
        }

        if ($isProduction && ! $isHttps) {
            $checks[] = $this->failCheck('APP_URL uses HTTPS', "APP_URL is not HTTPS in production: {$appUrl}");

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
    private function checkSessionSecurity(array &$checks, bool $isProduction): void
    {
        $httpOnly = (bool) config('session.http_only', true);
        $secure = config('session.secure');

        if (! $httpOnly) {
            $checks[] = $this->failCheck('SESSION_HTTP_ONLY', 'SESSION_HTTP_ONLY=false weakens cookie protection.');
        } else {
            $checks[] = $this->passCheck('SESSION_HTTP_ONLY', 'enabled');
        }

        if (! $isProduction) {
            $checks[] = $this->passCheck(
                'SESSION_SECURE_COOKIE',
                is_bool($secure) ? ($secure ? 'enabled' : 'disabled') : 'auto'
            );

            return;
        }

        if ($secure !== true) {
            $checks[] = $this->warnCheck(
                'SESSION_SECURE_COOKIE',
                'Set SESSION_SECURE_COOKIE=true in production to force HTTPS-only cookies.'
            );

            return;
        }

        $checks[] = $this->passCheck('SESSION_SECURE_COOKIE', 'enabled');
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkSessionDriver(array &$checks, bool $isProduction): void
    {
        $driver = (string) config('session.driver', 'file');

        if ($isProduction && $driver === 'array') {
            $checks[] = $this->failCheck('SESSION_DRIVER', 'SESSION_DRIVER=array is not suitable for production.');

            return;
        }

        $checks[] = $this->passCheck('SESSION_DRIVER', $driver);
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkCacheStore(array &$checks, bool $isProduction): void
    {
        $store = (string) config('cache.default', 'file');

        if ($isProduction && in_array($store, ['array', 'null'], true)) {
            $checks[] = $this->failCheck('CACHE_STORE', "CACHE_STORE={$store} is not suitable for production.");

            return;
        }

        $checks[] = $this->passCheck('CACHE_STORE', $store);
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkQueueConnection(array &$checks, bool $isProduction): void
    {
        $queueConnection = (string) config('queue.default', 'sync');

        if ($isProduction && $queueConnection === 'sync') {
            $checks[] = $this->failCheck(
                'QUEUE_CONNECTION',
                'QUEUE_CONNECTION=sync in production. Use database/redis and run workers.'
            );

            return;
        }

        if ($queueConnection === 'sync') {
            $checks[] = $this->warnCheck(
                'QUEUE_CONNECTION',
                'QUEUE_CONNECTION=sync. Background jobs run inline and can block requests.'
            );

            return;
        }

        $checks[] = $this->passCheck('QUEUE_CONNECTION', $queueConnection);
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkMailDriver(array &$checks, bool $isProduction): void
    {
        $mailer = (string) config('mail.default', 'log');

        if ($isProduction && in_array($mailer, ['log', 'array'], true)) {
            $checks[] = $this->warnCheck(
                'MAIL_MAILER',
                "MAIL_MAILER={$mailer}. User-facing mail will not be sent to providers."
            );

            return;
        }

        $checks[] = $this->passCheck('MAIL_MAILER', $mailer);
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkFilesystemDisk(array &$checks, bool $isProduction): void
    {
        $disk = (string) config('filesystems.default', 'local');

        if ($isProduction && $disk === 'local') {
            $checks[] = $this->warnCheck(
                'FILESYSTEM_DISK',
                'FILESYSTEM_DISK=local. Ensure your deploy keeps storage persistent.'
            );

            return;
        }

        $checks[] = $this->passCheck('FILESYSTEM_DISK', $disk);
    }

    /**
     * @param  list<array{status:string,check:string,details:string}>  &$checks
     */
    private function checkCspUnsafeEval(array &$checks, bool $isProduction): void
    {
        $allowUnsafeEval = (bool) config('saas.security.allow_unsafe_eval', true);

        if ($isProduction && $allowUnsafeEval) {
            $checks[] = $this->warnCheck(
                'CSP unsafe-eval',
                'CSP_ALLOW_UNSAFE_EVAL=true. Keep only if required by your frontend runtime.'
            );

            return;
        }

        $checks[] = $this->passCheck('CSP unsafe-eval', $allowUnsafeEval ? 'enabled' : 'disabled');
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
