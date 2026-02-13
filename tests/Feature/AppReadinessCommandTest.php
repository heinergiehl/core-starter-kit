<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppReadinessCommandTest extends TestCase
{
    public function test_app_readiness_command_fails_when_debug_is_enabled_in_production(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'app.url' => 'https://app.example.com',
            'app.key' => 'base64:test-key-for-readiness-check',
            'queue.default' => 'database',
            'cache.default' => 'database',
            'session.driver' => 'database',
            'mail.default' => 'smtp',
            'filesystems.default' => 'public',
            'saas.security.allow_unsafe_eval' => false,
        ]);

        $this->artisan('app:check-readiness')
            ->expectsOutputToContain('APP_DEBUG in production')
            ->assertExitCode(1);
    }

    public function test_app_readiness_command_strict_mode_fails_on_warnings(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://app.example.com',
            'app.key' => 'base64:test-key-for-readiness-check',
            'queue.default' => 'database',
            'cache.default' => 'database',
            'session.driver' => 'database',
            'session.http_only' => true,
            'session.secure' => null,
            'mail.default' => 'smtp',
            'filesystems.default' => 'public',
            'saas.security.allow_unsafe_eval' => true,
        ]);

        $this->artisan('app:check-readiness --strict')
            ->expectsOutputToContain('CSP unsafe-eval')
            ->assertExitCode(1);
    }

    public function test_app_readiness_command_passes_with_hardened_production_configuration(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://app.example.com',
            'app.key' => 'base64:test-key-for-readiness-check',
            'queue.default' => 'database',
            'cache.default' => 'database',
            'session.driver' => 'database',
            'session.http_only' => true,
            'session.secure' => true,
            'mail.default' => 'smtp',
            'filesystems.default' => 'public',
            'saas.security.allow_unsafe_eval' => false,
        ]);

        $this->artisan('app:check-readiness')
            ->expectsOutputToContain('App readiness summary')
            ->assertExitCode(0);
    }
}
