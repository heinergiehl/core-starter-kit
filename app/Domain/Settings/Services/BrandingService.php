<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Models\BrandSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BrandingService
{
    private const CACHE_TTL_MINUTES = 5;

    public function appName(): string
    {
        $setting = $this->globalSetting();

        if ($setting?->app_name) {
            return $setting->app_name;
        }

        return config('saas.branding.app_name', config('app.name'));
    }

    public function logoPath(): ?string
    {
        return $this->normalizeAssetPath(
            $this->globalSetting()?->logo_path ?: config('saas.branding.logo_path')
        );
    }

    public function faviconPath(): ?string
    {
        $setting = $this->globalSetting();

        return $this->normalizeAssetPath(
            $setting?->favicon_path ?: $setting?->logo_path ?: config('saas.branding.favicon_path')
        );
    }

    public function assetVersion(): string
    {
        $updatedAt = $this->globalSetting()?->updated_at;

        if ($updatedAt) {
            return (string) $updatedAt->getTimestamp();
        }

        return (string) config('app.asset_version', '1');
    }

    public function templateForGuest(): string
    {
        $globalTemplate = $this->globalSetting()?->template;

        return $globalTemplate ?: config('template.active', 'default');
    }

    public function emailPrimaryColor(): string
    {
        $setting = $this->globalSetting();

        if ($setting?->email_primary_color) {
            return $setting->email_primary_color;
        }

        return config('saas.branding.email.primary', '#4F46E5');
    }

    public function emailSecondaryColor(): string
    {
        $setting = $this->globalSetting();

        if ($setting?->email_secondary_color) {
            return $setting->email_secondary_color;
        }

        return config('saas.branding.email.secondary', '#A855F7');
    }

    private function globalSetting(): ?BrandSetting
    {
        if (! $this->brandingTableReady()) {
            return null;
        }

        return Cache::remember(
            'branding.global',
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => BrandSetting::query()->find(BrandSetting::GLOBAL_ID)
                ?? BrandSetting::query()->first()
        );
    }

    private function brandingTableReady(): bool
    {
        return (bool) Cache::remember(
            'branding.table_ready',
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => Schema::hasTable('brand_settings')
        );
    }

    private function normalizeAssetPath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            return Str::after($path, 'storage/');
        }

        return $path;
    }
}
