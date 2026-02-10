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

    protected ?array $distributionCache = null;

    protected function getData(): array
    {
        $data = $this->getDistribution();

        if ($this->isEmptyState()) {
            return [
                'datasets' => [
                    [
                        'label' => 'No active subscriptions',
                        'data' => [1],
                        'backgroundColor' => ['#e5e7eb'],
                        'borderWidth' => 0,
                    ],
                ],
                'labels' => ['No active plans yet'],
            ];
        }

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

    public function getDescription(): ?string
    {
        return $this->isEmptyState()
            ? 'No active or trialing subscriptions yet.'
            : 'Share of active subscriptions by plan.';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => ! $this->isEmptyState(),
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'enabled' => ! $this->isEmptyState(),
                ],
            ],
            'cutout' => '68%',
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    private function getDistribution(): array
    {
        return $this->distributionCache ??= app(SaasStatsService::class)->getPlanDistribution();
    }

    private function isEmptyState(): bool
    {
        return $this->getDistribution() === [];
    }
}
