<?php

namespace App\Providers;

use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Settings\Services\BrandingService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        View::composer('*', function ($view): void {
            $user = request()->user();
            $team = $user?->currentTeam;
            $branding = app(BrandingService::class);
            $entitlements = $team ? app(EntitlementService::class)->forTeam($team) : null;

            $view->with('themeTokens', $branding->tokensFor($team));
            $view->with('appBrandName', $branding->appNameFor($team));
            $view->with('appLogoPath', $branding->logoPathFor($team));
            $view->with('entitlements', $entitlements);
        });
    }
}
