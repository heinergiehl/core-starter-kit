<?php

use App\Domain\Billing\Exports\CatalogPublishService;
use App\Domain\Billing\Imports\CatalogImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:import-catalog {provider=stripe} {--apply} {--update}', function () {
    $provider = (string) $this->argument('provider');
    $apply = (bool) $this->option('apply');
    $update = (bool) $this->option('update');

    $service = app(CatalogImportService::class);
    $result = $apply
        ? $service->apply($provider, $update)
        : $service->preview($provider, $update);

    $summary = $result['summary'] ?? [];
    $warnings = $result['warnings'] ?? [];

    $this->info(($apply ? 'Applied' : 'Previewed')." catalog import for provider [{$provider}].");
    $this->line(sprintf(
        'Products: %d create, %d update, %d skip',
        $summary['products']['create'] ?? 0,
        $summary['products']['update'] ?? 0,
        $summary['products']['skip'] ?? 0
    ));
    $this->line(sprintf(
        'Prices: %d create, %d update, %d skip, %d skipped',
        $summary['prices']['create'] ?? 0,
        $summary['prices']['update'] ?? 0,
        $summary['prices']['skip'] ?? 0,
        $summary['prices']['skipped'] ?? 0
    ));

    if (! $apply) {
        $this->comment('Run with --apply to persist changes. Use --update to overwrite existing records.');
    }

    if (! empty($warnings)) {
        $this->warn('Warnings:');
        foreach ($warnings as $warning) {
            $this->line("- {$warning}");
        }
    }
})->purpose('Import billing catalog from a payment provider');

Artisan::command('billing:publish-catalog {provider=stripe} {--apply} {--update}', function () {
    $provider = (string) $this->argument('provider');
    $apply = (bool) $this->option('apply');
    $update = (bool) $this->option('update');

    $service = app(CatalogPublishService::class);
    $result = $apply
        ? $service->apply($provider, $update)
        : $service->preview($provider, $update);

    $summary = $result['summary'] ?? [];
    $warnings = $result['warnings'] ?? [];

    $this->info(($apply ? 'Applied' : 'Previewed')." catalog publish for provider [{$provider}].");
    $this->line(sprintf(
        'Products: %d create, %d update, %d skip',
        $summary['products']['create'] ?? 0,
        $summary['products']['update'] ?? 0,
        $summary['products']['skip'] ?? 0
    ));
    $this->line(sprintf(
        'Prices: %d create, %d update, %d link, %d skip',
        $summary['prices']['create'] ?? 0,
        $summary['prices']['update'] ?? 0,
        $summary['prices']['link'] ?? 0,
        $summary['prices']['skip'] ?? 0
    ));

    if (! $apply) {
        $this->comment('Run with --apply to persist changes. Use --update to push updates for existing products.');
    }

    if (! empty($warnings)) {
        $this->warn('Warnings:');
        foreach ($warnings as $warning) {
            $this->line("- {$warning}");
        }
    }
})->purpose('Publish the billing catalog to a payment provider');
