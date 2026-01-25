<?php

namespace Tests\Unit\Domain\Settings;

use App\Domain\Settings\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
