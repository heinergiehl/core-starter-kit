<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\Stats;
use App\Filament\Admin\Resources\BillingCustomerResource;
use App\Filament\Admin\Resources\PriceResource;
use App\Filament\Admin\Resources\SubscriptionResource;
use App\Filament\Admin\Resources\WebhookEventResource;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected string $view = 'filament.admin.widgets.quick-actions-widget';

    protected function getViewData(): array
    {
        return [
            'primaryActions' => [
                [
                    'label' => 'Open Stats',
                    'description' => 'Charts and trend insights',
                    'url' => Stats::getUrl(),
                    'icon' => 'heroicon-m-chart-bar-square',
                ],
                [
                    'label' => 'Subscriptions',
                    'description' => 'Review lifecycle and status',
                    'url' => SubscriptionResource::getUrl(),
                    'icon' => 'heroicon-o-rectangle-stack',
                ],
            ],
            'secondaryActions' => [
                [
                    'label' => 'Customers',
                    'description' => 'Inspect payer records',
                    'url' => BillingCustomerResource::getUrl(),
                    'icon' => 'heroicon-m-users',
                ],
                [
                    'label' => 'Webhook Events',
                    'description' => 'Check failed provider syncs',
                    'url' => WebhookEventResource::getUrl(),
                    'icon' => 'heroicon-m-bolt',
                ],
                [
                    'label' => 'Catalog Prices',
                    'description' => 'Adjust sellable price points',
                    'url' => PriceResource::getUrl(),
                    'icon' => 'heroicon-m-currency-dollar',
                ],
            ],
        ];
    }
}
