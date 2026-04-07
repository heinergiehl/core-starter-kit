<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppSettingsService
{
    private const CACHE_KEY = 'app_settings.all';

    public function isAvailable(): bool
    {
        if (! $this->databaseConnectionLooksReachable()) {
            return false;
        }

        try {
            return Schema::hasTable('app_settings');
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function all(): array
    {
        $all = [];

        foreach ($this->raw() as $key => $setting) {
            $all[$key] = $this->decodeValue(
                $setting['value'],
                $setting['type'],
                $setting['is_encrypted']
            );
        }

        return $all;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->raw()[$key] ?? null;

        if ($setting === null) {
            return $default;
        }

        return $this->decodeValue(
            $setting['value'],
            $setting['type'],
            $setting['is_encrypted']
        );
    }

    public function has(string $key): bool
    {
        if (! array_key_exists($key, $this->raw())) {
            return false;
        }

        $value = $this->get($key);

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
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

    /**
     * @return array<string, array{value: ?string, type: string, is_encrypted: bool}>
     */
    private function raw(): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        if (! $this->shouldUseCache()) {
            try {
                return $this->loadRawSettings();
            } catch (Throwable $exception) {
                report($exception);

                return [];
            }
        }

        try {
            return Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->loadRawSettings());
        } catch (Throwable $exception) {
            report($exception);

            try {
                return $this->loadRawSettings();
            } catch (Throwable $innerException) {
                report($innerException);

                return [];
            }
        }
    }

    /**
     * @return array<string, array{value: ?string, type: string, is_encrypted: bool}>
     */
    private function loadRawSettings(): array
    {
        return AppSetting::query()
            ->get()
            ->mapWithKeys(function (AppSetting $setting): array {
                return [
                    $setting->key => [
                        'value' => $setting->value,
                        'type' => $setting->type,
                        'is_encrypted' => $setting->is_encrypted,
                    ],
                ];
            })
            ->all();
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

        try {
            $raw = $encrypted ? Crypt::decryptString($value) : $value;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }

        return match ($type) {
            'bool' => $raw === '1',
            'int' => (int) $raw,
            'float' => (float) $raw,
            'json' => json_decode($raw, true),
            default => $raw,
        };
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

        $timeout = (float) config('database.connect_probe_timeout', 1.0);
        $socket = @fsockopen($host, (int) $port, $errorCode, $errorMessage, $timeout);

        if (! is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
