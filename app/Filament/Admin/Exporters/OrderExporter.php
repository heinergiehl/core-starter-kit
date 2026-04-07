<?php

namespace App\Filament\Admin\Exporters;

use App\Domain\Billing\Models\Order;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

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
            ExportColumn::make('amount')
                ->formatStateUsing(fn ($state): string => number_format(((int) $state) / 100, 2)),
            ExportColumn::make('currency'),
            ExportColumn::make('paid_at'),
            ExportColumn::make('refunded_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your order export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
