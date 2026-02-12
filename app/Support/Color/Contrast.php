<?php

namespace App\Support\Color;

final class Contrast
{
    public static function normalizeHex(?string $value): ?string
    {
        $normalized = strtoupper(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        if (! str_starts_with($normalized, '#')) {
            $normalized = '#'.$normalized;
        }

        if (preg_match('/^#([A-F0-9]{3}|[A-F0-9]{6})$/', $normalized, $matches) !== 1) {
            return null;
        }

        $hex = $matches[1];

        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex) ?? $hex;
        }

        return '#'.$hex;
    }

    public static function contrastRatio(string $foregroundHex, string $backgroundHex): ?float
    {
        $foreground = self::hexToRgb(self::normalizeHex($foregroundHex));
        $background = self::hexToRgb(self::normalizeHex($backgroundHex));

        if ($foreground === null || $background === null) {
            return null;
        }

        $foregroundLuminance = self::relativeLuminance($foreground);
        $backgroundLuminance = self::relativeLuminance($background);

        $lighter = max($foregroundLuminance, $backgroundLuminance);
        $darker = min($foregroundLuminance, $backgroundLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public static function isAccessible(string $foregroundHex, string $backgroundHex, float $minimumRatio = 4.5): bool
    {
        $ratio = self::contrastRatio($foregroundHex, $backgroundHex);

        return $ratio !== null && $ratio >= $minimumRatio;
    }

    public static function bestTextColor(string $backgroundHex, string $lightText = '#FFFFFF', string $darkText = '#0F172A'): string
    {
        $lightRatio = self::contrastRatio($lightText, $backgroundHex) ?? 0.0;
        $darkRatio = self::contrastRatio($darkText, $backgroundHex) ?? 0.0;

        return $darkRatio > $lightRatio ? self::normalizeHex($darkText) ?? '#0F172A' : self::normalizeHex($lightText) ?? '#FFFFFF';
    }

    /**
     * @return array{r:int,g:int,b:int}|null
     */
    private static function hexToRgb(?string $hex): ?array
    {
        if ($hex === null) {
            return null;
        }

        $value = ltrim($hex, '#');

        if (strlen($value) !== 6) {
            return null;
        }

        return [
            'r' => hexdec(substr($value, 0, 2)),
            'g' => hexdec(substr($value, 2, 2)),
            'b' => hexdec(substr($value, 4, 2)),
        ];
    }

    /**
     * @param  array{r:int,g:int,b:int}  $rgb
     */
    private static function relativeLuminance(array $rgb): float
    {
        $red = self::linearizeChannel($rgb['r'] / 255);
        $green = self::linearizeChannel($rgb['g'] / 255);
        $blue = self::linearizeChannel($rgb['b'] / 255);

        return (0.2126 * $red) + (0.7152 * $green) + (0.0722 * $blue);
    }

    private static function linearizeChannel(float $channel): float
    {
        if ($channel <= 0.03928) {
            return $channel / 12.92;
        }

        return (($channel + 0.055) / 1.055) ** 2.4;
    }
}
