<?php

namespace Tests\Feature\Settings;

use App\Domain\Settings\Models\BrandSetting;
use App\Domain\Settings\Services\BrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BrandingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_css_variable_overrides_uses_saved_brand_colors(): void
    {
        BrandSetting::query()->create([
            'color_primary' => '#4F46E5',
            'color_secondary' => '#A855F7',
            'color_accent' => '#EC4899',
        ]);

        Cache::forget('branding.global');

        $overrides = app(BrandingService::class)->themeCssVariableOverrides();

        $this->assertStringContainsString('--color-primary: 79 70 229', $overrides);
        $this->assertStringContainsString('--color-secondary: 168 85 247', $overrides);
        $this->assertStringContainsString('--color-accent: 236 72 153', $overrides);
    }

    public function test_theme_css_variable_overrides_ignores_invalid_values(): void
    {
        BrandSetting::query()->create([
            'color_primary' => 'not-a-color',
            'color_secondary' => 'rgb(12, 34, 56)',
            'color_accent' => '999 999 999',
        ]);

        Cache::forget('branding.global');

        $overrides = app(BrandingService::class)->themeCssVariableOverrides();

        $this->assertStringNotContainsString('--color-primary', $overrides);
        $this->assertStringContainsString('--color-secondary: 12 34 56', $overrides);
        $this->assertStringNotContainsString('--color-accent', $overrides);
    }

    public function test_email_colors_are_normalized_from_brand_settings(): void
    {
        BrandSetting::query()->create([
            'email_primary_color' => '#abc',
            'email_secondary_color' => 'A155F1',
        ]);

        Cache::forget('branding.global');

        $service = app(BrandingService::class);

        $this->assertSame('#AABBCC', $service->emailPrimaryColor());
        $this->assertSame('#A155F1', $service->emailSecondaryColor());
    }

    public function test_email_colors_fall_back_to_defaults_when_values_are_invalid(): void
    {
        config([
            'saas.branding.email.primary' => 'bad-primary',
            'saas.branding.email.secondary' => 'bad-secondary',
        ]);

        BrandSetting::query()->create([
            'email_primary_color' => 'invalid',
            'email_secondary_color' => 'also-invalid',
        ]);

        Cache::forget('branding.global');

        $service = app(BrandingService::class);

        $this->assertSame('#4F46E5', $service->emailPrimaryColor());
        $this->assertSame('#A855F7', $service->emailSecondaryColor());
    }
}
