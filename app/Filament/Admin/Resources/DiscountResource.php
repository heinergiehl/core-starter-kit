<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Discount;
use App\Filament\Admin\Resources\DiscountResource\Pages\CreateDiscount;
use App\Filament\Admin\Resources\DiscountResource\Pages\EditDiscount;
use App\Filament\Admin\Resources\DiscountResource\Pages\ListDiscounts;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-ticket';

    protected static string | \UnitEnum | null $navigationGroup = 'Product Management';

    protected static ?string $navigationLabel = 'Discounts';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('provider')
                    ->options(self::providerOptions())
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(50)
                    ->rules([
                        fn (Get $get, ?Discount $record) => Rule::unique('discounts', 'code')
                            ->where('provider', $get('provider'))
                            ->ignore($record?->id),
                    ]),
                Forms\Components\TextInput::make('name')
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Select::make('type')
                    ->options([
                        'percent' => 'Percent',
                        'fixed' => 'Fixed',
                    ])
                    ->required()
                    ->default('percent'),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->helperText('Percent: 1-100, Fixed: minor units (cents).'),
                Forms\Components\TextInput::make('currency')
                    ->maxLength(3)
                    ->required(fn (Get $get): bool => $get('type') === 'fixed')
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null),
                Forms\Components\Select::make('provider_type')
                    ->label('Provider type')
                    ->options([
                        'coupon' => 'Coupon',
                        'promotion_code' => 'Promotion code',
                    ])
                    ->default('coupon'),
                Forms\Components\TextInput::make('provider_id')
                    ->label('Provider ID')
                    ->maxLength(191)
                    ->disabled()
                    ->dehydrated()
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                Forms\Components\TextInput::make('max_redemptions')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Leave empty for unlimited redemptions.'),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Starts at'),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('Ends at'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Forms\Components\TagsInput::make('plan_keys')
                    ->label('Plan keys')
                    ->placeholder('starter, team'),
                Forms\Components\TagsInput::make('price_keys')
                    ->label('Price keys')
                    ->placeholder('monthly, yearly'),
                Forms\Components\TextInput::make('redeemed_count')
                    ->label('Redeemed count')
                    ->disabled()
                    ->dehydrated(false),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'fixed' ? 'warning' : 'primary'),
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(function ($state, Discount $record): string {
                        if ($record->type === 'percent') {
                            return "{$state}%";
                        }

                        $currency = strtoupper((string) $record->currency);
                        $formatted = number_format(((int) $state) / 100, 2);

                        return trim("{$currency} {$formatted}");
                    }),
                Tables\Columns\TextColumn::make('redeemed_count')
                    ->label('Used')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_redemptions')
                    ->label('Limit')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscounts::route('/'),
            'create' => CreateDiscount::route('/create'),
            'edit' => EditDiscount::route('/{record}/edit'),
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
