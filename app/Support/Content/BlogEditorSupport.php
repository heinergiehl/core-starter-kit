<?php

namespace App\Support\Content;

use Illuminate\Support\Str;

class BlogEditorSupport
{
    public static function generateSlug(?string $value): string
    {
        return Str::slug((string) $value);
    }

    public static function shouldAutoUpdateSlug(?string $currentSlug, ?string $previousSourceValue): bool
    {
        $currentSlug = (string) $currentSlug;

        if ($currentSlug === '') {
            return true;
        }

        return $currentSlug === static::generateSlug($previousSourceValue);
    }

    /**
     * @return array<int, string>
     */
    public static function parseBulkNames(?string $value): array
    {
        return collect(preg_split('/[\r\n,;]+/', (string) $value) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique(fn (string $item): string => Str::lower($item))
            ->values()
            ->all();
    }
}
