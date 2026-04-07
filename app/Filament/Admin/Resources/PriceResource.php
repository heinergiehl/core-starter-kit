<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Price;
use App\Enums\PriceType;
use App\Enums\UsageLimitBehavior;
use App\Filament\Admin\Resources\Concerns\InteractsWithMoneyFields;
use App\Filament\Admin\Resources\Concerns\InteractsWithPricingModes;
use App\Filament\Admin\Resources\PriceResource\Pages\EditPrice;
use App\Filament\Admin\Resources\PriceResource\Pages\ListPrices;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriceResource extends Resource
{
    use InteractsWithMoneyFields;
    use InteractsWithPricingModes;

    protected static ?string $model = Price::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Product Management';

    protected static ?string $navigationLabel = 'Prices';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Price Identity')
                    ->description('Core price details synced from your payment provider. Read-only fields are managed by the provider.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('product_id')
                                ->relationship('product', 'name')
                                ->required()
                                ->searchable()
                                ->preload(),
                            TextInput::make('key')
                                ->label('Key')
                                ->readOnly()
                                ->maxLength(64)
                                ->helperText('Unique slug, e.g. monthly, yearly, lifetime.'),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('label')
                                ->maxLength(100)
                                ->placeholder('e.g. Monthly, Yearly')
                                ->helperText('Customer-facing label shown on the pricing card.'),
                            Select::make('type')
                                ->options(PriceType::class)
                                ->disabled()
                                ->helperText('Set automatically based on the pricing mode.'),
                        ]),
                    ])->columns(2),

                Section::make('Billing & Amount')
                    ->description('Pricing configuration synced from the payment provider.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('amount')
                                ->label('Price')
                                ->numeric()
                                ->step(fn (Get $get): string => \App\Support\Money\CurrencyAmount::inputStep($get('currency')))
                                ->minValue(0)
                                ->required()
                                ->readOnly()
                                ->suffix(fn (Get $get): string => self::moneyCurrencyCode($get('currency')))
                                ->formatStateUsing(fn ($state, Get $get): ?string => self::formatMinorAmountForInput($state, $get('currency')))
                                ->dehydrateStateUsing(fn ($state, Get $get): ?int => self::parseMoneyInputToMinor($state, $get('currency')))
                                ->helperText(fn (Get $get): string => $get('pricing_mode') === 'usage_based'
                                    ? 'Recurring base fee before usage charges.'
                                    : 'Enter in normal currency units (e.g. 29.99).'),
                            TextInput::make('currency')
                                ->label('Currency')
                                ->maxLength(3)
                                ->required()
                                ->readOnly()
                                ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null)
                                ->helperText('ISO 4217 code.'),
                            TextInput::make('interval')
                                ->label('Interval')
                                ->maxLength(32)
                                ->required()
                                ->readOnly()
                                ->dehydrateStateUsing(function ($state, Get $get, ?Price $record): string {
                                    $pricingMode = (string) ($get('pricing_mode') ?: self::resolvePricingMode(
                                        $record?->interval,
                                        (bool) $record?->allow_custom_amount,
                                        (bool) $record?->is_metered,
                                        $record?->product?->type
                                    ));

                                    return self::isRecurringPricingMode($pricingMode)
                                        ? ((string) ($state ?: $record?->interval ?: 'month'))
                                        : 'once';
                                })
                                ->helperText('Billing cadence synced from provider.'),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('interval_count')
                                ->label('Interval count')
                                ->numeric()
                                ->minValue(1)
                                ->default(1)
                                ->readOnly()
                                ->helperText('e.g. 3 = quarterly, 6 = semi-annual.'),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(false)
                                ->helperText('Controls local visibility only — does not affect the provider.'),
                        ]),
                    ])->columns(2),

                Section::make('Pricing Mode')
                    ->description('Switch between flat billing and usage-based or pay-what-you-want modes.')
                    ->schema([
                        ToggleButtons::make('pricing_mode')
                            ->label('Pricing mode')
                            ->options(fn (?Price $record): array => self::pricingModeOptionsForInterval($record?->interval))
                            ->inline()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (?string $state, ?Price $record, Set $set): void {
                                $resolvedPricingMode = self::resolvePricingMode(
                                    $record?->interval,
                                    (bool) $record?->allow_custom_amount,
                                    (bool) $record?->is_metered,
                                    $record?->product?->type
                                );

                                if ($state !== $resolvedPricingMode) {
                                    $set('pricing_mode', $resolvedPricingMode);
                                }

                                self::fillPriceState($set, self::synchronizePriceStateForMode(
                                    [
                                        'label' => $record?->label,
                                        'interval' => $record?->interval,
                                        'interval_count' => $record?->interval_count,
                                        'type' => $record?->type?->value ?? $record?->type,
                                        'has_trial' => $record?->has_trial,
                                        'trial_interval' => $record?->trial_interval,
                                        'trial_interval_count' => $record?->trial_interval_count,
                                        'allow_custom_amount' => $record?->allow_custom_amount,
                                        'is_metered' => $record?->is_metered,
                                        'usage_meter_name' => $record?->usage_meter_name,
                                        'usage_meter_key' => $record?->usage_meter_key,
                                        'usage_unit_label' => $record?->usage_unit_label,
                                        'usage_included_units' => $record?->usage_included_units,
                                        'usage_package_size' => $record?->usage_package_size,
                                        'usage_overage_amount' => $record?->usage_overage_amount,
                                        'usage_rounding_mode' => $record?->usage_rounding_mode,
                                        'usage_limit_behavior' => $record?->usage_limit_behavior?->value ?? $record?->usage_limit_behavior,
                                        'custom_amount_default' => $record?->custom_amount_default,
                                        'custom_amount_minimum' => $record?->custom_amount_minimum,
                                        'custom_amount_maximum' => $record?->custom_amount_maximum,
                                        'suggested_amounts' => $record?->suggested_amounts,
                                        'amount' => $record?->amount,
                                    ],
                                    $resolvedPricingMode
                                ));
                            })
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                self::applyPricingMode($get, $set, $state);
                            }),
                        Placeholder::make('pricing_mode_summary')
                            ->label('What this mode does')
                            ->content(fn (Get $get, ?Price $record): string => self::pricingModeSummary(
                                (string) ($get('pricing_mode') ?: self::resolvePricingMode(
                                    $record?->interval,
                                    (bool) $record?->allow_custom_amount,
                                    (bool) $record?->is_metered,
                                    $record?->product?->type
                                ))
                            )),
                        Grid::make(3)->schema([
                            Toggle::make('has_trial')
                                ->label('Has trial')
                                ->disabled()
                                ->visible(fn (Get $get, ?Price $record): bool => filled($record) && self::isRecurringPricingMode(
                                    (string) ($get('pricing_mode') ?: self::resolvePricingMode(
                                        $record?->interval,
                                        (bool) $record?->allow_custom_amount,
                                        (bool) $record?->is_metered,
                                        $record?->product?->type
                                    ))
                                )),
                            TextInput::make('trial_interval')
                                ->label('Trial interval')
                                ->maxLength(32)
                                ->readOnly()
                                ->visible(fn (Get $get, ?Price $record): bool => filled($record) && self::isRecurringPricingMode(
                                    (string) ($get('pricing_mode') ?: self::resolvePricingMode(
                                        $record?->interval,
                                        (bool) $record?->allow_custom_amount,
                                        (bool) $record?->is_metered,
                                        $record?->product?->type
                                    ))
                                )),
                            TextInput::make('trial_interval_count')
                                ->label('Trial days')
                                ->numeric()
                                ->minValue(1)
                                ->readOnly()
                                ->visible(fn (Get $get, ?Price $record): bool => filled($record) && self::isRecurringPricingMode(
                                    (string) ($get('pricing_mode') ?: self::resolvePricingMode(
                                        $record?->interval,
                                        (bool) $record?->allow_custom_amount,
                                        (bool) $record?->is_metered,
                                        $record?->product?->type
                                    ))
                                )),
                        ]),
                        Hidden::make('is_metered')
                            ->dehydrateStateUsing(fn ($state, Get $get): bool => $get('pricing_mode') === 'usage_based'),
                    ]),
                Section::make('Usage-based billing')
                    ->description('Configure metering: define the usage unit, included allowance, and what happens when usage exceeds the limit.')
                    ->schema([
                        Placeholder::make('usage_billing_summary')
                            ->label('How this is shown')
                            ->content(fn (Get $get): string => self::usageLimitBehaviorSummary($get('usage_limit_behavior'))),
                        Grid::make(2)->schema([
                            TextInput::make('usage_meter_name')
                                ->label('Meter name')
                                ->required(fn (Get $get): bool => $get('pricing_mode') === 'usage_based')
                                ->maxLength(255)
                                ->placeholder('API requests')
                                ->helperText('Human-readable label shown on pricing and billing pages.'),
                            TextInput::make('usage_meter_key')
                                ->label('Meter key')
                                ->required(fn (Get $get): bool => $get('pricing_mode') === 'usage_based')
                                ->maxLength(255)
                                ->placeholder('api_requests')
                                ->helperText('Stable identifier used when your app records usage.'),
                            TextInput::make('usage_unit_label')
                                ->label('Usage unit label')
                                ->required(fn (Get $get): bool => $get('pricing_mode') === 'usage_based')
                                ->maxLength(80)
                                ->placeholder('request')
                                ->helperText('Use the singular form, for example request, seat, or GB.'),
                            TextInput::make('usage_included_units')
                                ->label('Included units')
                                ->numeric()
                                ->minValue(0)
                                ->placeholder('10000')
                                ->rules([self::usageIncludedUnitsRule()])
                                ->helperText(fn (Get $get): string => self::resolveUsageLimitBehavior($get('usage_limit_behavior'))->blocksUsage()
                                    ? 'Required when this plan blocks usage at the included limit.'
                                    : 'Leave empty if this price is pure pay-as-you-go.'),
                            Select::make('usage_limit_behavior')
                                ->label('Usage policy')
                                ->options(self::usageLimitBehaviorOptions())
                                ->default(UsageLimitBehavior::BillOverage->value)
                                ->required(fn (Get $get): bool => $get('pricing_mode') === 'usage_based')
                                ->native(false)
                                ->live()
                                ->helperText('Choose whether usage past the included amount is billed or blocked.'),
                            TextInput::make('usage_package_size')
                                ->label('Package size')
                                ->numeric()
                                ->required(fn (Get $get): bool => $get('pricing_mode') === 'usage_based'
                                    && self::resolveUsageLimitBehavior($get('usage_limit_behavior'))->allowsOverageBilling())
                                ->default(1)
                                ->minValue(1)
                                ->helperText('Bill overages per package of units, for example 1000 for "per 1,000 requests".')
                                ->visible(fn (Get $get): bool => $get('pricing_mode') === 'usage_based'
                                    && self::resolveUsageLimitBehavior($get('usage_limit_behavior'))->allowsOverageBilling()),
                            TextInput::make('usage_overage_amount')
                                ->label('Overage price')
                                ->numeric()
                                ->required(fn (Get $get): bool => $get('pricing_mode') === 'usage_based'
                                    && self::resolveUsageLimitBehavior($get('usage_limit_behavior'))->allowsOverageBilling())
                                ->step(fn (Get $get): string => \App\Support\Money\CurrencyAmount::inputStep($get('currency')))
                                ->minValue(0)
                                ->suffix(fn (Get $get): string => self::moneyCurrencyCode($get('currency')))
                                ->formatStateUsing(fn ($state, Get $get): ?string => self::formatMinorAmountForInput($state, $get('currency')))
                                ->dehydrateStateUsing(fn ($state, Get $get, ?Price $record): ?int => ($get('pricing_mode') ?: self::resolvePricingMode(
                                    $record?->interval,
                                    (bool) $record?->allow_custom_amount,
                                    (bool) $record?->is_metered,
                                    $record?->product?->type
                                )) === 'usage_based'
                                    ? self::parseMoneyInputToMinor($state, $get('currency'))
                                    : null)
                                ->helperText('The charge for each package of overage units.')
                                ->visible(fn (Get $get): bool => $get('pricing_mode') === 'usage_based'
                                    && self::resolveUsageLimitBehavior($get('usage_limit_behavior'))->allowsOverageBilling()),
                            Select::make('usage_rounding_mode')
                                ->label('Package rounding')
                                ->options([
                                    'up' => 'Round up',
                                    'down' => 'Round down',
                                ])
                                ->default('up')
                                ->required(fn (Get $get): bool => $get('pricing_mode') === 'usage_based'
                                    && self::resolveUsageLimitBehavior($get('usage_limit_behavior'))->allowsOverageBilling())
                                ->helperText('Round overages up for billing-friendly estimates, or down for stricter included usage.')
                                ->visible(fn (Get $get): bool => $get('pricing_mode') === 'usage_based'
                                    && self::resolveUsageLimitBehavior($get('usage_limit_behavior'))->allowsOverageBilling()),
                        ]),
                    ])
                    ->columns(1)
                    ->visible(fn (Get $get, ?Price $record): bool => filled($record) && (
                        (string) ($get('pricing_mode') ?: self::resolvePricingMode(
                            $record?->interval,
                            (bool) $record?->allow_custom_amount,
                            (bool) $record?->is_metered,
                            $record?->product?->type
                        ))
                    ) === 'usage_based'),
                Section::make('Flexible Pricing')
                    ->description('Configure the pay-what-you-want experience: set a suggested default, optional min/max bounds, and quick-pick amounts.')
                    ->schema([
                        TextInput::make('custom_amount_default')
                            ->label('Default amount')
                            ->numeric()
                            ->step(fn (Get $get): string => \App\Support\Money\CurrencyAmount::inputStep($get('currency')))
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::moneyCurrencyCode($get('currency')))
                            ->formatStateUsing(fn ($state, Get $get): ?string => self::formatMinorAmountForInput($state, $get('currency')))
                            ->dehydrateStateUsing(fn ($state, Get $get, ?Price $record): ?int => ($get('pricing_mode') ?: self::resolvePricingMode(
                                $record?->interval,
                                (bool) $record?->allow_custom_amount,
                                (bool) $record?->is_metered,
                                $record?->product?->type
                            )) === 'one_time_pwyw'
                                ? self::parseMoneyInputToMinor($state, $get('currency'))
                                : null)
                            ->rules([self::customAmountRangeRule('custom_amount_default')])
                            ->visible(fn (Get $get, ?Price $record): bool => filled($record) && ($get('pricing_mode') ?: self::resolvePricingMode(
                                $record?->interval,
                                (bool) $record?->allow_custom_amount,
                                (bool) $record?->is_metered,
                                $record?->product?->type
                            )) === 'one_time_pwyw'),
                        TextInput::make('custom_amount_minimum')
                            ->label('Minimum amount')
                            ->numeric()
                            ->step(fn (Get $get): string => \App\Support\Money\CurrencyAmount::inputStep($get('currency')))
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::moneyCurrencyCode($get('currency')))
                            ->formatStateUsing(fn ($state, Get $get): ?string => self::formatMinorAmountForInput($state, $get('currency')))
                            ->dehydrateStateUsing(fn ($state, Get $get, ?Price $record): ?int => ($get('pricing_mode') ?: self::resolvePricingMode(
                                $record?->interval,
                                (bool) $record?->allow_custom_amount,
                                (bool) $record?->is_metered,
                                $record?->product?->type
                            )) === 'one_time_pwyw'
                                ? self::parseMoneyInputToMinor($state, $get('currency'))
                                : null)
                            ->rules([self::customAmountRangeRule('custom_amount_minimum')])
                            ->visible(fn (Get $get, ?Price $record): bool => filled($record) && ($get('pricing_mode') ?: self::resolvePricingMode(
                                $record?->interval,
                                (bool) $record?->allow_custom_amount,
                                (bool) $record?->is_metered,
                                $record?->product?->type
                            )) === 'one_time_pwyw'),
                        TextInput::make('custom_amount_maximum')
                            ->label('Maximum amount')
                            ->numeric()
                            ->step(fn (Get $get): string => \App\Support\Money\CurrencyAmount::inputStep($get('currency')))
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => self::moneyCurrencyCode($get('currency')))
                            ->formatStateUsing(fn ($state, Get $get): ?string => self::formatMinorAmountForInput($state, $get('currency')))
                            ->dehydrateStateUsing(fn ($state, Get $get, ?Price $record): ?int => ($get('pricing_mode') ?: self::resolvePricingMode(
                                $record?->interval,
                                (bool) $record?->allow_custom_amount,
                                (bool) $record?->is_metered,
                                $record?->product?->type
                            )) === 'one_time_pwyw'
                                ? self::parseMoneyInputToMinor($state, $get('currency'))
                                : null)
                            ->rules([self::customAmountRangeRule('custom_amount_maximum')])
                            ->visible(fn (Get $get, ?Price $record): bool => filled($record) && ($get('pricing_mode') ?: self::resolvePricingMode(
                                $record?->interval,
                                (bool) $record?->allow_custom_amount,
                                (bool) $record?->is_metered,
                                $record?->product?->type
                            )) === 'one_time_pwyw'),
                        Textarea::make('suggested_amounts')
                            ->label('Suggested amounts')
                            ->rows(3)
                            ->visible(fn (Get $get, ?Price $record): bool => filled($record) && ($get('pricing_mode') ?: self::resolvePricingMode(
                                $record?->interval,
                                (bool) $record?->allow_custom_amount,
                                (bool) $record?->is_metered,
                                $record?->product?->type
                            )) === 'one_time_pwyw')
                            ->helperText('One currency amount per line, for example 5.00, 15.00, 30.00.')
                            ->formatStateUsing(function ($state, Get $get): string {
                                if (is_array($state)) {
                                    return implode("\n", array_map(
                                        fn ($amount): string => self::formatMinorAmountForInput($amount, $get('currency')) ?? '',
                                        $state
                                    ));
                                }

                                return (string) $state;
                            })
                            ->dehydrateStateUsing(fn ($state, Get $get, ?Price $record): ?array => ($get('pricing_mode') ?: self::resolvePricingMode(
                                $record?->interval,
                                (bool) $record?->allow_custom_amount,
                                (bool) $record?->is_metered,
                                $record?->product?->type
                            )) === 'one_time_pwyw'
                                ? self::parseMoneyLinesToMinor($state, $get('currency'))
                                : null)
                            ->rules([self::suggestedAmountsRule()]),
                        Hidden::make('allow_custom_amount')
                            ->dehydrateStateUsing(fn ($state, Get $get, ?Price $record): bool => ($get('pricing_mode') ?: self::resolvePricingMode(
                                $record?->interval,
                                (bool) $record?->allow_custom_amount,
                                (bool) $record?->is_metered,
                                $record?->product?->type
                            )) === 'one_time_pwyw'),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get, ?Price $record): bool => filled($record) && in_array(
                        (string) ($get('pricing_mode') ?: self::resolvePricingMode(
                            $record?->interval,
                            (bool) $record?->allow_custom_amount,
                            (bool) $record?->is_metered,
                            $record?->product?->type
                        )),
                        ['one_time_fixed', 'one_time_pwyw'],
                        true
                    )),
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
                TextColumn::make('allow_custom_amount')
                    ->label('Mode')
                    ->badge()
                    ->getStateUsing(fn (Price $record): string => self::pricingModeLabelForRecord($record->interval, $record->allow_custom_amount, $record->is_metered))
                    ->color(fn (string $state): string => match ($state) {
                        'Pay what you want' => 'success',
                        'One-time' => 'warning',
                        'Usage-based' => 'info',
                        default => 'primary',
                    }),
                TextColumn::make('amount')
                    ->formatStateUsing(function ($state, Price $record): string {
                        if ($record->allow_custom_amount) {
                            $defaultAmount = $record->custom_amount_default ?? (int) $state;

                            return trim(__('Pay what you want from :amount', [
                                'amount' => self::formatMinorAmountForPreview($defaultAmount, $record->currency),
                            ]));
                        }

                        if ($record->is_metered) {
                            $usageBehavior = $record->usage_limit_behavior instanceof UsageLimitBehavior
                                ? $record->usage_limit_behavior
                                : UsageLimitBehavior::tryFrom((string) $record->usage_limit_behavior) ?? UsageLimitBehavior::BillOverage;
                            $baseAmount = self::formatMinorAmountForPreview($state, $record->currency);
                            $includedUnits = max((int) ($record->usage_included_units ?? 0), 0);
                            $unitLabel = (string) ($record->usage_unit_label ?? 'unit');

                            if ($usageBehavior->blocksUsage()) {
                                return trim(__('Base :base with :included included :unit cap', [
                                    'base' => $baseAmount,
                                    'included' => number_format($includedUnits),
                                    'unit' => \Illuminate\Support\Str::plural($unitLabel, $includedUnits),
                                ]));
                            }

                            $overageAmount = self::formatMinorAmountForPreview($record->usage_overage_amount, $record->currency);
                            $packageSize = max((int) ($record->usage_package_size ?? 1), 1);

                            return trim(__('Base :base + :overage / :package :unit', [
                                'base' => $baseAmount,
                                'overage' => $overageAmount,
                                'package' => number_format($packageSize),
                                'unit' => $unitLabel,
                            ]));
                        }

                        return self::formatMinorAmountForPreview($state, $record->currency);
                    })
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->headerActions([
                // Sync moved to ProductResource because prices are synced together with products.
            ])
            ->defaultSort('product_id')
            ->actions([
                EditAction::make(),
                // Delete is intentionally disabled until provider-side archival is modeled.
            ])
            ->bulkActions([
                // Bulk delete is intentionally disabled until provider-side archival is modeled.
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
