<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Billing\BillingCheckoutController;
use App\Http\Controllers\Billing\BillingPortalController;
use App\Http\Controllers\Billing\BillingProcessingController;
use App\Http\Controllers\Billing\BillingStatusController;
use App\Http\Controllers\Billing\CheckoutStartController;
use App\Http\Controllers\Billing\PaddleCheckoutController;
use App\Http\Controllers\Billing\PricingController;
use App\Http\Controllers\Billing\WebhookController;
use App\Http\Controllers\Blog\BlogController;
use App\Http\Controllers\Content\BlogSitemapController;
use App\Http\Controllers\Content\BrandingAssetController;
use App\Http\Controllers\Content\DocsController;
use App\Http\Controllers\Content\MarketingSitemapController;
use App\Http\Controllers\Content\OgImageController;
use App\Http\Controllers\Content\RssController;
use App\Http\Controllers\Content\SitemapController;
use App\Http\Controllers\Content\SolutionPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Feedback\RoadmapController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RepoAccessController;
use App\Http\Controllers\TwoFactorController;
use App\Support\Localization\LocalizedRouteService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Public marketing routes are locale-prefixed for SEO and shareable URLs.
 * Legacy non-prefixed URLs are permanently redirected.
 */
$localizedRouteService = app(LocalizedRouteService::class);
$defaultLocale = $localizedRouteService->defaultLocale();
$localePattern = $localizedRouteService->localePattern();

$marketingRouteDefinitions = [
    [
        'uri' => '/',
        'name' => 'home',
        'action' => fn () => view('welcome'),
    ],
    [
        'uri' => '/features',
        'name' => 'features',
        'action' => fn () => view('features'),
    ],
    [
        'uri' => '/pricing',
        'name' => 'pricing',
        'action' => PricingController::class,
    ],
    [
        'uri' => '/solutions',
        'name' => 'solutions.index',
        'action' => [SolutionPageController::class, 'index'],
    ],
    [
        'uri' => '/solutions/{slug}',
        'name' => 'solutions.show',
        'action' => [SolutionPageController::class, 'show'],
        'where' => ['slug' => '[a-z0-9-]+'],
    ],
    [
        'uri' => '/blog',
        'name' => 'blog.index',
        'action' => [BlogController::class, 'index'],
    ],
    [
        'uri' => '/blog/{slug}',
        'name' => 'blog.show',
        'action' => [BlogController::class, 'show'],
    ],
    [
        'uri' => '/docs',
        'name' => 'docs.index',
        'action' => [DocsController::class, 'index'],
    ],
    [
        'uri' => '/docs/{page}',
        'name' => 'docs.show',
        'action' => [DocsController::class, 'show'],
        'where' => ['page' => '[A-Za-z0-9-]+'],
    ],
    [
        'uri' => '/roadmap',
        'name' => 'roadmap',
        'action' => [RoadmapController::class, 'index'],
    ],
    [
        'uri' => '/rss.xml',
        'name' => 'rss',
        'action' => RssController::class,
    ],
];

$redirectToLocalized = static function (Request $request, string $routeName, array $parameters = []) use ($defaultLocale) {
    $url = route($routeName, array_merge($parameters, ['locale' => $defaultLocale]));
    $queryParams = $request->query();
    unset($queryParams['lang']);
    $query = http_build_query($queryParams);

    return redirect()->to($query ? "{$url}?{$query}" : $url, 301);
};

foreach ($marketingRouteDefinitions as $definition) {
    $legacyRoute = Route::get($definition['uri'], function (Request $request) use ($definition, $redirectToLocalized) {
        $parameters = $request->route()?->parametersWithoutNulls() ?? [];

        return $redirectToLocalized($request, $definition['name'], $parameters);
    })->name('legacy.'.$definition['name']);

    if (isset($definition['where']) && is_array($definition['where'])) {
        $legacyRoute->where($definition['where']);
    }
}

Route::prefix('{locale}')
    ->where(['locale' => $localePattern])
    ->group(function () use ($defaultLocale, $marketingRouteDefinitions) {
        foreach ($marketingRouteDefinitions as $definition) {
            $localizedRoute = Route::get($definition['uri'], $definition['action'])
                ->defaults('locale', $defaultLocale)
                ->name($definition['name']);

            if (isset($definition['where']) && is_array($definition['where'])) {
                $localizedRoute->where($definition['where']);
            }
        }
    });

Route::post('/locale', LocaleController::class)->name('locale.update');
Route::post('/announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])
    ->name('announcements.dismiss');

// Branding asset fallback route for environments where /storage symlink is unavailable/restricted.
Route::get('/branding/{path}', BrandingAssetController::class)
    ->where('path', '.*')
    ->name('branding.asset');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/sitemaps/marketing.xml', MarketingSitemapController::class)->name('sitemap.marketing');
Route::get('/sitemaps/blog.xml', BlogSitemapController::class)->name('sitemap.blog');
Route::get('/og', OgImageController::class)->name('og');
Route::get('/og/blog/{slug}', [OgImageController::class, 'blog'])->name('og.blog');

// Webhook route - explicitly bypass CSRF protection
Route::post('/webhooks/{provider}', WebhookController::class)
    ->withoutMiddleware([
        VerifyCsrfToken::class,
    ])
    ->middleware('throttle:120,1')
    ->name('webhooks.handle');

Route::post('/billing/checkout', [BillingCheckoutController::class, 'store'])
    ->middleware('redirect_if_subscribed')
    ->name('billing.checkout');
Route::get('/billing/processing', BillingProcessingController::class)
    ->middleware(\App\Http\Middleware\RestoreCheckoutSession::class)
    ->name('billing.processing');
Route::get('/checkout/start', CheckoutStartController::class)
    ->middleware('redirect_if_subscribed')
    ->name('checkout.start');
Route::get('/paddle/checkout', PaddleCheckoutController::class)
    ->middleware('redirect_if_subscribed')
    ->name('paddle.checkout');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'subscribed'])
    ->name('dashboard');

// Onboarding
Route::middleware(['auth'])->group(function () {
    Route::get('/onboarding', [\App\Http\Controllers\OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding', [\App\Http\Controllers\OnboardingController::class, 'update'])->name('onboarding.update');
    Route::post('/onboarding/skip', [\App\Http\Controllers\OnboardingController::class, 'skip'])->name('onboarding.skip');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/billing/status', BillingStatusController::class)->name('billing.status');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/billing', [\App\Http\Controllers\Billing\BillingController::class, 'index'])
        ->name('billing.index');
    Route::post('/billing/cancel', [\App\Http\Controllers\Billing\BillingController::class, 'cancel'])
        ->name('billing.cancel');
    Route::post('/billing/resume', [\App\Http\Controllers\Billing\BillingController::class, 'resume'])
        ->name('billing.resume');
    Route::post('/billing/change-plan', [\App\Http\Controllers\Billing\BillingController::class, 'changePlan'])
        ->name('billing.change-plan');
    Route::post('/billing/sync-pending-plan-change', [\App\Http\Controllers\Billing\BillingController::class, 'syncPendingPlanChange'])
        ->name('billing.sync-pending-plan-change');
    Route::get('/billing/portal/{provider?}', BillingPortalController::class)
        ->name('billing.portal');

    Route::get('/app/orders/{order}/invoice', [\App\Http\Controllers\Billing\InvoiceController::class, 'download'])
        ->name('invoices.download');

    Route::get('/app/invoices/{invoice}/pdf', [\App\Http\Controllers\Billing\InvoiceController::class, 'downloadInvoice'])
        ->name('invoices.download_invoice');
});

Route::middleware('auth')->group(function () {
    Route::post('/roadmap', [RoadmapController::class, 'store'])->name('roadmap.store');
    Route::post('/roadmap/{feature}/vote', [RoadmapController::class, 'vote'])->name('roadmap.vote');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/repo-access/sync', [RepoAccessController::class, 'sync'])->name('repo-access.sync');
    Route::delete('/repo-access/github', [RepoAccessController::class, 'disconnectGithub'])->name('repo-access.github.disconnect');
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::delete('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
});

// Impersonation (admin only)
Route::middleware('auth')->group(function () {
    Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');
});

require __DIR__.'/auth.php';
