<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Data\Price;
use App\Enums\UsageLimitBehavior;
use App\Support\Money\CurrencyAmount;
use Illuminate\Support\Str;

class PricePresentationService
{
    /**
     * Build display-ready data for a single Price inside a plan card.
     *
     * @return array{
     *   label: string,
     *   currency: string,
     *   interval: ?string,
     *   intervalLabel: string,
     *   intervalBillingUnit: string,
     *   amountDisplay: string,
     *   amountCaption: ?string,
     *   amountMeta: ?string,
     *   amountSummaryText: ?string,
     *   targetAmountMinor: ?int,
     *   supportsCustomAmount: bool,
     *   isMetered: bool,
     *   usageBlocksAtLimit: bool,
     *   usageMeterName: string,
     *   usageUnitLabel: string,
     *   usageIncludedUnits: ?int,
     *   pricingModeBadge: ?string,
     *   pricingModeBadgeColor: string,
     *   hasTrial: bool,
     *   trialDays: ?int,
     *   trialLabel: ?string,
     *   suggestedAmounts: array,
     *   customAmountMinimumFormatted: ?string,
     *   customAmountMaximumFormatted: ?string,
     *   customAmountDefaultFormatted: ?string,
     * }
     */
    public function forPricingCard(Price $price): array
    {
        $currency = strtoupper($price->currency ?: 'USD');
        $interval = $price->interval ?: null;
        $amount = $price->amount;
        $supportsCustomAmount = (bool) $price->allowCustomAmount;
        $isMetered = (bool) $price->isMetered;
        $usageLimitBehavior = $price->usageLimitBehavior instanceof UsageLimitBehavior
            ? $price->usageLimitBehavior
            : (UsageLimitBehavior::tryFrom((string) $price->usageLimitBehavior) ?? UsageLimitBehavior::BillOverage);
        $usageBlocksAtLimit = $usageLimitBehavior->blocksUsage();
        $usageMeterName = (string) ($price->usageMeterName ?: __('Usage'));
        $usageUnitLabel = (string) ($price->usageUnitLabel ?: 'unit');
        $usageIncludedUnits = is_numeric($price->usageIncludedUnits) ? (int) $price->usageIncludedUnits : null;
        $usagePackageSize = max((int) ($price->usagePackageSize ?? 1), 1);
        $usageOverageAmountMinor = is_numeric($price->usageOverageAmount) ? (int) $price->usageOverageAmount : null;

        $intervalLabels = $this->intervalLabels();
        $intervalLabel = $interval ? ($intervalLabels[$interval] ?? Str::of($interval)->replace('_', ' ')->title()->value()) : __('Interval');
        $intervalBillingUnit = $interval ? Str::of($interval)->replace('_', ' ')->lower()->value() : __('interval');

        $amountDisplay = __('Custom');
        $amountCaption = null;
        $amountMeta = null;
        $amountSummaryText = null;
        $targetAmountMinor = null;
        $pricingModeBadge = null;
        $pricingModeBadgeColor = '';

        if ($supportsCustomAmount) {
            $amountDisplay = __('Pay what you want');
            $pricingModeBadge = __('Pay what you want');
            $pricingModeBadgeColor = 'violet';

            $defaultMinor = $price->customAmountDefault ?? (is_numeric($amount) ? (int) round((float) $amount) : null);
            $startingMinor = $price->customAmountMinimum ?? $defaultMinor;
            $startingLabel = $startingMinor !== null ? CurrencyAmount::formatMinor($startingMinor, $currency, true, true) : null;

            $amountSummaryText = $startingLabel
                ? __('Pay what you want from :amount', ['amount' => $startingLabel])
                : __('Pay what you want');

            if ($price->customAmountMinimum !== null && $price->customAmountMaximum !== null) {
                $amountMeta = __('Choose any amount between :min and :max.', [
                    'min' => CurrencyAmount::formatMinor($price->customAmountMinimum, $currency, true, true),
                    'max' => CurrencyAmount::formatMinor($price->customAmountMaximum, $currency, true, true),
                ]);
            } elseif ($startingLabel) {
                $amountMeta = __('Starts at :amount.', ['amount' => $startingLabel]);
            }

            $targetAmountMinor = $defaultMinor;
        } elseif ($isMetered && is_numeric($amount)) {
            $pricingModeBadge = __('Usage-based');
            $pricingModeBadgeColor = 'emerald';

            $amountValue = (float) $amount;
            $amountDisplay = $price->amountIsMinor
                ? CurrencyAmount::formatMinor($amountValue, $currency)
                : CurrencyAmount::formatMajor($amountValue, $currency);
            $targetAmountMinor = $price->amountIsMinor
                ? (int) round($amountValue)
                : CurrencyAmount::parseMajorToMinor($amountValue, $currency);

            $baseAmountLabel = $price->amountIsMinor
                ? CurrencyAmount::formatMinor($amountValue, $currency, true, true)
                : CurrencyAmount::formatMajor($amountValue, $currency, true, true);
            $overageLabel = $usageOverageAmountMinor !== null
                ? CurrencyAmount::formatMinor($usageOverageAmountMinor, $currency, true, true)
                : null;
            $packageUnitsLabel = number_format($usagePackageSize).' '.Str::plural($usageUnitLabel, $usagePackageSize);

            $amountCaption = __('Base / :interval', ['interval' => $intervalBillingUnit]);
            $amountSummaryText = $usageBlocksAtLimit
                ? __(':amount / :interval included usage', ['amount' => $baseAmountLabel, 'interval' => $intervalBillingUnit])
                : __(':amount / :interval + usage', ['amount' => $baseAmountLabel, 'interval' => $intervalBillingUnit]);

            $amountMeta = $this->buildUsageMeta(
                $usageBlocksAtLimit,
                $usageIncludedUnits,
                $usageUnitLabel,
                $intervalBillingUnit,
                $overageLabel,
                $packageUnitsLabel,
                $usageMeterName,
            );
        } elseif (is_numeric($amount)) {
            $amountValue = (float) $amount;
            $amountDisplay = $price->amountIsMinor
                ? CurrencyAmount::formatMinor($amountValue, $currency)
                : CurrencyAmount::formatMajor($amountValue, $currency);
            $targetAmountMinor = $price->amountIsMinor
                ? (int) round($amountValue)
                : CurrencyAmount::parseMajorToMinor($amountValue, $currency);
            $amountSummaryText = trim($currency.' '.$amountDisplay);
        }

        $hasTrial = (bool) $price->hasTrial;
        $trialDays = $hasTrial ? ($price->trialIntervalCount ?? null) : null;
        $trialLabel = null;

        if ($hasTrial && $trialDays !== null) {
            $trialUnit = ($price->trialInterval ?? 'day') === 'month' ? __('month') : __('day');
            $trialLabel = $trialDays === 1
                ? __('1 :unit free trial', ['unit' => $trialUnit])
                : __(':count :units free trial', ['count' => $trialDays, 'units' => Str::plural($trialUnit, $trialDays)]);
        }

        $suggestedAmountsFormatted = [];

        if ($supportsCustomAmount && ! empty($price->suggestedAmounts)) {
            foreach ($price->suggestedAmounts as $suggestedMinor) {
                if (is_numeric($suggestedMinor)) {
                    $suggestedAmountsFormatted[] = [
                        'minor' => (int) $suggestedMinor,
                        'formatted' => CurrencyAmount::formatMinor((int) $suggestedMinor, $currency, true, true),
                    ];
                }
            }
        }

        $customAmountMinimumFormatted = $price->customAmountMinimum !== null
            ? CurrencyAmount::formatMinor($price->customAmountMinimum, $currency, true, true)
            : null;
        $customAmountMaximumFormatted = $price->customAmountMaximum !== null
            ? CurrencyAmount::formatMinor($price->customAmountMaximum, $currency, true, true)
            : null;
        $customAmountDefaultFormatted = $price->customAmountDefault !== null
            ? CurrencyAmount::formatMinor($price->customAmountDefault, $currency, true, true)
            : null;

        return [
            'label' => $price->label ?: ucfirst($price->key),
            'currency' => $currency,
            'interval' => $interval,
            'intervalLabel' => $intervalLabel,
            'intervalBillingUnit' => $intervalBillingUnit,
            'amountDisplay' => $amountDisplay,
            'amountCaption' => $amountCaption,
            'amountMeta' => $amountMeta,
            'amountSummaryText' => $amountSummaryText,
            'targetAmountMinor' => $targetAmountMinor,
            'supportsCustomAmount' => $supportsCustomAmount,
            'isMetered' => $isMetered,
            'usageBlocksAtLimit' => $usageBlocksAtLimit,
            'usageMeterName' => $usageMeterName,
            'usageUnitLabel' => $usageUnitLabel,
            'usageIncludedUnits' => $usageIncludedUnits,
            'pricingModeBadge' => $pricingModeBadge,
            'pricingModeBadgeColor' => $pricingModeBadgeColor,
            'hasTrial' => $hasTrial,
            'trialDays' => $trialDays,
            'trialLabel' => $trialLabel,
            'suggestedAmounts' => $suggestedAmountsFormatted,
            'customAmountMinimumFormatted' => $customAmountMinimumFormatted,
            'customAmountMaximumFormatted' => $customAmountMaximumFormatted,
            'customAmountDefaultFormatted' => $customAmountDefaultFormatted,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function intervalLabels(): array
    {
        return [
            'month' => __('Monthly'),
            'year' => __('Yearly'),
            'week' => __('Weekly'),
            'day' => __('Daily'),
            'once' => __('Lifetime'),
        ];
    }

    private function buildUsageMeta(
        bool $usageBlocksAtLimit,
        ?int $usageIncludedUnits,
        string $usageUnitLabel,
        string $intervalBillingUnit,
        ?string $overageLabel,
        string $packageUnitsLabel,
        string $usageMeterName,
    ): ?string {
        if ($usageBlocksAtLimit && $usageIncludedUnits !== null && $usageIncludedUnits > 0) {
            return __('Includes :included :units per :interval. New usage is blocked until renewal or upgrade.', [
                'included' => number_format($usageIncludedUnits),
                'units' => Str::plural($usageUnitLabel, $usageIncludedUnits),
                'interval' => $intervalBillingUnit,
            ]);
        }

        if ($usageIncludedUnits !== null && $usageIncludedUnits > 0 && $overageLabel) {
            return __('Includes :included :units per :interval, then :overage per :package.', [
                'included' => number_format($usageIncludedUnits),
                'units' => Str::plural($usageUnitLabel, $usageIncludedUnits),
                'interval' => $intervalBillingUnit,
                'overage' => $overageLabel,
                'package' => $packageUnitsLabel,
            ]);
        }

        if ($overageLabel) {
            return __('No included usage. :meter is billed at :overage per :package.', [
                'meter' => $usageMeterName,
                'overage' => $overageLabel,
                'package' => $packageUnitsLabel,
            ]);
        }

        return null;
    }
}
