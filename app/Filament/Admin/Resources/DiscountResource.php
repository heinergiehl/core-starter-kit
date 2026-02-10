<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\PaymentProvider;
use App\Filament\Admin\Resources\DiscountResource\Pages\CreateDiscount;
use App\Filament\Admin\Resources\DiscountResource\Pages\EditDiscount;
use App\Filament\Admin\Resources\DiscountResource\Pages\ListDiscounts;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Product Management';

    protected static ?string $navigationLabel = 'Discounts';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('provider')
                    ->options(self::providerOptions())
                    ->required()
                    ->live(),
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(50)
                    ->rules([
                        fn (Get $get, ?Discount $record) => Rule::unique('discounts', 'code')
                            ->where('provider', $get('provider'))
                            ->ignore($record?->id),
                    ]),
                TextInput::make('name')
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('type')
                    ->options([
                        'percent' => 'Percent',
                        'fixed' => 'Fixed',
                    ])
                    ->required()
                    ->default('percent'),
                TextInput::make('amount')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->helperText('Percent: 1-100, Fixed: minor units (cents).'),
                TextInput::make('currency')
                    ->maxLength(3)
                    ->required(fn (Get $get): bool => $get('type') === 'fixed')
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null),
                Select::make('provider_type')
                    ->label('Provider type')
                    ->options([
                        'coupon' => 'Coupon',
                        'promotion_code' => 'Promotion code',
                    ])
                    ->default('coupon'),
                TextInput::make('provider_id')
                    ->label('Provider ID')
                    ->maxLength(191)
                    ->disabled()
                    ->dehydrated()
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                TextInput::make('max_redemptions')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Leave empty for unlimited redemptions.'),
                DateTimePicker::make('starts_at')
                    ->label('Starts at'),
                DateTimePicker::make('ends_at')
                    ->label('Ends at'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                TagsInput::make('plan_keys')
                    ->label('Plan keys')
                    ->placeholder('starter, growth'),
                TagsInput::make('price_keys')
                    ->label('Price keys')
                    ->placeholder('monthly, yearly'),
                TextInput::make('redeemed_count')
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
                TextColumn::make('code')
                    ->badge()
                    ->searchable(),
                TextColumn::make('provider')
                    ->badge()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'fixed' ? 'warning' : 'primary'),
                TextColumn::make('amount')
                    ->formatStateUsing(function ($state, Discount $record): string {
                        if ($record->type === 'percent') {
                            return "{$state}%";
                        }

                        $currency = strtoupper((string) $record->currency);
                        $formatted = number_format(((int) $state) / 100, 2);

                        return trim("{$currency} {$formatted}");
                    }),
                TextColumn::make('redeemed_count')
                    ->label('Used')
                    ->sortable(),
                TextColumn::make('max_redemptions')
                    ->label('Limit')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('ends_at')
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
        $providers = [];

        if (DatabaseSchema::hasTable('payment_providers')) {
            $providers = PaymentProvider::query()
                ->where('is_active', true)
                ->pluck('slug')
                ->map(fn (string $slug): string => strtolower($slug))
                ->all();
        }

        if (empty($providers)) {
            $providers = config('saas.billing.providers', ['stripe', 'paddle']);
        }

        foreach ($providers as $provider) {
            $options[$provider] = ucfirst((string) $provider);
        }

        return $options;
    }
}
