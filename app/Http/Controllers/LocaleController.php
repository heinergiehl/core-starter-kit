<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = (string) $request->input('locale');
        $supported = array_keys(config('saas.locales.supported', ['en' => 'English']));

        if (!in_array($locale, $supported, true)) {
            return redirect()->back();
        }

        $request->session()->put('locale', $locale);

        if ($request->user() && Schema::hasColumn('users', 'locale')) {
            $request->user()->update(['locale' => $locale]);
        }

        $redirect = (string) $request->input('redirect');
        $appUrl = rtrim((string) config('app.url', ''), '/');

        if ($redirect && $appUrl && str_starts_with($redirect, $appUrl)) {
            return redirect()->to($redirect);
        }

        return redirect()->back();
    }
}
