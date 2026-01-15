<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Daily sync for LemonSqueezy (no webhooks for products)
        $schedule->command('billing:sync-products --provider=lemonsqueezy')
            ->daily()
            ->at('03:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Weekly full sync (both providers) on Sundays as a safety net
        $schedule->command('billing:sync-products')
            ->weekly()
            ->sundays()
            ->at('04:00')
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'team' => \App\Http\Middleware\EnsureTeamIsSelected::class,
            'subscribed' => \App\Http\Middleware\EnsureSubscription::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\ResolveTeamByDomain::class,
            \App\Http\Middleware\EnsureOnboardingComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
