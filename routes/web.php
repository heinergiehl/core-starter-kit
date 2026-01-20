<?php

use App\Domain\Billing\Services\EntitlementService;
use App\Http\Controllers\Billing\BillingCheckoutController;
use App\Http\Controllers\Billing\BillingPortalController;
use App\Http\Controllers\Billing\BillingProcessingController;
use App\Http\Controllers\Billing\BillingStatusController;
use App\Http\Controllers\Billing\CheckoutStartController;
use App\Http\Controllers\Billing\PaddleCheckoutController;
use App\Http\Controllers\Billing\PricingController;
use App\Http\Controllers\Billing\WebhookController;
use App\Http\Controllers\Blog\BlogController;
use App\Http\Controllers\Content\OgImageController;
use App\Http\Controllers\Content\RssController;
use App\Http\Controllers\Content\SitemapController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Feedback\RoadmapController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Organization\TeamInvitationController;
use App\Http\Controllers\Organization\TeamController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => view('welcome'))->name('home');

Route::get('/pricing', PricingController::class)->name('pricing');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/roadmap', [RoadmapController::class, 'index'])->name('roadmap');
Route::post('/locale', LocaleController::class)->name('locale.update');
Route::post('/announcements/{announcement}/dismiss', function (\App\Domain\Content\Models\Announcement $announcement) {
    $dismissed = session('dismissed_announcements', []);
    $dismissed[] = $announcement->id;
    session(['dismissed_announcements' => array_unique($dismissed)]);
    return response()->json(['success' => true]);
})->name('announcements.dismiss');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/rss.xml', RssController::class)->name('rss');
Route::get('/og', OgImageController::class)->name('og');
Route::get('/og/blog/{slug}', [OgImageController::class, 'blog'])->name('og.blog');

Route::get('/invitations/{token}', [TeamInvitationController::class, 'show'])->name('invitations.accept');
Route::post('/invitations/{token}', [TeamInvitationController::class, 'store'])->name('invitations.store');
Route::post('/invitations/{token}/register', [TeamInvitationController::class, 'register'])->name('invitations.register');

// Webhook route - explicitly bypass tenancy middleware
Route::post('/webhooks/{provider}', WebhookController::class)
    ->withoutMiddleware([
        VerifyCsrfToken::class,
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
        \App\Http\Middleware\ResolveTeamByDomain::class,
    ])
    ->name('webhooks.handle');

Route::post('/billing/checkout', [BillingCheckoutController::class, 'store'])
    ->name('billing.checkout');
Route::get('/billing/processing', BillingProcessingController::class)
    ->middleware(\App\Http\Middleware\RestoreCheckoutSession::class)
    ->name('billing.processing');
Route::get('/checkout/start', CheckoutStartController::class)
    ->name('checkout.start');
Route::get('/paddle/checkout', PaddleCheckoutController::class)
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
    Route::get('/teams/select', [TeamController::class, 'select'])->name('teams.select');
    Route::post('/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');

    Route::get('/billing', [\App\Http\Controllers\Billing\BillingController::class, 'index'])
        ->middleware('team')
        ->name('billing.index');
    Route::post('/billing/cancel', [\App\Http\Controllers\Billing\BillingController::class, 'cancel'])
        ->middleware('team')
        ->name('billing.cancel');
    Route::post('/billing/resume', [\App\Http\Controllers\Billing\BillingController::class, 'resume'])
        ->middleware('team')
        ->name('billing.resume');
    Route::post('/billing/change-plan', [\App\Http\Controllers\Billing\BillingController::class, 'changePlan'])
        ->middleware('team')
        ->name('billing.change-plan');
    Route::get('/billing/portal/{provider?}', BillingPortalController::class)
        ->middleware('team')
        ->name('billing.portal');

    Route::get('/app/orders/{order}/invoice', [\App\Http\Controllers\Billing\InvoiceController::class, 'download'])
        ->middleware('team')
        ->name('invoices.download');

    Route::get('/app/invoices/{invoice}/pdf', [\App\Http\Controllers\Billing\InvoiceController::class, 'downloadInvoice'])
        ->middleware('team')
        ->name('invoices.download_invoice');
});

Route::middleware('auth')->group(function () {
    Route::post('/roadmap', [RoadmapController::class, 'store'])->name('roadmap.store');
    Route::post('/roadmap/{feature}/vote', [RoadmapController::class, 'vote'])->name('roadmap.vote');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

// Impersonation (admin only)
Route::middleware('auth')->group(function () {
    Route::get('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');
    Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
});

require __DIR__.'/auth.php';
