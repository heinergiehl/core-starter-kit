<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Billing\Services\BillingMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BillingMetricsWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    protected function getStats(): array
    {
        $metrics = app(BillingMetricsService::class)->snapshot();

        return [
            Stat::make('MRR', $this->formatCurrency($metrics['mrr']))
                ->description('Monthly recurring revenue')
                ->color('success'),
            Stat::make('ARR', $this->formatCurrency($metrics['arr']))
                ->description('Annualized recurring revenue'),
            Stat::make('Active subscriptions', (string) $metrics['active_subscriptions'])
                ->description('Live and trialing'),
            Stat::make('ARPU', $this->formatCurrency($metrics['arpu']))
                ->description('Per active customer'),
            Stat::make('Cancellations', (string) $metrics['cancellations_last_30_days'])
                ->description('Last 30 days'),
            Stat::make('Churn', number_format($metrics['churn_rate'], 1).'%')
                ->description('Last 30 days'),
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return 'USD '.number_format($amount, 2);
    }
}
