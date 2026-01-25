<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Invoice;
use App\Filament\Admin\Resources\InvoiceResource\Pages\ListInvoices;
use App\Filament\Admin\Resources\InvoiceResource\Pages\ViewInvoice;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Invoice')
                ->schema([
                    TextEntry::make('user.name')->label('Customer'),
                    TextEntry::make('provider')->badge(),
                    TextEntry::make('number')->label('Invoice #'),
                    TextEntry::make('provider_id')->label('Provider ID'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('amount_due')
                        ->label('Amount due')
                        ->formatStateUsing(fn ($state, Invoice $record): string => $record->currency
                            ? strtoupper($record->currency).' '.number_format(((int) $state) / 100, 2)
                            : (string) $state),
                    TextEntry::make('amount_paid')
                        ->label('Amount paid')
                        ->formatStateUsing(fn ($state, Invoice $record): string => $record->currency
                            ? strtoupper($record->currency).' '.number_format(((int) $state) / 100, 2)
                            : (string) $state),
                    TextEntry::make('issued_at')->dateTime(),
                    TextEntry::make('due_at')->dateTime(),
                    TextEntry::make('paid_at')->dateTime(),
                    TextEntry::make('hosted_invoice_url')
                        ->label('Hosted URL')
                        ->url(fn (?string $state): ?string => $state)
                        ->openUrlInNewTab()
                        ->visible(fn (?string $state): bool => ! empty($state)),
                    TextEntry::make('invoice_pdf')
                        ->label('PDF')
                        ->url(fn (?string $state): ?string => $state)
                        ->openUrlInNewTab()
                        ->visible(fn (?string $state): bool => ! empty($state)),
                    TextEntry::make('created_at')->dateTime(),
                ])
                ->columns(2),
            Section::make('Metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->formatStateUsing(fn ($state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('provider')
                    ->badge()
                    ->sortable(),
                TextColumn::make('number')
                    ->label('Invoice #')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'open', 'unpaid' => 'warning',
                        'void', 'voided', 'uncollectible', 'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('amount_due')
                    ->label('Amount due')
                    ->formatStateUsing(function ($state, Invoice $record): string {
                        $currency = strtoupper((string) $record->currency);
                        $formatted = number_format(((int) $state) / 100, 2);

                        return trim("{$currency} {$formatted}");
                    })
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('issued_at', 'desc')
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
