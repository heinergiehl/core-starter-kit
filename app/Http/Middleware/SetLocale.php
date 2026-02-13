<?php

namespace App\Http\Middleware;

use App\Support\Localization\LocalizedRouteService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class SetLocale
{
    public function __construct(
        private readonly LocalizedRouteService $localizedRouteService
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $supported = $this->localizedRouteService->supportedLocales();

        // Priority: 1. Locale from URL, 2. User preference, 3. Session, 4. Query param, 5. Browser Accept-Language
        $locale = $request->route('locale')
            ?? $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $request->query('lang')
            ?? $this->getBrowserLocale($request, $supported);

        if ($locale && $locale instanceof \App\Enums\Locale) {
            $locale = $locale->value;
        }

        if (! $locale || ! in_array($locale, $supported, true)) {
            $locale = $this->localizedRouteService->defaultLocale();
        }

        $request->session()->put('locale', $locale);
        app()->setLocale($locale);
        Carbon::setLocale($locale);
        URL::defaults(['locale' => $locale]);

        $response = $next($request);

        if (isset($response->headers)) {
            $response->headers->set('Content-Language', str_replace('_', '-', app()->getLocale()));
        }

        return $response;
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
            $languages[$langCode] = isset($languages[$langCode])
                ? max($languages[$langCode], $quality)
                : $quality;
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
