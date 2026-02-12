# Localization

This kit ships with a simple localization setup focused on customer-facing pages. The admin panel stays in English by default.

## 1) What is localized
- Marketing pages (`/`, `/pricing`, `/blog`, `/roadmap`)
- Auth screens and the customer dashboard (`/dashboard`)
- App Panel (customer-facing Filament panel at `/app`)

The Admin Panel (`/admin`) is intentionally left in English to reduce translation and support overhead.

---

## 2) Supported locales
Configure supported locales in `config/saas.php`:
```php
'locales' => [
    'default' => env('APP_LOCALE', 'en'),
    'supported' => [
        'en' => 'English',
        'de' => 'Deutsch',
        'es' => 'Español',
        'fr' => 'Français',
    ],
],
```

Set the default locale in `.env`:
```dotenv
APP_LOCALE=en
```

---

## 3) Locale selection flow
Locale resolution happens in `app/Http/Middleware/SetLocale.php`:
1) locale from URL route parameter (`/{locale}/...`)
2) user profile locale (if logged in)
3) session locale (set by the switcher)
4) `?lang=xx` query parameter
5) browser `Accept-Language` header

The `<x-locale-switcher />` component posts to `POST /locale` which stores the selection in session (and on the user record if signed in).

---

## 4) Translation files
All app-level translation files are loaded from:
```
resources/lang
```

Use both:
- JSON files for UI copy strings, e.g. `resources/lang/es.json`
- PHP group files for framework messages (`auth`, `validation`, `passwords`, `pagination`, etc.), e.g. `resources/lang/es/validation.php`

Add a new locale by creating `{locale}.json`, adding the locale to `config/saas.php`, and (recommended) adding PHP group files for validation/auth flows.

---

## 5) Notes for Filament
- `/app` includes the locale middleware so your custom App Panel labels can use `__()`.
- `/admin` does not include the locale middleware. Keep it in English unless you explicitly want to translate your operator UI.

If you want to localize Filament itself, publish the Filament language files and add the locale middleware to the admin panel provider.

---

## 6) Localizing plan copy
Plan names, summaries, and features can come from `config/saas.php` or the database catalog.

If you want to localize them:
- store per-locale copy in the database and render the correct locale, or
- replace plan copy with translation keys and use `__()` in the pricing view.

## 7) Add a new locale (checklist)
1) Add the locale to `config/saas.php` (`saas.locales.supported`)
2) Create `resources/lang/{locale}.json`
3) (Recommended) Add group files for auth/validation under `resources/lang/{locale}/...`
4) Confirm route generation is locale-aware:
   - marketing routes live under `/{locale}/...` (example: `/en/docs`)
5) Smoke-test:
   - Switch language via the selector
   - Refresh and confirm it persists (session + user profile)
