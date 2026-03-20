<?php

namespace Tests\Unit\Domain\Settings;

use App\Domain\Settings\Services\BrandingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrandingServiceTest extends TestCase
{
    public function test_it_treats_branding_as_unavailable_when_cache_lookup_throws_and_schema_is_offline(): void
    {
        Cache::shouldReceive('remember')
            ->times(3)
            ->andThrow(new \RuntimeException('Cache store unavailable'));

        Schema::shouldReceive('hasTable')
            ->times(3)
            ->with('brand_settings')
            ->andThrow(new \RuntimeException('Database offline'));

        $service = app(BrandingService::class);

        $this->assertSame(config('saas.branding.app_name', config('app.name')), $service->appName());
        $this->assertSame(config('saas.branding.logo_path'), $service->logoPath());
        $this->assertSame(config('saas.branding.favicon_path'), $service->faviconPath());
    }
}
