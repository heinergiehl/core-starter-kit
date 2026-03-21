# Localization

This kit ships with a localization setup focused on customer-facing surfaces and SEO-safe public URLs. The admin panel stays in English by default.

## 1) What is localized
- Marketing pages under `/{locale}/...` (`/{locale}`, `/{locale}/pricing`, `/{locale}/solutions`, `/{locale}/roadmap`)
- Blog index, blog posts, and RSS under `/{locale}/blog...`
- Auth screens and the customer dashboard (`/dashboard`)
- App Panel (customer-facing Filament panel at `/app`)

The Admin Panel (`/admin`) is intentionally left in English to reduce translation and support overhead.

---

## 2) Public content model
This kit now separates two localization concerns:

- Static UI copy uses Laravel translation files in `resources/lang`
- Blog posts use database-backed locale variants linked by `translation_group_uuid`

That means each blog translation has its own:
- `locale`
- `slug`
- SEO title / description
- publish state
- publish date

This is intentional. It keeps multilingual SEO clean because each locale version is a real page, not a translated shell around the same record.

Blog categories and tags are currently shared taxonomy records used as archive filters. They are not separate multilingual landing pages yet.

---

## 3) Supported locales
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

## 4) Locale selection flow
Locale resolution happens in `app/Http/Middleware/SetLocale.php`:
1) locale from URL route parameter (`/{locale}/...`)
2) user profile locale (if logged in)
3) session locale (set by the switcher)
4) `?lang=xx` query parameter
5) browser `Accept-Language` header

The `<x-locale-switcher />` component posts to `POST /locale` which stores the selection in session (and on the user record if signed in).

---

## 5) Translation files
All app-level translation files are loaded from:
```
resources/lang
```

Use both:
- JSON files for UI copy strings, e.g. `resources/lang/es.json`
- PHP group files for framework messages (`auth`, `validation`, `passwords`, `pagination`, etc.), e.g. `resources/lang/es/validation.php`

Add a new locale by creating `{locale}.json`, adding the locale to `config/saas.php`, and (recommended) adding PHP group files for validation/auth flows.

---

## 6) Notes for Filament
- `/app` includes the locale middleware so your custom App Panel labels can use `__()`.
- `/admin` does not include the locale middleware. Keep it in English unless you explicitly want to translate your operator UI.
- The blog editor in `/admin` supports locale-specific blog posts, but the operator UI itself remains English.

If you want to localize Filament itself, publish the Filament language files and add the locale middleware to the admin panel provider.

---

## 7) SEO behavior
Public localized pages follow these rules:

- each language version uses its own URL
- `hreflang` is emitted only for real published blog translations
- blog post canonicals are self-referencing per locale
- filtered blog archive pages stay `noindex,follow` and canonicalize to the root blog archive
- sitemap entries include only existing published blog translations

This avoids the common multilingual SEO mistake of exposing `/{locale}/...` URLs for content that does not actually exist in that language.

---

## 8) Localizing plan copy
Plan names, summaries, and features can come from `config/saas.php` or the database catalog.

If you want to localize them:
- store per-locale copy in the database and render the correct locale, or
- replace plan copy with translation keys and use `__()` in the pricing view.

## 9) Add a new locale (checklist)
1) Add the locale to `config/saas.php` (`saas.locales.supported`)
2) Create `resources/lang/{locale}.json`
3) (Recommended) Add group files for auth/validation under `resources/lang/{locale}/...`
4) Confirm route generation is locale-aware:
   - marketing routes live under `/{locale}/...` (example: `/en/docs`)
   - blog routes live under `/{locale}/blog` and `/{locale}/blog/{slug}`
5) Smoke-test:
   - Switch language via the selector
   - Refresh and confirm it persists (session + user profile)
   - Create one translated blog post and confirm the locale switcher resolves to the translated slug
