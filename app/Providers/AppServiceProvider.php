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

        \App\Domain\Billing\Models\Subscription::observe(\App\Domain\Billing\Observers\SubscriptionObserver::class);
        \App\Domain\Billing\Models\Order::observe(\App\Domain\Billing\Observers\OrderObserver::class);

        Event::subscribe(\App\Domain\Billing\Listeners\SendSubscriptionNotifications::class);

        \Filament\Livewire\DatabaseNotifications::pollingInterval('30s');
    }
}
