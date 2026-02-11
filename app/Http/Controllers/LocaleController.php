<?php

namespace App\Http\Controllers;

use App\Support\Localization\LocalizedRouteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LocaleController extends Controller
{
    public function __construct(
        private readonly LocalizedRouteService $localizedRouteService
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $locale = (string) $request->input('locale');
        $localeEnum = \App\Enums\Locale::tryFrom($locale);

        if (! $localeEnum) {
            return redirect()->back();
        }

        $request->session()->put('locale', $locale);

        if ($request->user() && Schema::hasColumn('users', 'locale')) {
            $request->user()->update(['locale' => $localeEnum]);
        }

        $redirect = (string) $request->input('redirect', '');

        return redirect()->to(
            $this->localizedRouteService->resolveLocaleSwitchRedirect($request, $localeEnum->value, $redirect)
        );
    }
}
