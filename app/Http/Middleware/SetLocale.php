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
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $request->query('lang');

        if (!$locale || !in_array($locale, $supported, true)) {
            $locale = config('app.locale', 'en');
        } else {
            $request->session()->put('locale', $locale);
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
