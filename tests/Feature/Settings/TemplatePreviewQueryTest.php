<?php

namespace Tests\Feature\Settings;

use App\Domain\Settings\Models\BrandSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TemplatePreviewQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_preview_query_overrides_marketing_layout_template(): void
    {
        BrandSetting::query()->create([
            'template' => 'default',
        ]);

        Cache::forget('branding.global');

        $response = $this->get(route('home', [
            'locale' => 'en',
            'template_preview' => 'prism',
        ]));

        $response->assertOk();
        $response->assertSee('data-template="prism"', false);
    }

    public function test_invalid_template_preview_query_is_ignored(): void
    {
        BrandSetting::query()->create([
            'template' => 'ocean',
        ]);

        Cache::forget('branding.global');

        $response = $this->get(route('home', [
            'locale' => 'en',
            'template_preview' => 'not-real',
        ]));

        $response->assertOk();
        $response->assertSee('data-template="ocean"', false);
    }
}
