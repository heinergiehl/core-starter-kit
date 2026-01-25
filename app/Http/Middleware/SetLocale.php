<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $supported = array_keys(config('saas.locales.supported', ['en' => 'English']));

        // Priority: 1. User preference, 2. Session, 3. Query param, 4. Browser Accept-Language
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $request->query('lang')
            ?? $this->getBrowserLocale($request, $supported);

        if ($locale && $locale instanceof \App\Enums\Locale) {
            $locale = $locale->value;
        }

        if (! $locale || ! \App\Enums\Locale::tryFrom($locale)) {
            $locale = config('app.locale', 'en');
        } else {
            $request->session()->put('locale', $locale);
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    /**
     * Detect locale from browser Accept-Language header.
     */
    protected function getBrowserLocale(Request $request, array $supported): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');

        if (! $acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header (e.g., "de-DE,de;q=0.9,en;q=0.8")
        $languages = [];
        foreach (explode(',', $acceptLanguage) as $part) {
            $part = trim($part);
            $quality = 1.0;

            if (str_contains($part, ';q=')) {
                [$lang, $q] = explode(';q=', $part);
                $quality = (float) $q;
            } else {
                $lang = $part;
            }

            // Extract base language code (e.g., "de-DE" -> "de")
            $langCode = strtolower(explode('-', $lang)[0]);
            $languages[$langCode] = $quality;
        }

        // Sort by quality descending
        arsort($languages);

        // Return first supported language
        foreach (array_keys($languages) as $lang) {
            if (in_array($lang, $supported, true)) {
                return $lang;
            }
        }

        return null;
    }
}
