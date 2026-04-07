<?php

namespace App\Filament\Admin\Resources\Concerns;

use App\Support\Money\CurrencyAmount;
use Closure;
use Filament\Schemas\Components\Utilities\Get;

trait InteractsWithMoneyFields
{
    protected static function formatMinorAmountForInput(mixed $state, ?string $currency = null): ?string
    {
        return CurrencyAmount::formatMinorForInput($state, $currency);
    }

    protected static function formatMajorAmountForPreview(mixed $state, ?string $currency = null): string
    {
        return CurrencyAmount::formatMajor($state, $currency, true, false);
    }

    protected static function formatMinorAmountForPreview(mixed $state, ?string $currency = null): string
    {
        return CurrencyAmount::formatMinor($state, $currency, true, true);
    }

    protected static function moneyCurrencyCode(?string $currency): string
    {
        return CurrencyAmount::currencyCode($currency);
    }

    protected static function parseMoneyInputToMinor(mixed $state, ?string $currency = null): ?int
    {
        return CurrencyAmount::parseMajorToMinor($state, $currency);
    }

    /**
     * @return array<int, int>|null
     */
    protected static function parseMoneyLinesToMinor(mixed $state, ?string $currency = null): ?array
    {
        if ($state === null) {
            return null;
        }

        $lines = preg_split('/\r?\n/', (string) $state) ?: [];
        $amounts = [];

        foreach ($lines as $line) {
            $normalizedLine = trim($line);

            if ($normalizedLine === '') {
                continue;
            }

            $amountMinor = self::parseMoneyInputToMinor($normalizedLine, $currency);

            if ($amountMinor === null) {
                continue;
            }

            $amounts[] = $amountMinor;
        }

        return $amounts === [] ? null : array_values($amounts);
    }

    protected static function customAmountRangeRule(string $field): Closure
    {
        return function (Get $get) use ($field): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($get, $field): void {
                $currency = self::moneyCurrencyCode($get('currency'));
                $minimum = $field === 'custom_amount_minimum'
                    ? self::parseMoneyInputToMinor($value, $currency)
                    : self::parseMoneyInputToMinor($get('custom_amount_minimum'), $currency);
                $default = $field === 'custom_amount_default'
                    ? self::parseMoneyInputToMinor($value, $currency)
                    : self::parseMoneyInputToMinor($get('custom_amount_default'), $currency);
                $maximum = $field === 'custom_amount_maximum'
                    ? self::parseMoneyInputToMinor($value, $currency)
                    : self::parseMoneyInputToMinor($get('custom_amount_maximum'), $currency);

                if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
                    $fail(__('The minimum amount must be less than or equal to the maximum amount.'));

                    return;
                }

                if ($default !== null && $minimum !== null && $default < $minimum) {
                    $fail(__('The default amount must be at least the minimum amount.'));

                    return;
                }

                if ($default !== null && $maximum !== null && $default > $maximum) {
                    $fail(__('The default amount must be less than or equal to the maximum amount.'));
                }
            };
        };
    }

    protected static function suggestedAmountsRule(): Closure
    {
        return function (Get $get): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                if ($value === null || trim((string) $value) === '') {
                    return;
                }

                $currency = self::moneyCurrencyCode($get('currency'));
                $minimum = self::parseMoneyInputToMinor($get('custom_amount_minimum'), $currency);
                $maximum = self::parseMoneyInputToMinor($get('custom_amount_maximum'), $currency);
                $lines = preg_split('/\r?\n/', (string) $value) ?: [];

                foreach ($lines as $line) {
                    $normalizedLine = trim($line);

                    if ($normalizedLine === '') {
                        continue;
                    }

                    $amountMinor = self::parseMoneyInputToMinor($normalizedLine, $currency);

                    if ($amountMinor === null) {
                        $fail(__('Suggested amounts must be valid currency values, one per line.'));

                        return;
                    }

                    if ($minimum !== null && $amountMinor < $minimum) {
                        $fail(__('Each suggested amount must be at least the minimum amount.'));

                        return;
                    }

                    if ($maximum !== null && $amountMinor > $maximum) {
                        $fail(__('Each suggested amount must be less than or equal to the maximum amount.'));

                        return;
                    }
                }
            };
        };
    }
}
