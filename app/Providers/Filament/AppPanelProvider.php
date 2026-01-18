<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use App\Http\Middleware\EnsureTeamIsSelected;
use App\Http\Middleware\SetLocale;
use App\Domain\Settings\Services\BrandingService;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->login()
            ->font(config('saas.branding.fonts.sans', 'Instrument Sans'))
            ->serifFont(config('saas.branding.fonts.display', 'Instrument Serif'))
            ->colors([
                'primary' => config('saas.branding.colors.primary') ?? Color::Teal,
            ])
            ->brandName(fn (): string => app(BrandingService::class)->appNameFor(auth()->user()?->currentTeam))
            ->brandLogoHeight('2rem')
            ->brandLogo(function (): HtmlString {
                $branding = app(BrandingService::class);
                $team = auth()->user()?->currentTeam;
                $name = $branding->appNameFor($team);
                $logoPath = $branding->logoPathFor($team);
                $logoUrl = $logoPath ? asset($logoPath) : null;

                $nameEscaped = e($name);
                $logoMarkup = '';

                if ($logoUrl) {
                    $logoMarkup = sprintf(
                        '<img src="%s" alt="%s" style="width:28px;height:28px;border-radius:10px;object-fit:cover;display:block;" />',
                        e($logoUrl),
                        $nameEscaped
                    );
                }

                $markup = sprintf(
                    '<div style="display:flex;align-items:center;gap:8px;min-width:0;">%s<span style="font-size:0.875rem;font-weight:600;line-height:1;color:inherit;max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">%s</span></div>',
                    $logoMarkup,
                    $nameEscaped
                );

                return new HtmlString($markup);
            })
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                InitializeTenancyByDomain::class,
                \App\Http\Middleware\ResolveTeamByDomain::class,
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureTeamIsSelected::class,
                \App\Http\Middleware\EnsureSubscription::class,
                \App\Http\Middleware\ApplyTenantBranding::class,
            ]);
    }
}
