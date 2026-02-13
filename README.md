# SaaS Kit (SSR-first Laravel)

A production-oriented, SSR-first Laravel SaaS starter kit for teams that want to launch quickly with clean architecture and maintainable billing.

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
For v1 launch confidence, use `docs/customer-release-checklist.md` plus `docs/billing-go-live-checklist.md`.

## Contents
- [Positioning (v1)](#positioning-v1)
- [Quickstart](#quickstart)
- [Environment variables](#environment-variables)
- [Local development](#local-development)
- [Billing providers](#billing-providers)
- [Theming](#theming)
- [Localization](#localization)
- [Known limitations (v1)](#known-limitations-v1)
- [Deploy](#deploy)
- [Docs](#docs)
- [License](#license)

---

## Positioning (v1)

This kit is designed to be a high-quality starting point for:
- teams launching their first paid SaaS
- agencies building client SaaS products
- developers who want an SSR-first stack with billing/auth/admin already integrated

This kit is not positioned as a fully managed enterprise platform out of the box. You still need to own production infrastructure, monitoring, and deployment operations.

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
- Production mail provider scope in v1: `postmark` and `ses` (configured in Admin > Settings > Email Settings)
- Validate runtime + billing config before launch:
  - `php artisan app:check-readiness`
  - `php artisan app:check-readiness --strict` (CI/staging gate)
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
For staging subscription rehearsals, seed recurring plans with `php artisan billing:seed-subscription-plans --force`.

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

## Known limitations (v1)

- Queue-driven billing: webhook processing depends on healthy workers; if workers stop, billing state updates will lag.
- Deploy workflow is a template: `.github/workflows/deploy.yml` must be customized for your infrastructure and secrets.
- CSP is pragmatic by default: `unsafe-inline` is enabled and `CSP_ALLOW_UNSAFE_EVAL` may still be needed for some Alpine/Livewire flows.
- Admin localization is intentionally limited: `/admin` remains English-first by default.
- Email provider scope is intentionally narrow in v1: Postmark + SES are first-class; other drivers require custom integration.
- Zero-downtime rollout strategy is not bundled: you should add your own blue/green or rolling deployment pattern if required.

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
8) Validate runtime + billing config:
   - `php artisan app:check-readiness` (or `--strict`)
   - `php artisan billing:check-readiness` (or `--strict`)
9) Ensure writable runtime paths exist (`storage/*`, `bootstrap/cache`) and run `php artisan storage:link`
10) Set environment-level deploy variables explicitly:
   - `CSP_ALLOW_UNSAFE_EVAL` for your CSP posture
   - `WEB_GROUP` to match your PHP-FPM/Nginx group (usually `www-data`)
11) Run `docs/billing-go-live-checklist.md` end-to-end on staging before production billing changes

See:
- `docs/security.md` for hardening
- `docs/billing.md` for webhooks
- `docs/versions.md` for pinned dependency versions

---

## Docs
When running locally, you can browse these in the app at `/{locale}/docs` (example: `/en/docs`).

- `docs/customer-release-checklist.md` - one-page customer launch checklist (runtime, billing, security, smoke tests)
- `docs/getting-started.md` - start here: local setup + where to configure things
- `docs/architecture.md` - boundaries, entitlements, webhook pipeline
- `docs/billing.md` - providers, env/config, webhooks
- `docs/billing-go-live-checklist.md` - release gate for subscription + one-time billing flows
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
