<?php

namespace App\Support\Money;

use Illuminate\Support\Str;

class CurrencyAmount
{
    /**
     * @var array<int, string>
     */
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF',
        'CLP',
        'DJF',
        'GNF',
        'JPY',
        'KMF',
        'KRW',
        'MGA',
        'PYG',
        'RWF',
        'UGX',
        'VND',
        'VUV',
        'XAF',
        'XOF',
        'XPF',
    ];

    /**
     * @var array<int, string>
     */
    private const THREE_DECIMAL_CURRENCIES = [
        'BHD',
        'IQD',
        'JOD',
        'KWD',
        'LYD',
        'OMR',
        'TND',
    ];

    public static function currencyCode(?string $currency): string
    {
        $normalizedCurrency = trim((string) $currency);

        return strtoupper($normalizedCurrency !== '' ? $normalizedCurrency : 'USD');
    }

    public static function fractionDigits(?string $currency): int
    {
        $currencyCode = self::currencyCode($currency);

        if (in_array($currencyCode, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return 0;
        }

        if (in_array($currencyCode, self::THREE_DECIMAL_CURRENCIES, true)) {
            return 3;
        }

        return 2;
    }

    public static function factor(?string $currency): int
    {
        return 10 ** self::fractionDigits($currency);
    }

    public static function inputStep(?string $currency): string
    {
        $fractionDigits = self::fractionDigits($currency);

        if ($fractionDigits === 0) {
            return '1';
        }

        return '0.'.str_repeat('0', $fractionDigits - 1).'1';
    }

    public static function formatMinorForInput(mixed $amountMinor, ?string $currency): ?string
    {
        if ($amountMinor === null || $amountMinor === '' || ! is_numeric($amountMinor)) {
            return null;
        }

        $fractionDigits = self::fractionDigits($currency);
        $displayAmount = ((float) $amountMinor) / self::factor($currency);

        return number_format($displayAmount, $fractionDigits, '.', '');
    }

    public static function formatMinor(mixed $amountMinor, ?string $currency, bool $includeCurrency = false, bool $currencyFirst = true): string
    {
        if ($amountMinor === null || $amountMinor === '' || ! is_numeric($amountMinor)) {
            return '';
        }

        $currencyCode = self::currencyCode($currency);
        $fractionDigits = self::fractionDigits($currency);
        $displayAmount = ((float) $amountMinor) / self::factor($currency);
        $formattedAmount = number_format($displayAmount, $fractionDigits, '.', ',');

        if (! $includeCurrency) {
            return $formattedAmount;
        }

        return $currencyFirst
            ? trim($currencyCode.' '.$formattedAmount)
            : trim($formattedAmount.' '.$currencyCode);
    }

    public static function formatMajor(mixed $amount, ?string $currency, bool $includeCurrency = false, bool $currencyFirst = false): string
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return '';
        }

        $currencyCode = self::currencyCode($currency);
        $formattedAmount = number_format((float) $amount, self::fractionDigits($currency), '.', ',');

        if (! $includeCurrency) {
            return $formattedAmount;
        }

        return $currencyFirst
            ? trim($currencyCode.' '.$formattedAmount)
            : trim($formattedAmount.' '.$currencyCode);
    }

    public static function parseMajorToMinor(mixed $amount, ?string $currency): ?int
    {
        if ($amount === null) {
            return null;
        }

        $normalizedAmount = trim((string) $amount);

        if ($normalizedAmount === '') {
            return null;
        }

        if (! preg_match('/^[+-]?\d+(?:\.(\d+))?$/', $normalizedAmount, $matches)) {
            return null;
        }

        $fractionDigits = self::fractionDigits($currency);
        $fraction = $matches[1] ?? '';

        if (strlen($fraction) > $fractionDigits) {
            return null;
        }

        $unsignedAmount = ltrim($normalizedAmount, '+-');
        [$wholePart, $fractionPart] = array_pad(explode('.', $unsignedAmount, 2), 2, '');

        $wholePart = ltrim($wholePart, '0');
        $wholePart = $wholePart === '' ? '0' : $wholePart;
        $fractionPart = Str::of($fractionPart)->padRight($fractionDigits, '0')->value();

        $minorAmount = $wholePart.$fractionPart;

        if (! ctype_digit($minorAmount)) {
            return null;
        }

        $resolvedMinorAmount = (int) $minorAmount;

        if (str_starts_with($normalizedAmount, '-')) {
            return $resolvedMinorAmount * -1;
        }

        return $resolvedMinorAmount;
    }
}
