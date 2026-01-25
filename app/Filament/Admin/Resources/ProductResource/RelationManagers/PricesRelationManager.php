<?php

namespace App\Filament\Admin\Resources\ProductResource\RelationManagers;

use App\Domain\Billing\Models\Price;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Prices';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-tag';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Key')
                    ->badge()
                    ->sortable(),
                TextColumn::make('interval')
                    ->label('Interval')
                    ->sortable(),
                TextColumn::make('interval_count')
                    ->label('Count')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(function ($state, Price $record): string {
                        $currency = strtoupper((string) $record->currency);
                        $formatted = number_format(((int) $state) / 100, 2);

                        return trim("{$currency} {$formatted}");
                    })
                    ->sortable(),
                IconColumn::make('has_trial')
                    ->label('Trial')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('interval')
            ->paginated(false)
            ->headerActions([
                // No create action - prices are managed on provider side
            ])
            ->actions([
                // No edit/delete actions - prices are managed on provider side
            ])
            ->bulkActions([
                // No bulk actions - prices are managed on provider side
            ])
            ->emptyStateHeading('No prices')
            ->emptyStateDescription('This product has no prices. Prices are synced from your billing provider.')
            ->emptyStateIcon('heroicon-o-tag');
    }
}
