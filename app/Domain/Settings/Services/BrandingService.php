<?php

namespace App\Domain\Settings\Services;

use App\Domain\Organization\Models\Team;
use App\Domain\Settings\Models\BrandSetting;

class BrandingService
{
    public function tokensFor(?Team $team): array
    {
        $defaults = config('saas.branding.colors', []);
        $setting = $this->settingFor($team);

        return [
            'primary' => $this->normalizeColor($setting?->color_primary, $defaults['primary'] ?? '99 102 241'),
            'secondary' => $this->normalizeColor($setting?->color_secondary, $defaults['secondary'] ?? '16 185 129'),
            'accent' => $this->normalizeColor($setting?->color_accent, $defaults['accent'] ?? '248 113 113'),
            'bg' => $this->normalizeColor($setting?->color_bg, $defaults['bg'] ?? '15 23 42'),
            'fg' => $this->normalizeColor($setting?->color_fg, $defaults['fg'] ?? '248 250 252'),
        ];
    }

    public function appNameFor(?Team $team): string
    {
        $setting = $this->settingFor($team);

        return $setting?->app_name ?: config('saas.branding.app_name', config('app.name'));
    }

    public function logoPathFor(?Team $team): ?string
    {
        return $this->settingFor($team)?->logo_path ?: config('saas.branding.logo_path');
    }

    private function settingFor(?Team $team): ?BrandSetting
    {
        if ($team) {
            $teamSetting = BrandSetting::query()->where('team_id', $team->id)->first();

            if ($teamSetting) {
                return $teamSetting;
            }
        }

        return BrandSetting::query()->whereNull('team_id')->first();
    }

    private function normalizeColor(?string $value, string $fallback): string
    {
        if (!$value) {
            return $fallback;
        }

        $value = trim($value);

        if (str_starts_with($value, '#')) {
            $hex = ltrim($value, '#');

            if (strlen($hex) === 3) {
                $hex = "{$hex[0]}{$hex[0]}{$hex[1]}{$hex[1]}{$hex[2]}{$hex[2]}";
            }

            if (strlen($hex) === 6 && ctype_xdigit($hex)) {
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));

                return "{$r} {$g} {$b}";
            }
        }

        if (preg_match('/^\d+\s+\d+\s+\d+$/', $value)) {
            return $value;
        }

        if (preg_match('/^\d+,\s*\d+,\s*\d+$/', $value)) {
            return str_replace(',', ' ', $value);
        }

        return $fallback;
    }
}
