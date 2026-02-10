<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class AppSettingsService
{
    private const CACHE_KEY = 'app_settings.all';

    public function isAvailable(): bool
    {
        return Schema::hasTable('app_settings');
    }

    public function all(): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return AppSetting::query()
                ->get()
                ->mapWithKeys(function (AppSetting $setting): array {
                    return [$setting->key => $this->decodeValue($setting->value, $setting->type, $setting->is_encrypted)];
                })
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function set(string $key, mixed $value, ?string $group = null, ?string $type = null, bool $encrypted = false): AppSetting
    {
        $type = $type ?? $this->inferType($value);
        $encoded = $this->encodeValue($value, $type, $encrypted);

        $setting = AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'type' => $type,
                'value' => $encoded,
                'is_encrypted' => $encrypted,
            ]
        );

        Cache::forget(self::CACHE_KEY);

        return $setting;
    }

    public function featureEnabled(string $key, bool $default = true): bool
    {
        return (bool) $this->get("features.{$key}", $default);
    }

    public function applyToConfig(): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $supportEmail = $this->get('support.email');
        $supportDiscord = $this->get('support.discord');

        if ($supportEmail !== null) {
            config(['saas.support.email' => $supportEmail]);
        }

        if ($supportDiscord !== null) {
            config(['saas.support.discord' => $supportDiscord]);
        }

        $features = [
            'blog',
            'roadmap',
            'announcements',
            'onboarding',
        ];

        foreach ($features as $feature) {
            $flag = $this->get("features.{$feature}");
            if ($flag !== null) {
                config(["saas.features.{$feature}" => (bool) $flag]);
            }
        }

        $billingDefaultProvider = $this->get('billing.default_provider');
        if (filled($billingDefaultProvider)) {
            config(['saas.billing.default_provider' => strtolower((string) $billingDefaultProvider)]);
        }
    }

    private function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };
    }

    private function encodeValue(mixed $value, string $type, bool $encrypted): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = match ($type) {
            'bool' => $value ? '1' : '0',
            'int' => (string) (int) $value,
            'float' => (string) (float) $value,
            'json' => json_encode($value, JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };

        return $encrypted ? Crypt::encryptString($raw) : $raw;
    }

    private function decodeValue(?string $value, string $type, bool $encrypted): mixed
    {
        if ($value === null) {
            return null;
        }

        $raw = $encrypted ? Crypt::decryptString($value) : $value;

        return match ($type) {
            'bool' => $raw === '1',
            'int' => (int) $raw,
            'float' => (float) $raw,
            'json' => json_decode($raw, true),
            default => $raw,
        };
    }
}
