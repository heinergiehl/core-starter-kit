<?php

namespace Tests\Unit\Domain\Settings;

use App\Domain\Settings\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AppSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_and_reads_settings(): void
    {
        $service = app(AppSettingsService::class);
        $service->set('support.email', 'support@example.com', 'support');
        $service->set('features.blog', true, 'features');
        $service->set('limits.max_projects', 10, 'limits');

        $this->assertSame('support@example.com', $service->get('support.email'));
        $this->assertTrue($service->get('features.blog'));
        $this->assertSame(10, $service->get('limits.max_projects'));
    }

    public function test_it_encrypts_sensitive_values(): void
    {
        $service = app(AppSettingsService::class);
        $service->set('mail.postmark.token', 'secret-token', 'mail', null, true);

        $raw = \App\Domain\Settings\Models\AppSetting::query()
            ->where('key', 'mail.postmark.token')
            ->value('value');

        $this->assertNotNull($raw);
        $this->assertNotSame('secret-token', $raw);
        $this->assertSame('secret-token', $service->get('mail.postmark.token'));
    }

    public function test_it_applies_billing_default_provider_to_config(): void
    {
        config(['saas.billing.default_provider' => 'stripe']);

        $service = app(AppSettingsService::class);
        $service->set('billing.default_provider', 'paddle', 'billing');

        $service->applyToConfig();

        $this->assertSame('paddle', config('saas.billing.default_provider'));
    }

    public function test_it_invalidates_cached_settings_when_updating_a_key(): void
    {
        $service = app(AppSettingsService::class);

        $service->set('support.email', 'first@example.com', 'support');

        $this->assertSame('first@example.com', $service->get('support.email'));

        $service->set('support.email', 'second@example.com', 'support');

        $this->assertSame('second@example.com', $service->get('support.email'));
    }

    public function test_cache_keeps_encrypted_values_ciphertext_at_rest(): void
    {
        Cache::forget('app_settings.all');

        $service = app(AppSettingsService::class);
        $service->set('mail.postmark.token', 'secret-token', 'mail', null, true);

        $this->assertSame('secret-token', $service->get('mail.postmark.token'));

        $cached = Cache::get('app_settings.all');

        $this->assertIsArray($cached);
        $this->assertArrayHasKey('mail.postmark.token', $cached);
        $this->assertIsArray($cached['mail.postmark.token']);
        $this->assertTrue($cached['mail.postmark.token']['is_encrypted']);
        $this->assertNotSame('secret-token', $cached['mail.postmark.token']['value']);
    }
}
