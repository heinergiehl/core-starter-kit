<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Models\BrandSetting;
use App\Support\Color\Contrast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class BrandingService
{
    private const CACHE_TTL_MINUTES = 5;

    private const DEFAULT_EMAIL_PRIMARY = '#4F46E5';

    private const DEFAULT_EMAIL_SECONDARY = '#A855F7';

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

        return $this->resolveEmailColor(
            $setting?->email_primary_color,
            config('saas.branding.email.primary'),
            self::DEFAULT_EMAIL_PRIMARY
        );
    }

    public function emailSecondaryColor(): string
    {
        $setting = $this->globalSetting();

        return $this->resolveEmailColor(
            $setting?->email_secondary_color,
            config('saas.branding.email.secondary'),
            self::DEFAULT_EMAIL_SECONDARY
        );
    }

    public function themeCssVariableOverrides(): string
    {
        $setting = $this->globalSetting();

        if (! $setting) {
            return '';
        }

        $mapping = [
            'color_primary' => '--color-primary',
            'color_secondary' => '--color-secondary',
            'color_accent' => '--color-accent',
        ];

        $declarations = [];

        foreach ($mapping as $column => $variable) {
            $rgb = $this->normalizeColorToRgbTriplet($setting->{$column} ?? null);

            if ($rgb !== null) {
                $declarations[] = "{$variable}: {$rgb}";
            }
        }

        return $declarations === [] ? '' : implode('; ', $declarations).';';
    }

    private function globalSetting(): ?BrandSetting
    {
        if (! $this->brandingTableReady()) {
            return null;
        }

        if (! $this->shouldUseCache()) {
            try {
                return $this->loadGlobalSetting();
            } catch (Throwable $exception) {
                report($exception);

                return null;
            }
        }

        try {
            return Cache::remember(
                'branding.global',
                now()->addMinutes(self::CACHE_TTL_MINUTES),
                fn () => $this->loadGlobalSetting()
            );
        } catch (Throwable $exception) {
            report($exception);

            try {
                return $this->loadGlobalSetting();
            } catch (Throwable $innerException) {
                report($innerException);

                return null;
            }
        }
    }

    private function brandingTableReady(): bool
    {
        if (! $this->databaseConnectionLooksReachable()) {
            return false;
        }

        if (! $this->shouldUseCache()) {
            return $this->detectBrandingTableReady();
        }

        try {
            return (bool) Cache::remember(
                'branding.table_ready',
                now()->addMinutes(self::CACHE_TTL_MINUTES),
                fn (): bool => $this->detectBrandingTableReady()
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->detectBrandingTableReady();
        }
    }

    private function loadGlobalSetting(): ?BrandSetting
    {
        return BrandSetting::query()->find(BrandSetting::GLOBAL_ID)
            ?? BrandSetting::query()->first();
    }

    private function detectBrandingTableReady(): bool
    {
        try {
            return Schema::hasTable('brand_settings');
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
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

    private function normalizeColorToRgbTriplet(?string $color): ?string
    {
        $value = trim((string) $color);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $value, $matches) === 1) {
            $hex = strtolower($matches[1]);

            if (strlen($hex) === 3) {
                $hex = preg_replace('/(.)/', '$1$1', $hex) ?? $hex;
            }

            return sprintf(
                '%d %d %d',
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2))
            );
        }

        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $value, $matches) === 1) {
            return $this->normalizeRgbParts([$matches[1], $matches[2], $matches[3]]);
        }

        if (preg_match('/^(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})$/', $value, $matches) === 1) {
            return $this->normalizeRgbParts([$matches[1], $matches[2], $matches[3]]);
        }

        return null;
    }

    private function resolveEmailColor(mixed $settingColor, mixed $configColor, string $fallback): string
    {
        $normalizedSetting = Contrast::normalizeHex(is_string($settingColor) ? $settingColor : null);

        if ($normalizedSetting !== null) {
            return $normalizedSetting;
        }

        $normalizedConfig = Contrast::normalizeHex(is_string($configColor) ? $configColor : null);

        if ($normalizedConfig !== null) {
            return $normalizedConfig;
        }

        return $fallback;
    }

    /**
     * @param  array<int, string|int>  $parts
     */
    private function normalizeRgbParts(array $parts): ?string
    {
        $values = array_map('intval', $parts);

        foreach ($values as $value) {
            if ($value < 0 || $value > 255) {
                return null;
            }
        }

        return sprintf('%d %d %d', $values[0], $values[1], $values[2]);
    }

    private function shouldUseCache(): bool
    {
        $defaultStore = config('cache.default');
        $driver = config("cache.stores.{$defaultStore}.driver");

        return $driver !== 'database';
    }

    private function databaseConnectionLooksReachable(): bool
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! is_array($connection)) {
            return true;
        }

        $driver = $connection['driver'] ?? null;

        if ($driver === 'sqlite') {
            return true;
        }

        $host = $connection['host'] ?? null;
        $port = $connection['port'] ?? null;

        if (! is_string($host) || trim($host) === '' || ! is_numeric($port)) {
            return true;
        }

        $timeout = (float) env('DB_CONNECT_PROBE_TIMEOUT', 1.0);
        $socket = @fsockopen($host, (int) $port, $errorCode, $errorMessage, $timeout);

        if (! is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
