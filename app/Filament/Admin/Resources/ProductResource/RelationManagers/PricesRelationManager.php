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

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->schema([
                \Filament\Forms\Components\TextInput::make('key')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                \Filament\Forms\Components\TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\Select::make('interval')
                    ->options([
                        'month' => 'Month',
                        'year' => 'Year',
                        'week' => 'Week',
                        'day' => 'Day',
                        'once' => 'One-time',
                    ])
                    ->required(),
                \Filament\Forms\Components\TextInput::make('interval_count')
                    ->numeric()
                    ->default(1)
                    ->required(),
                \Filament\Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->helperText('Amount in cents (e.g. 1000 = $10.00)'),
                \Filament\Forms\Components\TextInput::make('currency')
                    ->default('USD')
                    ->required()
                    ->maxLength(3),
                \Filament\Forms\Components\Toggle::make('has_trial')
                    ->label('Has Trial')
                    ->reactive(),
                \Filament\Forms\Components\TextInput::make('trial_interval_count')
                    ->label('Trial Duration')
                    ->numeric()
                    ->visible(fn ($get) => $get('has_trial')),
                \Filament\Forms\Components\Select::make('trial_interval')
                    ->options([
                        'day' => 'Day',
                        'month' => 'Month',
                    ])
                    ->visible(fn ($get) => $get('has_trial')),
                \Filament\Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

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
                \Filament\Actions\CreateAction::make(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for now
            ])
            ->emptyStateHeading('No prices')
            ->emptyStateDescription('Add a price to this product. It will be synced to providers.')
            ->emptyStateIcon('heroicon-o-tag');
    }
}
