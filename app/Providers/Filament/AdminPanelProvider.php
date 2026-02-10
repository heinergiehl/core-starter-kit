<?php

namespace App\Providers\Filament;

use App\Domain\Settings\Services\BrandingService;
use App\Filament\Admin\Pages\Dashboard;
use App\Http\Middleware\EnsureAdminUser;
use App\Support\Authorization\PermissionGuardrails;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id(PermissionGuardrails::ADMIN_PANEL_ID)
            ->path(PermissionGuardrails::ADMIN_PANEL_PATH)
            ->login()
            ->authGuard(PermissionGuardrails::guardName())
            ->font(config('saas.branding.fonts.sans', 'Instrument Sans'))
            ->serifFont(config('saas.branding.fonts.display', 'Instrument Serif'))
            ->colors([
                'primary' => Color::Slate,
            ])
            ->brandName(fn (): string => app(BrandingService::class)->appName())
            ->brandLogoHeight('3rem')
            ->brandLogo(fn (): ?string => ($logo = app(BrandingService::class)->logoPath()) ? asset($logo) : null)
            ->favicon(function (): string {
                $branding = app(BrandingService::class);
                $faviconPath = $branding->faviconPath() ?: (string) config('saas.branding.favicon_path', 'branding/shipsolid-s-favicon.svg');
                $version = rawurlencode($branding->assetVersion());

                return asset($faviconPath).'?v='.$version;
            })
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureAdminUser::class,
            ])
            ->globalSearch(false)
            ->databaseNotifications();
    }
}
