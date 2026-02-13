# Getting Started

Run the kit locally, learn the moving parts, and make the first changes most SaaS teams need.

## 1) Run it locally (10 minutes)

### Requirements
- PHP >= 8.2
- Composer >= 2
- Node.js >= 20.19 (or >= 22.12 for Vite 7)
- Postgres (recommended) or MySQL

### Install + configure
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Update `.env` with your DB credentials and (optional) billing/mail keys.

### Migrate + seed + storage
```bash
php artisan migrate --seed
php artisan storage:link
```

Seeded users:
- Admin (operators): `admin@example.com` / `password`
- Customer: `test@example.com` / `password`

### Run dev
```bash
# Terminal 1
composer dev
```

Windows note:
- `composer dev:windows` runs the same stack without Laravel Pail (Pail needs the `pcntl` extension).

## 2) URLs you should know
- Marketing (locale-prefixed): `/{locale}` (example: `/en`)
- Docs: `/{locale}/docs`
- Pricing: `/{locale}/pricing`
- Admin Panel (operators): `/admin`
- App Panel (customers): `/app`

## 3) Where to configure things
- Environment: `.env` and `.env.example`
- Core product config: `config/saas.php`
- Marketing templates + palettes: `config/template.php`
- OAuth providers: `config/services.php`

## 4) Billing setup (staging-first)
Billing is webhook-driven. A successful redirect is not proof of payment until the webhook is processed.

1) Configure provider keys in `.env`
2) Configure webhooks on Stripe/Paddle to hit:
   - `POST /webhooks/stripe`
   - `POST /webhooks/paddle`
3) Validate runtime + billing end-to-end:
```bash
php artisan app:check-readiness
php artisan app:check-readiness --strict
php artisan billing:check-readiness
php artisan billing:check-readiness --strict
```

Next: see `docs/billing.md` and run `docs/billing-go-live-checklist.md` before production billing changes.

## 5) Recommended reading order
1) [Architecture](architecture.md) (boundaries + entitlements + webhook pipeline)
2) [Billing](billing.md) (providers, catalog, webhooks, readiness checks)
3) [Security](security.md) (hardening + CSP notes)
4) [Testing](testing.md) (smoke pass + CI gates)
5) [Theming](theming.md) and [Localization](localization.md) (launch polish)
