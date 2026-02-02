<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Price;
use App\Filament\Admin\Resources\PriceResource\Pages\EditPrice;
use App\Filament\Admin\Resources\PriceResource\Pages\ListPrices;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriceResource extends Resource
{
    protected static ?string $model = Price::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Product Management';

    protected static ?string $navigationLabel = 'Prices';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'provider_id';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('key')
                    ->label('Key')
                    ->readOnly()
                    ->maxLength(64)
                    ->helperText('Use keys like monthly, yearly, lifetime.'),
                TextInput::make('label')
                    ->maxLength(100),
                // Provider selection removed for agnostic pricing
                // Select::make('provider')
                //     ->options(self::providerOptions())
                //     ->required(),
                // TextInput::make('provider_id')
                //     ->label('Provider ID')
                //     ->helperText('Optional until you publish the catalog to a provider.')
                //     ->maxLength(191),
                TextInput::make('interval')
                    ->label('Interval')
                    ->maxLength(32)
                    ->required()
                    ->readOnly()
                    ->helperText('Example: month, year, lifetime.'),
                TextInput::make('interval_count')
                    ->label('Interval count')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->readOnly(),
                TextInput::make('currency')
                    ->label('Currency')
                    ->maxLength(3)
                    ->required()
                    ->readOnly()
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null),
                TextInput::make('amount')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->readOnly()
                    ->helperText('Stored in minor units (e.g. 2900 for €29.00).'),
                Select::make('type')
                    ->options([
                        'flat' => 'Flat',
                    ])
                    ->default('flat')
                    ->readOnly(),
                Toggle::make('has_trial')
                    ->label('Has trial')
                    ->readOnly(),
                TextInput::make('trial_interval')
                    ->label('Trial interval')
                    ->maxLength(32)
                    ->readOnly(),
                TextInput::make('trial_interval_count')
                    ->label('Trial interval count')
                    ->numeric()
                    ->minValue(1)
                    ->readOnly(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(false)
                    ->helperText('Controls local visibility only.'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('key')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('mappings.provider')
                    ->label('Providers')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stripe' => 'primary',
                        'paddle' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->toggleable(),
                TextColumn::make('interval')
                    ->sortable(),
                TextColumn::make('amount')
                    ->formatStateUsing(function ($state, Price $record): string {
                        $currency = strtoupper((string) $record->currency);
                        $formatted = number_format(((int) $state) / 100, 2);

                        return trim("{$currency} {$formatted}");
                    })
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->headerActions([
                // Sync moved to ProductResource - prices are synced together with products
            ])
            ->defaultSort('product_id')
            ->actions([
                EditAction::make(),
                // Delete removed: Prices are managed on provider side (Paddle/Stripe)
            ])
            ->bulkActions([
                // Bulk delete removed: Prices are managed on provider side
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrices::route('/'),
            // 'create' => CreatePrice::route('/create'),
            'edit' => EditPrice::route('/{record}/edit'),
        ];
    }

    private static function providerOptions(): array
    {
        $options = [];
        $providers = config('saas.billing.providers', ['stripe', 'paddle']);

        foreach ($providers as $provider) {
            $options[$provider] = ucfirst((string) $provider);
        }

        return $options;
    }
}
