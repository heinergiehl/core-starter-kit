# SaaS Kit (SSR-first Laravel)

A production-grade, SSR-first SaaS starter kit that helps you ship a reliable product with a clean architecture and maintainable billing.

Highlights:
- SSR-first UI (Blade + Filament + Livewire) with minimal JS
- Auth (email/password + social login)
- Billing adapters for Stripe and Paddle
- Admin Panel (operators) + App Panel (customers)
- Plan catalog in Admin (`products` + `prices`, optional DB-backed)
- Discount/coupon support (Stripe/Paddle checkout)
- SaaS metrics (MRR, ARR, churn, ARPU)
- Roadmap + feedback board
- Blog + SEO (sitemap, OG tags, RSS, OG images)
- Theming/branding (CSS variables + light/dark)
- Strong test suite + CI

This README is the quickstart. Detailed docs live in `docs/`.

## Contents
- [Quickstart](#quickstart)
- [Environment variables](#environment-variables)
- [Local development](#local-development)
- [Billing providers](#billing-providers)
- [Theming](#theming)
- [Localization](#localization)
- [Deploy](#deploy)
- [Docs](#docs)
- [License](#license)

---

## Quickstart

### 1) Requirements
- PHP >= 8.2
- PHP ext-intl (required by Filament)
- Composer >= 2
- Node.js >= 20.19 (or >= 22.12 for Vite 7)
- Postgres (recommended) or MySQL
- A mail driver (use `log` for local)

### 2) Install
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### 3) Configure
- Set DB credentials in `.env`
- Choose billing providers to enable in `config/saas.php`
- Configure mail (local: `MAIL_MAILER=log`)
- Validate billing/env secrets before launch:
  - `php artisan billing:check-readiness`
  - `php artisan billing:check-readiness --strict` (CI/staging gate)

### 4) Setup database
```bash
php artisan migrate --seed
php artisan storage:link
```

Seeded users:
- Admin: `admin@example.com` (password: `password`)
- Customer: `test@example.com` (password: `password`)

### 5) Run dev
```bash
# Terminal 1
composer dev

# If you do not have composer scripts:
# php artisan serve
# npm run dev
# php artisan queue:work
```

Open the app at the URL printed by `artisan serve` (default `http://127.0.0.1:8000`).

Windows note:
- `composer dev:windows` runs the same stack without Laravel Pail (Pail requires the `pcntl` extension).

---

## Environment variables

Minimum local `.env` keys (examples):
```dotenv
APP_NAME="SaaS Kit"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=saas_kit
DB_USERNAME=postgres
DB_PASSWORD=postgres

MAIL_MAILER=log
```

Billing provider env vars vary; see `docs/billing.md` for exact keys and webhook setup.

---

## Local development

### Frontend assets
- Uses Vite. Run `npm run dev` during development.
- Use `npm run build` for production assets.

### Filament panels
- Admin Panel: platform operators
- App Panel: customers

Routes:
- Admin Panel: `/admin`
- App Panel: `/app`

### Blog + SEO
- Sitemap: `/sitemap.xml`
- RSS feed: `/rss.xml`
- OG tags are populated for marketing and blog pages, with dynamic images at `/og`.

### Project structure (overview)
This kit follows a domain-first structure to keep boundaries clear. See `docs/architecture.md` for details.

---

## Billing providers

This kit supports:
- Stripe
- Paddle

Billing is webhook-driven. Webhooks must be configured for your environment. See `docs/billing.md`.
Set plan price IDs in `.env` (see `.env.example`) to enable checkout on the pricing page.
Use `BILLING_CATALOG=database` to manage plans via `products` + `prices` in the Admin Panel.

---

## Theming

Branding is driven by CSS variables and applies globally.
See `docs/theming.md`.

---

## Localization

Customer-facing pages (marketing, auth, dashboard, App Panel) are locale-aware via JSON translation files.
The Admin Panel stays in English by default.

See `docs/localization.md`.

---

## Deploy

High level checklist:
1) Set `APP_ENV=production`, `APP_DEBUG=false`
2) Configure DB, cache, queue, mail
3) Build assets: `npm run build`
4) Run migrations: `php artisan migrate --force`
5) Run queue workers
6) Configure scheduler cron
7) Configure billing webhooks (Stripe/Paddle)

See:
- `docs/security.md` for hardening
- `docs/billing.md` for webhooks
- `docs/versions.md` for pinned dependency versions

---

## Docs
- `docs/architecture.md` - boundaries, entitlements, webhook pipeline
- `docs/billing.md` - providers, env/config, webhooks
- `docs/features.md` - parity checklist and missing features
- `docs/theming.md` - design tokens, theming, branding
- `docs/localization.md` - locale setup and translation workflow
- `docs/testing.md` - automated + manual QA checklist
- `docs/security.md` - security checklist, webhook safety, domain verification
- `docs/versions.md` - pinned major versions
- `UPGRADING.md` - release upgrade notes

---

## License
MIT (update to your chosen license if needed).
