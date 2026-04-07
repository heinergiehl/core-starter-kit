<?php

namespace App\Filament\Admin\Resources\Concerns;

use App\Enums\PriceType;
use App\Enums\UsageLimitBehavior;
use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

trait InteractsWithPricingModes
{
    protected static function billingFamilyOptions(): array
    {
        return [
            'subscription' => 'Subscription',
            'one_time' => 'One-time',
        ];
    }

    protected static function billingFamilySummary(?string $billingFamily): string
    {
        return match ($billingFamily) {
            'one_time' => 'Use this for lifetime access, single-purchase licenses, or pay-what-you-want support offers.',
            default => 'Use this for recurring plans, including flat subscriptions and usage-based billing.',
        };
    }

    protected static function defaultPricingModeForProductType(?string $productType): string
    {
        return $productType === 'one_time' ? 'one_time_fixed' : 'subscription';
    }

    protected static function pricingModeOptionsForProductType(?string $productType): array
    {
        return $productType === 'one_time'
            ? [
                'one_time_fixed' => 'One-time fixed',
                'one_time_pwyw' => 'Pay what you want',
            ]
            : [
                'subscription' => 'Subscription',
                'usage_based' => 'Usage-based',
            ];
    }

    protected static function pricingModeOptionsForInterval(?string $interval): array
    {
        return $interval === 'once'
            ? [
                'one_time_fixed' => 'One-time fixed',
                'one_time_pwyw' => 'Pay what you want',
            ]
            : [
                'subscription' => 'Subscription',
                'usage_based' => 'Usage-based',
            ];
    }

    protected static function pricingModeSummary(?string $pricingMode): string
    {
        return match ($pricingMode) {
            'one_time_fixed' => 'Charge a single fixed amount once. Good for lifetime plans and one-off licenses.',
            'one_time_pwyw' => 'Let buyers choose their own one-time amount with optional defaults and suggested amounts.',
            'usage_based' => 'Charge a recurring base fee and track included usage with either overage billing or a hard usage cap.',
            default => 'Charge on a recurring schedule. Configure billing frequency and optional free trial below.',
        };
    }

    protected static function usageLimitBehaviorOptions(): array
    {
        $options = [];

        foreach (UsageLimitBehavior::cases() as $behavior) {
            $options[$behavior->value] = $behavior->label();
        }

        return $options;
    }

    protected static function usageLimitBehaviorSummary(?string $usageLimitBehavior): string
    {
        return self::resolveUsageLimitBehavior($usageLimitBehavior)->description();
    }

    protected static function resolveUsageLimitBehavior(mixed $usageLimitBehavior): UsageLimitBehavior
    {
        return UsageLimitBehavior::tryFrom((string) $usageLimitBehavior) ?? UsageLimitBehavior::BillOverage;
    }

    protected static function usageIncludedUnitsRule(): Closure
    {
        return function (Get $get): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                if ($get('pricing_mode') !== 'usage_based') {
                    return;
                }

                if (! self::resolveUsageLimitBehavior($get('usage_limit_behavior'))->blocksUsage()) {
                    return;
                }

                $includedUnits = is_numeric($value) ? (int) $value : null;

                if ($includedUnits === null || $includedUnits < 1) {
                    $fail(__('Included units are required when usage is blocked at the limit.'));
                }
            };
        };
    }

    protected static function isRecurringPricingMode(?string $pricingMode): bool
    {
        return in_array($pricingMode, ['subscription', 'usage_based'], true);
    }

    protected static function pricingModeLabelForRecord(?string $interval, bool $allowCustomAmount, bool $isMetered = false): string
    {
        return match (self::resolvePricingMode($interval, $allowCustomAmount, $isMetered)) {
            'one_time_pwyw' => 'Pay what you want',
            'one_time_fixed' => 'One-time',
            'usage_based' => 'Usage-based',
            default => 'Subscription',
        };
    }

    protected static function resolvePricingMode(
        ?string $interval,
        bool $allowCustomAmount = false,
        bool $isMetered = false,
        ?string $productType = null
    ): string {
        if ($interval === 'once') {
            return $allowCustomAmount ? 'one_time_pwyw' : 'one_time_fixed';
        }

        if ($isMetered) {
            return 'usage_based';
        }

        return self::defaultPricingModeForProductType($productType);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function collectPriceState(Get $get): array
    {
        return [
            'label' => $get('label'),
            'interval' => $get('interval'),
            'interval_count' => $get('interval_count'),
            'type' => $get('type'),
            'has_trial' => $get('has_trial'),
            'trial_interval' => $get('trial_interval'),
            'trial_interval_count' => $get('trial_interval_count'),
            'allow_custom_amount' => $get('allow_custom_amount'),
            'is_metered' => $get('is_metered'),
            'usage_meter_name' => $get('usage_meter_name'),
            'usage_meter_key' => $get('usage_meter_key'),
            'usage_unit_label' => $get('usage_unit_label'),
            'usage_included_units' => $get('usage_included_units'),
            'usage_package_size' => $get('usage_package_size'),
            'usage_overage_amount' => $get('usage_overage_amount'),
            'usage_rounding_mode' => $get('usage_rounding_mode'),
            'usage_limit_behavior' => $get('usage_limit_behavior'),
            'custom_amount_default' => $get('custom_amount_default'),
            'custom_amount_minimum' => $get('custom_amount_minimum'),
            'custom_amount_maximum' => $get('custom_amount_maximum'),
            'suggested_amounts' => $get('suggested_amounts'),
            'amount' => $get('amount'),
        ];
    }

    /**
     * @param  array<string, mixed>  $priceState
     */
    protected static function fillPriceState(Set $set, array $priceState): void
    {
        foreach ([
            'label',
            'interval',
            'interval_count',
            'type',
            'has_trial',
            'trial_interval',
            'trial_interval_count',
            'allow_custom_amount',
            'is_metered',
            'usage_meter_name',
            'usage_meter_key',
            'usage_unit_label',
            'usage_included_units',
            'usage_package_size',
            'usage_overage_amount',
            'usage_rounding_mode',
            'usage_limit_behavior',
            'custom_amount_default',
            'custom_amount_minimum',
            'custom_amount_maximum',
            'suggested_amounts',
        ] as $field) {
            $set($field, $priceState[$field] ?? null);
        }
    }

    protected static function applyPricingMode(Get $get, Set $set, ?string $pricingMode): void
    {
        self::fillPriceState($set, self::synchronizePriceStateForMode(
            self::collectPriceState($get),
            $pricingMode
        ));
    }

    /**
     * @param  array<string, mixed>  $priceState
     * @return array<string, mixed>
     */
    protected static function synchronizePriceStateForMode(array $priceState, ?string $pricingMode): array
    {
        $resolvedPricingMode = in_array($pricingMode, ['subscription', 'usage_based', 'one_time_fixed', 'one_time_pwyw'], true)
            ? $pricingMode
            : 'subscription';

        if (in_array($resolvedPricingMode, ['subscription', 'usage_based'], true)) {
            $resolvedInterval = in_array(($priceState['interval'] ?? null), ['month', 'year', 'week', 'day'], true)
                ? (string) $priceState['interval']
                : 'month';

            $priceState['interval'] = $resolvedInterval;
            $priceState['interval_count'] = max(1, (int) ($priceState['interval_count'] ?? 1));
            $priceState['type'] = PriceType::Recurring->value;
            $priceState['allow_custom_amount'] = false;
            $priceState['is_metered'] = $resolvedPricingMode === 'usage_based';

            self::clearCustomAmountConfiguration($priceState);

            if ($resolvedPricingMode === 'usage_based') {
                $resolvedUsageBehavior = self::resolveUsageLimitBehavior($priceState['usage_limit_behavior'] ?? null);
                $priceState['usage_package_size'] = max(1, (int) ($priceState['usage_package_size'] ?? 1));
                $priceState['usage_rounding_mode'] = in_array(($priceState['usage_rounding_mode'] ?? null), ['up', 'down'], true)
                    ? (string) $priceState['usage_rounding_mode']
                    : 'up';
                $priceState['usage_limit_behavior'] = $resolvedUsageBehavior->value;

                if (blank($priceState['label'] ?? null) || in_array($priceState['label'], ['Lifetime', 'Supporter', 'Monthly', 'Yearly', 'Weekly', 'Daily'], true)) {
                    $priceState['label'] = self::defaultUsagePriceLabelForInterval($resolvedInterval);
                }

                return $priceState;
            }

            self::clearUsageConfiguration($priceState);

            if (blank($priceState['label'] ?? null) || in_array($priceState['label'], ['Lifetime', 'Supporter', 'Metered Monthly', 'Metered Yearly', 'Metered Weekly', 'Metered Daily'], true)) {
                $priceState['label'] = self::defaultPriceLabelForInterval($resolvedInterval);
            }

            return $priceState;
        }

        $priceState['interval'] = 'once';
        $priceState['interval_count'] = 1;
        $priceState['type'] = PriceType::OneTime->value;
        $priceState['has_trial'] = false;
        $priceState['trial_interval'] = null;
        $priceState['trial_interval_count'] = null;
        $priceState['allow_custom_amount'] = $resolvedPricingMode === 'one_time_pwyw';
        $priceState['is_metered'] = false;
        self::clearUsageConfiguration($priceState);

        if (blank($priceState['label'] ?? null) || in_array($priceState['label'], ['Monthly', 'Yearly', 'Weekly', 'Daily'], true)) {
            $priceState['label'] = 'Lifetime';
        }

        if ($resolvedPricingMode === 'one_time_pwyw') {
            if (filled($priceState['amount'] ?? null) && blank($priceState['custom_amount_default'] ?? null)) {
                $priceState['custom_amount_default'] = $priceState['amount'];
            }

            return $priceState;
        }

        self::clearCustomAmountConfiguration($priceState);

        return $priceState;
    }

    /**
     * @param  array<string, mixed>  $priceState
     * @return array<string, mixed>
     */
    public static function normalizePriceDataForPersistence(
        array $priceState,
        ?string $pricingMode = null,
        ?string $productType = null
    ): array {
        $resolvedPricingMode = $pricingMode ?? self::resolvePricingMode(
            $priceState['interval'] ?? null,
            (bool) ($priceState['allow_custom_amount'] ?? false),
            (bool) ($priceState['is_metered'] ?? false),
            $productType
        );

        $normalizedState = self::synchronizePriceStateForMode($priceState, $resolvedPricingMode);

        $isRecurringMode = in_array($resolvedPricingMode, ['subscription', 'usage_based'], true);

        $normalizedState['interval'] = $isRecurringMode
            ? (in_array($normalizedState['interval'] ?? null, ['month', 'year', 'week', 'day'], true)
                ? (string) $normalizedState['interval']
                : 'month')
            : 'once';
        $normalizedState['interval_count'] = $isRecurringMode
            ? max(1, (int) ($normalizedState['interval_count'] ?? 1))
            : 1;
        $normalizedState['type'] = $isRecurringMode
            ? PriceType::Recurring->value
            : PriceType::OneTime->value;
        $normalizedState['allow_custom_amount'] = $resolvedPricingMode === 'one_time_pwyw';
        $normalizedState['is_metered'] = $resolvedPricingMode === 'usage_based';

        if ($resolvedPricingMode !== 'one_time_pwyw') {
            self::clearCustomAmountConfiguration($normalizedState);
        }

        if ($resolvedPricingMode !== 'usage_based') {
            self::clearUsageConfiguration($normalizedState);
        }

        unset($normalizedState['pricing_mode']);

        return $normalizedState;
    }

    /**
     * @param  array<string, mixed>  $priceState
     */
    protected static function clearCustomAmountConfiguration(array &$priceState): void
    {
        $priceState['custom_amount_default'] = null;
        $priceState['custom_amount_minimum'] = null;
        $priceState['custom_amount_maximum'] = null;
        $priceState['suggested_amounts'] = null;
    }

    /**
     * @param  array<string, mixed>  $priceState
     */
    protected static function clearUsageConfiguration(array &$priceState): void
    {
        $priceState['usage_meter_name'] = null;
        $priceState['usage_meter_key'] = null;
        $priceState['usage_unit_label'] = null;
        $priceState['usage_included_units'] = null;
        $priceState['usage_package_size'] = null;
        $priceState['usage_overage_amount'] = null;
        $priceState['usage_rounding_mode'] = null;
        $priceState['usage_limit_behavior'] = UsageLimitBehavior::BillOverage->value;
    }

    protected static function defaultPriceLabelForInterval(string $interval): string
    {
        return match ($interval) {
            'year' => 'Yearly',
            'week' => 'Weekly',
            'day' => 'Daily',
            default => 'Monthly',
        };
    }

    protected static function defaultUsagePriceLabelForInterval(string $interval): string
    {
        return match ($interval) {
            'year' => 'Metered Yearly',
            'week' => 'Metered Weekly',
            'day' => 'Metered Daily',
            default => 'Metered Monthly',
        };
    }
}
