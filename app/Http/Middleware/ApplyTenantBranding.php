<?php

namespace App\Http\Middleware;

use App\Domain\Settings\Models\BrandSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ApplyTenantBranding
{
    /**
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->currentTeam) {
            return $next($request);
        }

        if (!Schema::hasTable('brand_settings')) {
            return $next($request);
        }

        $setting = BrandSetting::query()
            ->where('team_id', $user->currentTeam->id)
            ->first();

        if (!$setting) {
            return $next($request);
        }

        config([
            'saas.branding.app_name' => $setting->app_name ?? config('saas.branding.app_name'),
            'saas.branding.logo_path' => $setting->logo_path ?? config('saas.branding.logo_path'),
            'saas.branding.colors.primary' => $setting->color_primary ?? config('saas.branding.colors.primary'),
            'saas.branding.colors.secondary' => $setting->color_secondary ?? config('saas.branding.colors.secondary'),
            'saas.branding.colors.accent' => $setting->color_accent ?? config('saas.branding.colors.accent'),
            'saas.branding.colors.bg' => $setting->color_bg ?? config('saas.branding.colors.bg'),
            'saas.branding.colors.fg' => $setting->color_fg ?? config('saas.branding.colors.fg'),
            'template.active' => $setting->template ?? config('template.active'),
        ]);

        return $next($request);
    }
}
