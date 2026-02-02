<?php

namespace App\Providers;

use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Settings\Services\AppSettingsService;
use App\Domain\Settings\Services\BrandingService;
use App\Domain\Settings\Services\MailSettingsService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local')
            && class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)
        ) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Database\Eloquent\Model::shouldBeStrict(! $this->app->isProduction());

        app(AppSettingsService::class)->applyToConfig();
        app(MailSettingsService::class)->applyConfig();

        // Global Branding for all views
        View::composer('*', function ($view): void {
            $branding = app(BrandingService::class);
            $view->with('appBrandName', $branding->appName());
            $view->with('appLogoPath', $branding->logoPath());
        });

        // Entitlements only for authenticated app layout
        View::composer(['layouts.app', 'components.app-layout'], function ($view): void {
            $user = request()->user();
            if ($user) {
                $entitlements = app(EntitlementService::class)->forUser($user);
                $view->with('entitlements', $entitlements);
            }
        });

        Event::subscribe(\App\Domain\Billing\Listeners\SendSubscriptionNotifications::class);

        \Filament\Livewire\DatabaseNotifications::pollingInterval('30s');

        \App\Domain\Billing\Models\Product::observe(\App\Domain\Billing\Observers\ProductObserver::class);
        \App\Domain\Billing\Models\Price::observe(\App\Domain\Billing\Observers\PriceObserver::class);
    }
}
