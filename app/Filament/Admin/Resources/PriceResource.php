<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Price;
use App\Filament\Admin\Resources\PriceResource\Pages\CreatePrice;
use App\Filament\Admin\Resources\PriceResource\Pages\EditPrice;
use App\Filament\Admin\Resources\PriceResource\Pages\ListPrices;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class PriceResource extends Resource
{
    protected static ?string $model = Price::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static string | \UnitEnum | null $navigationGroup = 'Product Management';

    protected static ?string $navigationLabel = 'Prices';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'provider_id';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('plan_id')
                    ->relationship('plan', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('key')
                    ->label('Key')
                    ->maxLength(64)
                    ->helperText('Use keys like monthly, yearly, lifetime.'),
                Forms\Components\TextInput::make('label')
                    ->maxLength(100),
                Forms\Components\Select::make('provider')
                    ->options(self::providerOptions())
                    ->required(),
                Forms\Components\TextInput::make('provider_id')
                    ->label('Provider ID')
                    ->helperText('Optional until you publish the catalog to a provider.')
                    ->maxLength(191),
                Forms\Components\TextInput::make('interval')
                    ->label('Interval')
                    ->maxLength(32)
                    ->required()
                    ->helperText('Example: month, year, lifetime.'),
                Forms\Components\TextInput::make('interval_count')
                    ->label('Interval count')
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
                Forms\Components\TextInput::make('currency')
                    ->label('Currency')
                    ->maxLength(3)
                    ->required()
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->helperText('Stored in minor units (e.g. 2900 for €29.00).'),
                Forms\Components\Select::make('type')
                    ->options([
                        'flat' => 'Flat',
                        'per_seat' => 'Per seat',
                    ])
                    ->default('flat'),
                Forms\Components\Toggle::make('has_trial')
                    ->label('Has trial'),
                Forms\Components\TextInput::make('trial_interval')
                    ->label('Trial interval')
                    ->maxLength(32),
                Forms\Components\TextInput::make('trial_interval_count')
                    ->label('Trial interval count')
                    ->numeric()
                    ->minValue(1),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('key')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'stripe' => 'primary',
                        'lemonsqueezy' => 'warning',
                        'paddle' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('interval')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(function ($state, Price $record): string {
                        $currency = strtoupper((string) $record->currency);
                        $formatted = number_format(((int) $state) / 100, 2);

                        return trim("{$currency} {$formatted}");
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('sync')
                    ->label('Sync from Providers')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function () {
                        Artisan::call('billing:sync-products');
                        Notification::make()
                            ->title('Sync complete')
                            ->body('Products and prices have been synced from all providers.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('plan_id')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrices::route('/'),
            'create' => CreatePrice::route('/create'),
            'edit' => EditPrice::route('/{record}/edit'),
        ];
    }

    private static function providerOptions(): array
    {
        $options = [];
        $providers = config('saas.billing.providers', ['stripe', 'paddle', 'lemonsqueezy']);

        foreach ($providers as $provider) {
            $options[$provider] = ucfirst((string) $provider);
        }

        return $options;
    }
}
