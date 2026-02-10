<?php

namespace App\Providers;

use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Settings\Services\AppSettingsService;
use App\Domain\Settings\Services\BrandingService;
use App\Domain\Settings\Services\MailSettingsService;
use App\Support\Authorization\PermissionGuardrails;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
            $view->with('appFaviconPath', $branding->faviconPath());
            $view->with('appBrandingVersion', $branding->assetVersion());
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

        Role::saving(function (Role $role): void {
            if (! $role->exists || ! $role->isDirty('name')) {
                return;
            }

            if (! PermissionGuardrails::isProtectedRoleName((string) $role->getOriginal('name'))) {
                return;
            }

            throw ValidationException::withMessages([
                'name' => PermissionGuardrails::protectedRoleRenameMessage(),
            ]);
        });

        Role::deleting(function (Role $role): void {
            $roleName = (string) ($role->getOriginal('name') ?: $role->name);

            if (! PermissionGuardrails::isProtectedRoleName($roleName)) {
                return;
            }

            throw ValidationException::withMessages([
                'name' => PermissionGuardrails::protectedRoleDeleteMessage(),
            ]);
        });

        Permission::saving(function (Permission $permission): void {
            if (! $permission->exists || ! $permission->isDirty('name')) {
                return;
            }

            if (! PermissionGuardrails::isProtectedPermissionName((string) $permission->getOriginal('name'))) {
                return;
            }

            throw ValidationException::withMessages([
                'name' => PermissionGuardrails::protectedPermissionRenameMessage(),
            ]);
        });

        Permission::deleting(function (Permission $permission): void {
            $permissionName = (string) ($permission->getOriginal('name') ?: $permission->name);

            if (! PermissionGuardrails::isProtectedPermissionName($permissionName)) {
                return;
            }

            throw ValidationException::withMessages([
                'name' => PermissionGuardrails::protectedPermissionDeleteMessage(),
            ]);
        });
    }
}
