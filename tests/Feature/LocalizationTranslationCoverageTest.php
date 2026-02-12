<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalizationTranslationCoverageTest extends TestCase
{
    public function test_supported_non_english_locales_have_core_framework_translations(): void
    {
        $supported = array_keys((array) config('saas.locales.supported', ['en' => 'English']));

        $englishAuthFailed = trans('auth.failed', [], 'en');
        $englishValidationRequired = trans('validation.required', ['attribute' => 'email'], 'en');
        $englishPaginationNext = trans('pagination.next', [], 'en');

        foreach ($supported as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $this->assertNotSame(
                $englishAuthFailed,
                trans('auth.failed', [], $locale),
                "Missing auth translation for locale [{$locale}]."
            );

            $this->assertNotSame(
                $englishValidationRequired,
                trans('validation.required', ['attribute' => 'email'], $locale),
                "Missing validation translation for locale [{$locale}]."
            );

            $this->assertNotSame(
                $englishPaginationNext,
                trans('pagination.next', [], $locale),
                "Missing pagination translation for locale [{$locale}]."
            );
        }
    }
}
