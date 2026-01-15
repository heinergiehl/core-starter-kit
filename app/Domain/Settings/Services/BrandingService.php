<?php

namespace App\Domain\Settings\Services;

use App\Domain\Organization\Models\Team;
use App\Domain\Settings\Models\BrandSetting;

class BrandingService
{


    public function appNameFor(?Team $team): string
    {
        $setting = $this->settingFor($team);

        if ($setting?->app_name) {
            return $setting->app_name;
        }

        if ($team?->name) {
            return $team->name;
        }

        return config('saas.branding.app_name', config('app.name'));
    }

    public function logoPathFor(?Team $team): ?string
    {
        return $this->settingFor($team)?->logo_path ?: config('saas.branding.logo_path');
    }

    public function templateForGuest(): string
    {
        $globalTemplate = BrandSetting::query()->whereNull('team_id')->value('template');

        return $globalTemplate ?: config('template.active', 'default');
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


}
