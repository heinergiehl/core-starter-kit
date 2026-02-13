<?php

namespace Tests\Feature\Settings;

use App\Domain\Settings\Services\AppSettingsService;
use App\Filament\Admin\Pages\ManageEmailSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageEmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_email_settings_rejects_unsupported_provider_ids(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(ManageEmailSettings::class)
            ->set('data.mail_provider', 'mailgun')
            ->set('data.from_address', 'noreply@example.com')
            ->set('data.from_name', 'Example')
            ->call('save')
            ->assertHasErrors(['data.mail_provider']);
    }

    public function test_manage_email_settings_mounts_with_default_supported_provider_when_stored_provider_is_unsupported(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        app(AppSettingsService::class)->set('mail.provider', 'mailgun', 'mail');

        $this->actingAs($admin);

        Livewire::test(ManageEmailSettings::class)
            ->assertSet('data.mail_provider', 'postmark');
    }

    public function test_manage_email_settings_requires_postmark_token_when_missing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(ManageEmailSettings::class)
            ->set('data.mail_provider', 'postmark')
            ->set('data.from_address', 'noreply@example.com')
            ->set('data.from_name', 'Example')
            ->set('data.postmark_token', '')
            ->call('save')
            ->assertHasErrors(['data.postmark_token']);
    }

    public function test_manage_email_settings_can_reuse_existing_postmark_token_when_left_blank(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $settings = app(AppSettingsService::class);
        $settings->set('mail.postmark.token', 'existing-token', 'mail', null, true);

        $this->actingAs($admin);

        Livewire::test(ManageEmailSettings::class)
            ->set('data.mail_provider', 'postmark')
            ->set('data.from_address', 'noreply@example.com')
            ->set('data.from_name', 'Example')
            ->set('data.postmark_token', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('existing-token', $settings->get('mail.postmark.token'));
        $this->assertDatabaseHas('app_settings', [
            'key' => 'mail.provider',
            'value' => 'postmark',
        ]);
    }

    public function test_manage_email_settings_can_clear_inactive_provider_secret(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $settings = app(AppSettingsService::class);
        $settings->set('mail.postmark.token', 'legacy-token', 'mail', null, true);

        $this->actingAs($admin);

        Livewire::test(ManageEmailSettings::class)
            ->set('data.mail_provider', 'ses')
            ->set('data.from_address', 'noreply@example.com')
            ->set('data.from_name', 'Example')
            ->set('data.ses_key', 'new-access-key')
            ->set('data.ses_secret', 'new-secret-key')
            ->set('data.ses_region', 'us-east-1')
            ->set('data.clear_postmark_token', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($settings->get('mail.postmark.token'));
    }

    public function test_manage_email_settings_applies_mail_config_immediately_after_save(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(ManageEmailSettings::class)
            ->set('data.mail_provider', 'postmark')
            ->set('data.from_address', 'noreply@example.com')
            ->set('data.from_name', 'Example')
            ->set('data.postmark_token', 'token-123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('postmark', config('mail.default'));
        $this->assertSame('noreply@example.com', config('mail.from.address'));
        $this->assertSame('Example', config('mail.from.name'));
        $this->assertSame('token-123', config('services.postmark.token'));
    }
}
