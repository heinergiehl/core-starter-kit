<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Billing\Services\SaasStatsService;
use Filament\Widgets\ChartWidget;

class RevenueGrowthChart extends ChartWidget
{
    protected ?string $heading = 'Subscription Growth';

    protected static ?int $sort = 2;

    protected ?string $maxHeight = '300px';

    protected static bool $isLazy = true;

    protected function getData(): array
    {
        $data = app(SaasStatsService::class)->getMonthlyGrowth(12);

        return [
            'datasets' => [
                [
                    'label' => 'Active Subscriptions',
                    'data' => array_values($data),
                    'fill' => true,
                    'borderColor' => '#8b5cf6', // Violet-500
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'tension' => 0.4,
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
