<?php

namespace App\Providers;

use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Settings\Services\BrandingService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

use App\Domain\Billing\Models\Price;
use App\Observers\PriceObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Price::observe(PriceObserver::class);
        \App\Domain\Billing\Models\Product::observe(\App\Observers\ProductObserver::class);

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('brand_settings')) {
                $globalSetting = \App\Domain\Settings\Models\BrandSetting::whereNull('team_id')->first();
                
                if ($globalSetting) {
                    config([
                        'saas.branding.app_name' => $globalSetting->app_name ?? config('saas.branding.app_name'),
                        'saas.branding.logo_path' => $globalSetting->logo_path ?? config('saas.branding.logo_path'),
                        'saas.branding.colors.primary' => $globalSetting->color_primary ?? config('saas.branding.colors.primary'),
                        'saas.branding.colors.secondary' => $globalSetting->color_secondary ?? config('saas.branding.colors.secondary'),
                        'saas.branding.colors.accent' => $globalSetting->color_accent ?? config('saas.branding.colors.accent'),
                        'saas.branding.colors.bg' => $globalSetting->color_bg ?? config('saas.branding.colors.bg'),
                        'saas.branding.colors.fg' => $globalSetting->color_fg ?? config('saas.branding.colors.fg'),
                        'template.active' => $globalSetting->template ?? config('template.active'),
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Suppress errors during early boot (e.g. migrations)
        }



        View::composer('*', function ($view): void {
            $user = request()->user();
            // Only apply Tenant Branding if we are inside the App Panel (/app/*)
            // Otherwise (Marketing, Auth, Admin), use Global Branding (null).
            $isAppRoute = request()->is('app') || request()->is('app/*');
            $team = ($isAppRoute && $user) ? $user->currentTeam : null;
            
            $branding = app(BrandingService::class);
            $entitlements = ($user && $user->currentTeam) ? app(EntitlementService::class)->forTeam($user->currentTeam) : null;

            $view->with('appBrandName', $branding->appNameFor($team));
            $view->with('appLogoPath', $branding->logoPathFor($team));
            $view->with('entitlements', $entitlements);
        });
    }
}
