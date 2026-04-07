# Getting Started

Run the kit locally, learn the moving parts, and make the first changes most SaaS teams need.

---

## 1) Run it locally (10 minutes)

### Requirements

- PHP >= 8.2 with extensions: mbstring, openssl, pdo, tokenizer, xml, ctype, json
- Composer >= 2
- Node.js >= 20.19 (or >= 22.12 for Vite 7)
- Postgres (recommended) or MySQL 8+

### Install + configure

```bash
git clone <your-repo-url> my-saas
cd my-saas
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Update `.env` with your DB credentials and (optional) billing/mail keys.

### Migrate + seed + build

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
```

Seeded users:
| Email | Password | Role |
|-------|----------|------|
| `admin@example.com` | `password` | Admin (full access) |
| `test@example.com` | `password` | Customer |

### Run dev

```bash
# Terminal 1 — starts PHP server, Vite, queue, and log watcher
composer dev
```

Windows note:

- `composer dev:windows` runs the same stack without Laravel Pail (Pail needs the `pcntl` extension).

## 2) URLs you should know

- Marketing (locale-prefixed): `/{locale}` (example: `/en`)
- Blog: `/{locale}/blog`
- Blog post: `/{locale}/blog/{slug}`
- Docs: `/{locale}/docs`
- Pricing: `/{locale}/pricing`
- RSS: `/{locale}/rss.xml`
- Admin Panel (operators): `/admin`
- App Panel (customers): `/app`

## 3) Where to configure things

- Environment: `.env` and `.env.example`
- Core product config: `config/saas.php`
- Marketing templates + palettes: `config/template.php`
- OAuth providers: `config/services.php`

## 4) Billing setup (staging-first)

Billing is webhook-driven. A successful redirect is not proof of payment until the webhook is processed.

1. Configure provider keys in `.env`
2. Configure webhooks on Stripe/Paddle to hit:
    - `POST /webhooks/stripe`
    - `POST /webhooks/paddle`
3. Validate runtime + billing end-to-end:

```bash
php artisan app:check-readiness
php artisan app:check-readiness --strict
php artisan billing:check-readiness
php artisan billing:check-readiness --strict
```

Next: see `docs/billing.md` and run `docs/billing-go-live-checklist.md` before production billing changes.

## 5) Recommended reading order

1. [Architecture](architecture.md) — boundaries, entitlements, webhook pipeline
2. [Billing](billing.md) — providers, catalog, webhooks, readiness checks, PWYW
3. [Security](security.md) — hardening, CSP notes
4. [Testing](testing.md) — smoke pass, CI gates
5. [Theming](theming.md) and [Localization](localization.md) — launch polish, locale-aware blog, SEO
6. [Blog Content Sync](blog-content-sync.md) — markdown structure, multilingual import, admin behavior
7. [Features](features.md) — full feature matrix

## 6) Common first-run issues

| Symptom                    | Fix                                                                          |
| -------------------------- | ---------------------------------------------------------------------------- |
| Vite manifest not found    | Run `npm run build` or keep `npm run dev` running                            |
| Storage link error         | `php artisan storage:link` (needs admin on Windows)                          |
| Billing webhooks 404       | Ensure `STRIPE_WEBHOOK_SECRET` / `PADDLE_WEBHOOK_SECRET` are set in `.env`   |
| Social login redirect loop | Confirm `APP_URL` matches the domain your browser uses                       |
| Queue jobs not processing  | Start the queue worker: `php artisan queue:work` (or use `composer dev`)     |
| Styles look broken         | Clear browser cache; run `npm run build`; check `config/template.php` preset |

## 7) Using this kit as open-source (dogfooding)

If you publish this kit publicly and want to accept voluntary contributions ("pay what you want"), the billing system has first-class support:

1. Create a product + price in Admin → Products with **Allow custom amount** enabled
2. Set a minimum (e.g. $5) and optional suggested amounts ($10, $25, $50)
3. The checkout page automatically adapts: shows a friendly contribution prompt instead of a fixed price
4. See [Billing → Section 17: Pay What You Want](billing.md) for the full setup guide
