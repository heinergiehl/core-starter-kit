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
use App\Http\Controllers\Content\OgImageController;
use App\Http\Controllers\Content\RssController;
use App\Http\Controllers\Content\SitemapController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Feedback\RoadmapController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

Route::get('/pricing', PricingController::class)->name('pricing');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/roadmap', [RoadmapController::class, 'index'])->name('roadmap');
Route::post('/locale', LocaleController::class)->name('locale.update');
Route::post('/announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])
    ->name('announcements.dismiss');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/rss.xml', RssController::class)->name('rss');
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
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::delete('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
});

// Impersonation (admin only)
Route::middleware('auth')->group(function () {
    Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');
});

require __DIR__.'/auth.php';
