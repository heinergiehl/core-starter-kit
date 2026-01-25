<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Billing\Services\SaasStatsService;
use Filament\Widgets\ChartWidget;

class PlanDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Plan Distribution';

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '300px';

    protected static bool $isLazy = true;

    protected function getData(): array
    {
        $data = app(SaasStatsService::class)->getPlanDistribution();

        return [
            'datasets' => [
                [
                    'label' => 'Subscriptions',
                    'data' => array_values($data),
                    'backgroundColor' => [
                        '#3b82f6', // Blue-500
                        '#10b981', // Emerald-500
                        '#f59e0b', // Amber-500
                        '#6366f1', // Indigo-500
                        '#ec4899', // Pink-500
                    ],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
