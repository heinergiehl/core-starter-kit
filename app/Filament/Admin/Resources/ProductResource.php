<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Product;
use App\Enums\PriceType;
use App\Enums\UsageLimitBehavior;
use App\Filament\Admin\Resources\Concerns\InteractsWithMoneyFields;
use App\Filament\Admin\Resources\Concerns\InteractsWithPricingModes;
use App\Filament\Admin\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Admin\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Admin\Resources\ProductResource\Pages\ListProducts;
use App\Jobs\SyncProductsJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class ProductResource extends Resource
{
    use InteractsWithMoneyFields;
    use InteractsWithPricingModes;

    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Product Management';

    protected static ?string $navigationLabel = 'Products';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Product Details')
                    ->schema([
                        TextInput::make('key')
                            ->label('Key')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('This name will be synced to payment providers'),
                        TextInput::make('summary')
                            ->maxLength(255)
                            ->helperText('Local marketing summary (not synced)'),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('This description will be synced to payment providers'),
                        ToggleButtons::make('type')
                            ->label('Billing family')
                            ->options(self::billingFamilyOptions())
                            ->required()
                            ->default('subscription')
                            ->inline()
                            ->live()
                            ->helperText(fn (Get $get): string => self::billingFamilySummary($get('type')))
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                $prices = $get('prices');

                                if (! is_array($prices) || $prices === []) {
                                    return;
                                }

                                $updatedPrices = [];

                                foreach ($prices as $key => $priceState) {
                                    if (! is_array($priceState)) {
                                        $updatedPrices[$key] = $priceState;

                                        continue;
                                    }

                                    $pricingMode = $state === 'one_time'
                                        ? self::resolvePricingMode(
                                            $priceState['interval'] ?? null,
                                            (bool) ($priceState['allow_custom_amount'] ?? false),
                                            (bool) ($priceState['is_metered'] ?? false),
                                            'one_time'
                                        )
                                        : ((bool) ($priceState['is_metered'] ?? false) ? 'usage_based' : 'subscription');

                                    if ($state === 'one_time' && ! in_array($pricingMode, ['one_time_fixed', 'one_time_pwyw'], true)) {
                                        $pricingMode = 'one_time_fixed';
                                    }

                                    $updatedPriceState = self::synchronizePriceStateForMode($priceState, $pricingMode);
                                    $updatedPriceState['pricing_mode'] = $pricingMode;

                                    $updatedPrices[$key] = $updatedPriceState;
                                }

                                $set('prices', $updatedPrices);
                            }),
                        Placeholder::make('usage_based_status')
                            ->label('Usage-based pricing')
                            ->content('Usage-based pricing is available on recurring offers. Configure included units, overage rate, and the meter details inside each recurring price.')
                            ->columnSpanFull(),
                        Toggle::make('is_featured')
                            ->label('Featured'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(false),
                    ])->columns(2),

                Section::make('Pricing')
                    ->description('Manage prices for this product. They will be synced automatically.')
                    ->schema([
                        Repeater::make('prices')
                            ->hiddenOn('edit')
                            ->relationship()
                            ->helperText('Choose a clear pricing mode for each offer. Usage-based prices stay recurring and add included usage plus overage tracking.')
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::normalizePriceDataForPersistence(
                                $data,
                                $data['pricing_mode'] ?? null
                            ))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::normalizePriceDataForPersistence(
                                $data,
                                $data['pricing_mode'] ?? null
                            ))
                            ->schema([
                                ToggleButtons::make('pricing_mode')
                                    ->label('Pricing mode')
                                    ->options(fn (Get $get): array => self::pricingModeOptionsForProductType(
                                        (string) ($get('../../type') ?: 'subscription')
                                    ))
                                    ->default(fn (Get $get): string => self::defaultPricingModeForProductType(
                                        (string) ($get('../../type') ?: 'subscription')
                                    ))
                                    ->inline()
                                    ->live()
                                    ->afterStateHydrated(function (?string $state, Get $get, Set $set): void {
                                        $resolvedPricingMode = self::resolvePricingMode(
                                            $get('interval'),
                                            (bool) $get('allow_custom_amount'),
                                            (bool) $get('is_metered'),
                                            (string) ($get('../../type') ?: 'subscription')
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
                                        (string) ($get('pricing_mode') ?: self::defaultPricingModeForProductType($get('../../type')))
                                    )),
                                Group::make([
                                    Select::make('interval')
                                        ->label('Billing Frequency')
                                        ->options([
                                            'month' => 'Monthly',
                                            'year' => 'Yearly',
                                            'week' => 'Weekly',
                                            'day' => 'Daily',
                                            'once' => 'Lifetime / One-time',
                                        ])
                                        ->required()
                                        ->default('month')
                                        ->live()
                                        ->visible(fn (Get $get): bool => self::isRecurringPricingMode($get('pricing_mode')))
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

                                    TextInput::make('amount')
                                        ->label('Amount')
                                        ->numeric()
                                        ->step(fn (Get $get): string => \App\Support\Money\CurrencyAmount::inputStep($get('currency')))
                                        ->required()
                                        ->suffix(fn (Get $get): string => self::moneyCurrencyCode($get('currency')))
                                        ->formatStateUsing(fn ($state, Get $get): ?string => self::formatMinorAmountForInput($state, $get('currency')))
                                        ->dehydrateStateUsing(fn ($state, Get $get): ?int => self::parseMoneyInputToMinor($state, $get('currency')))
                                        ->helperText(fn (Get $get): string => $get('pricing_mode') === 'usage_based'
                                            ? 'This is the recurring base fee. Included usage and overage billing are configured below.'
                                            : ($get('allow_custom_amount')
                                                ? 'Shown as a normal currency value. Stored in minor units automatically and used as the default checkout amount.'
                                                : 'Shown as a normal currency value. Stored in minor units automatically.')),

                                    TextInput::make('label')
                                        ->required()
                                        ->placeholder('e.g. Monthly'),
                                ])->columns(3),

                                Section::make('Advanced Config')
                                    ->collapsed()
                                    ->compact()
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('currency')
                                                ->default('USD')
                                                ->maxLength(3)
                                                ->required(),
                                            TextInput::make('key')
                                                ->unique(ignoreRecord: true)
                                                ->placeholder('Leave empty to auto-generate')
                                                ->helperText('Unique ID for API usage'),
                                            TextInput::make('interval_count')
                                                ->numeric()
                                                ->default(1)
                                                ->label('Interval Count')
                                                ->visible(fn (Get $get): bool => self::isRecurringPricingMode($get('pricing_mode')))
                                                ->dehydrateStateUsing(fn ($state, Get $get): int => self::isRecurringPricingMode($get('pricing_mode'))
                                                    ? max(1, (int) $state)
                                                    : 1)
                                                ->helperText('e.g. "3" for Quarterly'),
                                        ]),

                                        Grid::make(2)->schema([
                                            Toggle::make('has_trial')
                                                ->label('Offer Free Trial')
                                                ->live()
                                                ->visible(fn (Get $get): bool => self::isRecurringPricingMode($get('pricing_mode')))
                                                ->dehydrateStateUsing(fn ($state, Get $get): bool => self::isRecurringPricingMode($get('pricing_mode'))
                                                    ? (bool) $state
                                                    : false),
                                            TextInput::make('trial_interval_count')
                                                ->label('Trial Days')
                                                ->numeric()
                                                ->default(7)
                                                ->visible(fn (Get $get): bool => self::isRecurringPricingMode($get('pricing_mode')) && (bool) $get('has_trial'))
                                                ->dehydrateStateUsing(fn ($state, Get $get): ?int => self::isRecurringPricingMode($get('pricing_mode')) && (bool) $get('has_trial')
                                                    ? (int) $state
                                                    : null),
                                        ]),

                                        Section::make('Usage-based billing')
                                            ->collapsed()
                                            ->compact()
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
                                            ->collapsed()
                                            ->compact()
                                            ->visible(fn (Get $get): bool => $get('pricing_mode') === 'one_time_pwyw')
                                            ->schema([
                                                Grid::make(3)->schema([
                                                    TextInput::make('custom_amount_default')
                                                        ->label('Default amount')
                                                        ->numeric()
                                                        ->step(fn (Get $get): string => \App\Support\Money\CurrencyAmount::inputStep($get('currency')))
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
                                        Hidden::make('is_active')->default(true),
                                    ]),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                $defaultAmount = $state['custom_amount_default'] ?? $state['amount'] ?? null;
                                $currency = self::moneyCurrencyCode((string) ($state['currency'] ?? 'USD'));
                                $amountLabel = self::formatMajorAmountForPreview($defaultAmount, $currency);

                                if (! empty($state['allow_custom_amount'])) {
                                    $amountLabel = 'Pay what you want'.($amountLabel !== '' ? " from {$amountLabel}" : '');
                                } elseif (! empty($state['is_metered'])) {
                                    $unitLabel = trim((string) ($state['usage_unit_label'] ?? 'unit'));
                                    $includedUnits = $state['usage_included_units'] ?? null;
                                    $usageBehavior = UsageLimitBehavior::tryFrom((string) ($state['usage_limit_behavior'] ?? '')) ?? UsageLimitBehavior::BillOverage;
                                    $amountLabel = $usageBehavior->blocksUsage()
                                        ? trim($amountLabel.($includedUnits ? " + {$includedUnits} included {$unitLabel}".($includedUnits === 1 ? '' : 's').' cap' : ' + capped usage'))
                                        : trim($amountLabel.($includedUnits ? " + {$includedUnits} included {$unitLabel}".($includedUnits === 1 ? '' : 's') : ' + usage'));
                                }

                                return trim(($state['label'] ?? 'Price').' - '.$amountLabel);
                            })
                            ->collapsed(false)
                            ->cloneable()
                            ->grid(1) // 1 price per row for better visibility
                            ->defaultItems(1),
                    ]),

                Section::make('Entitlements & Features')
                    ->schema([
                        Textarea::make('features')
                            ->label('Features (one per line)')
                            ->rows(5)
                            ->columnSpanFull()
                            ->formatStateUsing(function ($state): string {
                                if (is_array($state)) {
                                    return implode("\n", $state);
                                }

                                return (string) $state;
                            })
                            ->dehydrateStateUsing(function ($state): array {
                                if (! $state) {
                                    return [];
                                }
                                $lines = preg_split('/\r?\n/', (string) $state);

                                return array_values(array_filter(array_map('trim', $lines)));
                            }),
                        Textarea::make('entitlements')
                            ->label('Entitlements (JSON)')
                            ->rows(6)
                            ->columnSpanFull()
                            ->helperText('Example: {"storage_limit_mb": 2048, "support_sla": "priority"}')
                            ->formatStateUsing(function ($state): ?string {
                                if (! $state) {
                                    return null;
                                }

                                return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            })
                            ->dehydrateStateUsing(function ($state): ?array {
                                if (! $state) {
                                    return null;
                                }
                                $decoded = json_decode((string) $state, true);

                                return is_array($decoded) ? $decoded : null;
                            })
                            ->rules(['nullable', 'json']),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->badge()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'one_time' ? 'One-time' : 'Subscription')
                    ->color(fn (string $state): string => $state === 'one_time' ? 'warning' : 'primary'),
                TextColumn::make('providerMappings.provider')
                    ->label('Providers')
                    ->badge()
                    ->getStateUsing(function (Product $record) {
                        static $activeProviders = null;

                        if ($activeProviders === null) {
                            $activeProviders = \App\Domain\Billing\Models\PaymentProvider::where('is_active', true)
                                ->pluck('slug')
                                ->map(fn ($s) => strtolower($s))
                                ->toArray();
                        }

                        return $record->providerMappings
                            ->pluck('provider')
                            ->filter(fn ($provider) => in_array(strtolower((string) $provider), $activeProviders))
                            ->unique()
                            ->values()
                            ->all();
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'stripe' => 'primary',
                        'paddle' => 'success',
                        'default' => 'gray', // Added default case to match style
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->toggleable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->default(true) // Only show active by default
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->placeholder('All Products'),
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('sync')
                    ->label('Import Provider Catalog')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->disabled(fn () => false) // Placeholder if we had a job status to check
                    ->form([
                        Toggle::make('include_deleted')
                            ->label('Include deleted (override local deletions)')
                            ->default(false),
                    ])
                    ->disabled(fn () => \Illuminate\Support\Facades\Cache::has('sync_products_job'))
                    ->tooltip(fn () => \Illuminate\Support\Facades\Cache::has('sync_products_job') ? 'Import is currently running in the background.' : 'Sync products from payment providers')
                    ->action(function (array $data) {
                        if (Cache::has('sync_products_job')) {
                            Notification::make()
                                ->title('Sync already in progress')
                                ->body('Please wait for the current synchronization to finish.')
                                ->warning()
                                ->send();

                            return;
                        }

                        // Lock the process for 10 minutes to prevent overlapping runs
                        Cache::put('sync_products_job', true, 600);

                        $includeDeleted = (bool) ($data['include_deleted'] ?? false);

                        SyncProductsJob::dispatch($includeDeleted, auth()->id());

                        Notification::make()
                            ->title('Import started')
                            ->body('Provider catalog import has started in the background. Local products will be updated when it completes.')
                            ->info()
                            ->send();
                    }),

                // Provider import is kept as a migration/reconciliation tool.
            ])
            ->defaultSort('name')
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
            'index' => ListProducts::route('/'),
            // 'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
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
