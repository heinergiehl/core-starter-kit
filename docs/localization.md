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
1) user profile locale (if logged in)
2) session locale (set by the switcher)
3) `?lang=xx` query parameter

The `<x-locale-switcher />` component posts to `POST /locale` which stores the selection in session (and on the user record if signed in).

---

## 4) Translation files
All customer-facing strings are stored in JSON files:
```
resources/lang/de.json
```

Add a new language by creating a `{locale}.json` file and adding it to `config/saas.php`. If you want to edit English copy without touching templates, add an `en.json` file with your overrides.

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
