<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Billing\Services\SaasStatsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SaasStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $stats = app(SaasStatsService::class)->getMetrics();

        return [
            Stat::make('Monthly Recurring Revenue', '$'.number_format($stats['mrr'], 2))
                ->description('MRR')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('Active Subscriptions', number_format($stats['active_subscriptions']))
                ->description('Current paying customers')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('One-time Orders (Month)', number_format($stats['one_time_orders_this_month']))
                ->description('Paid/completed one-time purchases')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),

            Stat::make('One-time Revenue (Month)', '$'.number_format($stats['one_time_revenue_this_month'], 2))
                ->description('One-time gross revenue')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Churn Rate', $stats['churn_rate'].'%')
                ->description('30-day churn')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($stats['churn_rate'] > 5 ? 'danger' : 'success'),

            Stat::make('ARPU', '$'.number_format($stats['arpu'], 2))
                ->description('Avg Revenue Per User')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('warning'),

            Stat::make('Cancellations (Month)', number_format($stats['cancellations_this_month']))
                ->description('Effective cancellations')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
