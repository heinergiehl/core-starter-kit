<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\PlanDistributionChart;
use App\Filament\Admin\Widgets\RevenueGrowthChart;
use App\Filament\Admin\Widgets\SaasStatsWidget;
use Filament\Pages\Page;

class Stats extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Stats';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.admin.pages.stats';

    protected function getHeaderWidgets(): array
    {
        return [
            SaasStatsWidget::class,
            RevenueGrowthChart::class,
            PlanDistributionChart::class,
        ];
    }
}
