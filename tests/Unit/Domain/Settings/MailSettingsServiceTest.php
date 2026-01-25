<?php

namespace Tests\Unit\Domain\Settings;

use App\Domain\Settings\Services\AppSettingsService;
use App\Domain\Settings\Services\MailSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_mail_provider_config(): void
    {
        $settings = app(AppSettingsService::class);
        $settings->set('mail.provider', 'postmark', 'mail');
        $settings->set('mail.from.address', 'noreply@example.com', 'mail');
        $settings->set('mail.from.name', 'Example', 'mail');
        $settings->set('mail.postmark.token', 'token-123', 'mail', null, true);

        app(MailSettingsService::class)->applyConfig();

        $this->assertSame('postmark', config('mail.default'));
        $this->assertSame('noreply@example.com', config('mail.from.address'));
        $this->assertSame('Example', config('mail.from.name'));
        $this->assertSame('token-123', config('services.postmark.token'));
    }
}
