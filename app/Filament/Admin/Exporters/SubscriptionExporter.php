<?php

namespace App\Filament\Admin\Exporters;

use App\Domain\Billing\Models\Subscription;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class SubscriptionExporter extends Exporter
{
    protected static ?string $model = Subscription::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('user.name')
                ->label('Customer'),
            ExportColumn::make('user.email')
                ->label('Customer Email'),
            ExportColumn::make('provider'),
            ExportColumn::make('provider_id'),
            ExportColumn::make('plan_key')
                ->label('Plan'),
            ExportColumn::make('status'),
            ExportColumn::make('quantity'),
            ExportColumn::make('trial_ends_at'),
            ExportColumn::make('renews_at'),
            ExportColumn::make('ends_at'),
            ExportColumn::make('canceled_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your subscription export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
