<?php

namespace App\Filament\Admin\Resources\ProductResource\RelationManagers;

use App\Domain\Billing\Models\Price;
use App\Enums\PriceType;
use App\Enums\UsageLimitBehavior;
use App\Filament\Admin\Resources\Concerns\InteractsWithMoneyFields;
use App\Filament\Admin\Resources\Concerns\InteractsWithPricingModes;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PricesRelationManager extends RelationManager
{
    use InteractsWithMoneyFields;
    use InteractsWithPricingModes;

    protected static string $relationship = 'prices';

    protected static ?string $title = 'Prices';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-tag';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Price Identity')
                    ->compact()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('key')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. monthly, yearly, lifetime')
                                ->helperText('Unique slug used in API calls and configuration.'),
                            TextInput::make('label')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. Monthly, Yearly, Lifetime')
                                ->helperText('Customer-facing label shown on the pricing card.'),
                        ]),
                        ToggleButtons::make('pricing_mode')
                            ->label('Pricing mode')
                            ->options(fn (): array => self::pricingModeOptionsForProductType($this->getOwnerRecord()?->type))
                            ->default(fn (): string => self::defaultPricingModeForProductType($this->getOwnerRecord()?->type))
                            ->inline()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (?string $state, Get $get, Set $set): void {
                                $resolvedPricingMode = self::resolvePricingMode(
                                    $get('interval'),
                                    (bool) $get('allow_custom_amount'),
                                    (bool) $get('is_metered'),
                                    $this->getOwnerRecord()?->type
                                );

                                if ($state !== $resolvedPricingMode) {
                                    $set('pricing_mode', $resolvedPricingMode);
                                }

                                self::applyPricingMode($get, $set, $resolvedPricingMode);
                            })
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                self::applyPricingMode($get, $set, $state);
                            }),
                        Placeholder::make('pricing_mode_summary')
                            ->label('What this mode does')
                            ->content(fn (Get $get): string => self::pricingModeSummary(
                                (string) ($get('pricing_mode') ?: self::defaultPricingModeForProductType($this->getOwnerRecord()?->type))
                            )),
                    ]),

                Section::make('Pricing')
                    ->compact()
                    ->schema([
                        Select::make('interval')
                            ->label('Billing Frequency')
                            ->options([
                                'month' => 'Monthly',
                                'year' => 'Yearly',
                                'week' => 'Weekly',
                                'day' => 'Daily',
                                'once' => 'One-time',
                            ])
                            ->required()
                            ->visible(fn (Get $get): bool => self::isRecurringPricingMode($get('pricing_mode')))
                            ->live()
                            ->dehydrateStateUsing(fn ($state, Get $get): string => self::isRecurringPricingMode($get('pricing_mode'))
                                ? (in_array($state, ['month', 'year', 'week', 'day'], true) ? (string) $state : 'month')
                                : 'once')
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                $priceState = self::collectPriceState($get);
                                $priceState['interval'] = $state;

                                self::fillPriceState($set, self::synchronizePriceStateForMode(
                                    $priceState,
                                    self::isRecurringPricingMode($get('pricing_mode')) ? (string) $get('pricing_mode') : 'subscription'
                                ));
                            }),
                        Grid::make(3)->schema([
                            TextInput::make('amount')
                                ->label('Price')
                                ->numeric()
                                ->step(fn (Get $get): string => \App\Support\Money\CurrencyAmount::inputStep($get('currency')))
                                ->required()
                                ->suffix(fn (Get $get): string => self::moneyCurrencyCode($get('currency')))
                                ->formatStateUsing(fn ($state, Get $get): ?string => self::formatMinorAmountForInput($state, $get('currency')))
                                ->dehydrateStateUsing(fn ($state, Get $get): ?int => self::parseMoneyInputToMinor($state, $get('currency')))
                                ->placeholder('e.g. 29.99')
                                ->helperText(fn (Get $get): string => $get('pricing_mode') === 'usage_based'
                                    ? 'Recurring base fee charged each billing cycle.'
                                    : ($get('allow_custom_amount')
                                        ? 'Default checkout amount. Customers can override this.'
                                        : 'Enter in normal currency units (e.g. 29.99).')),
                            TextInput::make('currency')
                                ->default('USD')
                                ->required()
                                ->maxLength(3)
                                ->placeholder('USD')
                                ->helperText('ISO 4217 code.'),
                            TextInput::make('interval_count')
                                ->label('Interval Count')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->visible(fn (Get $get): bool => self::isRecurringPricingMode($get('pricing_mode')))
                                ->dehydrateStateUsing(fn ($state, Get $get): int => self::isRecurringPricingMode($get('pricing_mode'))
                                    ? max(1, (int) $state)
                                    : 1)
                                ->helperText('Set to 3 for quarterly, 6 for semi-annual.'),
                        ]),
                    ]),

                Section::make('Free Trial')
                    ->compact()
                    ->collapsed()
                    ->visible(fn (Get $get): bool => self::isRecurringPricingMode($get('pricing_mode')))
                    ->description('Allow new subscribers to try this plan for free before being charged.')
                    ->schema([
                        Toggle::make('has_trial')
                            ->label('Offer Free Trial')
                            ->live()
                            ->dehydrateStateUsing(fn ($state, Get $get): bool => self::isRecurringPricingMode($get('pricing_mode'))
                                ? (bool) $state
                                : false),
                        Grid::make(2)->schema([
                            TextInput::make('trial_interval_count')
                                ->label('Trial Duration')
                                ->numeric()
                                ->visible(fn (Get $get): bool => (bool) $get('has_trial'))
                                ->dehydrateStateUsing(fn ($state, Get $get): ?int => self::isRecurringPricingMode($get('pricing_mode')) && (bool) $get('has_trial')
                                    ? (int) $state
                                    : null),
                            Select::make('trial_interval')
                                ->options([
                                    'day' => 'Day',
                                    'month' => 'Month',
                                ])
                                ->visible(fn (Get $get): bool => (bool) $get('has_trial'))
                                ->dehydrateStateUsing(fn ($state, Get $get): ?string => self::isRecurringPricingMode($get('pricing_mode')) && (bool) $get('has_trial')
                                    ? (string) $state
                                    : null),
                        ]),
                    ]),
                Section::make('Usage-based billing')
                    ->compact()
                    ->collapsed()
                    ->description('Configure metering: define the usage unit, included allowance, and what happens when usage exceeds the limit.')
                    ->visible(fn (Get $get): bool => $get('pricing_mode') === 'usage_based')
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
                                ->dehydrateStateUsing(fn ($state, Get $get): ?int => $get('pricing_mode') === 'usage_based'
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
                    ]),
                Section::make('Flexible Pricing')
                    ->compact()
                    ->collapsed()
                    ->description('Configure the pay-what-you-want experience: set a suggested default, optional min/max bounds, and quick-pick amounts.')
                    ->visible(fn (Get $get): bool => $get('pricing_mode') === 'one_time_pwyw')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('custom_amount_default')
                                ->label('Default amount')
                                ->numeric()
                                ->step(fn (Get $get): string => \App\Support\Money\CurrencyAmount::inputStep($get('currency')))
                                ->minValue(0)
                                ->suffix(fn (Get $get): string => self::moneyCurrencyCode($get('currency')))
                                ->formatStateUsing(fn ($state, Get $get): ?string => self::formatMinorAmountForInput($state, $get('currency')))
                                ->dehydrateStateUsing(fn ($state, Get $get): ?int => $get('pricing_mode') === 'one_time_pwyw'
                                    ? self::parseMoneyInputToMinor($state, $get('currency'))
                                    : null)
                                ->rules([self::customAmountRangeRule('custom_amount_default')])
                                ->visible(fn (Get $get): bool => $get('pricing_mode') === 'one_time_pwyw'),
                            TextInput::make('custom_amount_minimum')
                                ->label('Minimum amount')
                                ->numeric()
                                ->step(fn (Get $get): string => \App\Support\Money\CurrencyAmount::inputStep($get('currency')))
                                ->minValue(0)
                                ->suffix(fn (Get $get): string => self::moneyCurrencyCode($get('currency')))
                                ->formatStateUsing(fn ($state, Get $get): ?string => self::formatMinorAmountForInput($state, $get('currency')))
                                ->dehydrateStateUsing(fn ($state, Get $get): ?int => $get('pricing_mode') === 'one_time_pwyw'
                                    ? self::parseMoneyInputToMinor($state, $get('currency'))
                                    : null)
                                ->rules([self::customAmountRangeRule('custom_amount_minimum')])
                                ->visible(fn (Get $get): bool => $get('pricing_mode') === 'one_time_pwyw'),
                            TextInput::make('custom_amount_maximum')
                                ->label('Maximum amount')
                                ->numeric()
                                ->step(fn (Get $get): string => \App\Support\Money\CurrencyAmount::inputStep($get('currency')))
                                ->minValue(0)
                                ->suffix(fn (Get $get): string => self::moneyCurrencyCode($get('currency')))
                                ->formatStateUsing(fn ($state, Get $get): ?string => self::formatMinorAmountForInput($state, $get('currency')))
                                ->dehydrateStateUsing(fn ($state, Get $get): ?int => $get('pricing_mode') === 'one_time_pwyw'
                                    ? self::parseMoneyInputToMinor($state, $get('currency'))
                                    : null)
                                ->rules([self::customAmountRangeRule('custom_amount_maximum')])
                                ->visible(fn (Get $get): bool => $get('pricing_mode') === 'one_time_pwyw'),
                        ]),
                        Textarea::make('suggested_amounts')
                            ->label('Suggested amounts')
                            ->rows(3)
                            ->visible(fn (Get $get): bool => $get('pricing_mode') === 'one_time_pwyw')
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
                            ->dehydrateStateUsing(fn ($state, Get $get): ?array => $get('pricing_mode') === 'one_time_pwyw'
                                ? self::parseMoneyLinesToMinor($state, $get('currency'))
                                : null)
                            ->rules([self::suggestedAmountsRule()]),
                    ]),
                Hidden::make('allow_custom_amount')
                    ->dehydrateStateUsing(fn ($state, Get $get): bool => $get('pricing_mode') === 'one_time_pwyw'),
                Hidden::make('is_metered')
                    ->dehydrateStateUsing(fn ($state, Get $get): bool => $get('pricing_mode') === 'usage_based'),
                Hidden::make('type')
                    ->default(PriceType::Recurring->value)
                    ->dehydrateStateUsing(fn ($state, Get $get): string => self::isRecurringPricingMode($get('pricing_mode'))
                        ? PriceType::Recurring->value
                        : PriceType::OneTime->value),
                Toggle::make('is_active')
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
                    ->label('Amount')
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
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
            ])
            ->emptyStateHeading('No prices')
            ->emptyStateDescription('Add a price to this product. It will be synced to providers.')
            ->emptyStateIcon('heroicon-o-tag');
    }
}
