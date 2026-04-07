<?php

namespace App\Http\Controllers;

use App\Http\Requests\Locale\LocaleUpdateRequest;
use App\Support\Localization\LocalizedRouteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;

class LocaleController extends Controller
{
    public function __construct(
        private readonly LocalizedRouteService $localizedRouteService
    ) {}

    public function __invoke(LocaleUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $localeEnum = \App\Enums\Locale::from($validated['locale']);

        $request->session()->put('locale', $localeEnum->value);

        if ($request->user() && Schema::hasColumn('users', 'locale')) {
            $request->user()->update(['locale' => $localeEnum]);
        }

        $redirect = $validated['redirect'] ?? '';

        return redirect()->to(
            $this->localizedRouteService->resolveLocaleSwitchRedirect($request, $localeEnum->value, $redirect)
        );
    }
}
