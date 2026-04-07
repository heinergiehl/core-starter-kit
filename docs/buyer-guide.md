# Buyer Guide

> A comprehensive overview for developers and teams evaluating or onboarding with this Laravel SaaS Starter Kit.

---

## What You Get

This kit is a production-grade foundation for launching a paid SaaS product. It handles the parts that take months to build correctly — billing, authentication, multi-locale marketing, admin dashboards, and theming — so you can focus on your product's unique value.

### Core Capabilities

| Domain             | What's Included                                                                                                                                                                                                              |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Billing**        | Stripe + Paddle adapters, subscription + one-time purchases, plan catalog (config or database), discount/coupon support, customer portal links, transaction/invoice views, upgrade credit handling, webhook-driven lifecycle |
| **Authentication** | Email/password (Breeze), social login (Google, GitHub, LinkedIn), remember-me, email verification, password reset                                                                                                            |
| **Access Control** | Entitlements system for feature-gating and usage limits, RBAC with roles and policies, optional GitHub repo access post-purchase                                                                                             |
| **Admin Panel**    | Filament-powered operator dashboard with SaaS metrics (MRR, ARR, churn, ARPU), product/price/discount CRUD, user management, blog management                                                                                 |
| **Customer Panel** | Filament-powered customer dashboard for billing, invoices, and account settings                                                                                                                                              |
| **Marketing**      | SEO-optimized pricing page, multi-locale blog with per-locale slugs, sitemap, RSS feeds, OG tags, OG image generator                                                                                                         |
| **Theming**        | 8 CSS template presets (default, void, aurora, prism, velvet, frost, ember, ocean), admin branding UI for logo/favicon/colors, light + dark mode, email color branding                                                       |
| **Localization**   | 4 locales out of the box (en, de, es, fr), locale switcher, localized email templates, per-locale blog posts with `hreflang` support                                                                                         |
| **DevOps**         | PHPUnit test suite, Pint code formatting, GitHub Actions CI template, readiness checks (`app:check-readiness`, `billing:check-readiness`), Telescope for debugging                                                           |

### Tech Stack

```
Backend:     PHP 8.4, Laravel 12, Filament 4, Livewire 3
Billing:     Stripe SDK, Paddle (custom adapter)
Frontend:    Blade + Alpine.js 3 + Tailwind CSS 3
Bundler:     Vite 7
Database:    PostgreSQL (recommended) or MySQL
Auth:        Laravel Breeze + Socialite 5
Testing:     PHPUnit 11
Tooling:     Pint, Telescope, Pail
```

---

## Quick Start

### Prerequisites

- PHP 8.2+ with standard extensions (mbstring, openssl, pdo, tokenizer, xml, ctype, json)
- Composer 2
- Node.js 18+ with npm
- PostgreSQL 14+ (or MySQL 8+)
- A Stripe and/or Paddle account (for billing)

### Installation

```bash
# Clone and install
git clone <your-repo-url> my-saas
cd my-saas
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed

# Build frontend assets
npm run build

# Start the dev server
php artisan serve
```

### Seeded Accounts

| Email               | Password   | Role  |
| ------------------- | ---------- | ----- |
| `admin@example.com` | `password` | Admin |
| `test@example.com`  | `password` | User  |

### Key URLs

| URL          | Purpose                        |
| ------------ | ------------------------------ |
| `/`          | Marketing homepage             |
| `/pricing`   | Pricing page                   |
| `/login`     | Login                          |
| `/register`  | Registration                   |
| `/blog`      | Public blog                    |
| `/admin`     | Admin panel (admin users only) |
| `/app`       | Customer dashboard             |
| `/telescope` | Debug dashboard (local only)   |

---

## Architecture at a Glance

The kit follows a **domain-driven directory structure** within Laravel's conventions:

```
app/
├── Domain/
│   ├── Billing/    # Plans, prices, checkout, webhooks, providers
│   ├── Content/    # Blog posts, translations, markdown sync
│   ├── Identity/   # Users, roles, social auth, entitlements
│   └── Settings/   # App config, branding, feature flags
├── Filament/       # Admin + App panel resources
├── Http/           # Controllers, middleware, form requests
├── Livewire/       # Interactive components
├── Models/         # Eloquent models
├── Enums/          # Application enumerations
└── Policies/       # Authorization policies
```

**Key design principles:**

- **SSR-first**: Blade + Livewire with Alpine.js for interactivity. No SPA complexity.
- **Provider-agnostic billing**: Stripe and Paddle share the same canonical data model. Switching providers doesn't require rewriting business logic.
- **Entitlements over plan checks**: Gate access by capabilities (`can_export`, `max_projects: 10`), not by plan name. This decouples your feature logic from your pricing strategy.
- **Webhook-driven state**: All billing state changes flow through verified, idempotent webhooks — not client-side callbacks.

---

## Billing Setup

### Provider Configuration

Add your provider keys in `.env`:

```env
# Stripe
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Paddle (optional)
PADDLE_VENDOR_ID=...
PADDLE_API_KEY=...
PADDLE_WEBHOOK_SECRET=...
```

### Plan Catalog

Plans can be managed via:

1. **Config file** (`config/saas.php`) — Version-controlled, best for simple setups
2. **Database** (Admin Panel > Products) — Dynamic, best for teams that iterate on pricing

Each plan supports:

- **Fixed-price subscriptions** — Monthly, yearly, or custom intervals
- **One-time purchases** — Lifetime access with upgrade paths
- **Pay-what-you-want** — Custom amounts with min/max/default and suggested amounts
- **Usage-based billing** — Base fee + metered usage with included units, overage billing, and limit behaviors

### Webhook Endpoints

Register these webhook URLs with your provider:

| Provider | URL                                     |
| -------- | --------------------------------------- |
| Stripe   | `https://yourdomain.com/stripe/webhook` |
| Paddle   | `https://yourdomain.com/paddle/webhook` |

> See [docs/billing.md](billing.md) for the complete billing architecture reference.
> See [docs/billing-go-live-checklist.md](billing-go-live-checklist.md) before going live with real payments.

---

## Customization

### Theming

The kit uses CSS variables for theming, giving you full control over colors, typography, and spacing without touching component code.

**Quick customization:**

1. Go to Admin Panel > Settings > Branding
2. Upload your logo, favicon, and set brand colors
3. Choose a template preset or create your own

**Available presets:** Default, Void, Aurora, Prism, Velvet, Frost, Ember, Ocean

All presets support light and dark mode automatically. See [docs/theming.md](theming.md) for CSS variable reference and custom preset creation.

### Localization

Add a new locale:

1. Create `resources/lang/{locale}.json` with your translations
2. Add the locale code to `config/app.php` → `available_locales`
3. Run `php artisan blog:sync` if you have locale-specific blog content

The kit ships with **en**, **de**, **es**, **fr** out of the box — covering UI strings, email templates, and validation messages.

See [docs/localization.md](localization.md) for the full i18n reference.

### Blog & Content

Blog posts are managed through:

- **Markdown files** in `content/blog/` — synced to the database via `php artisan blog:sync`
- **Admin Panel** — Direct CRUD with rich text editor

Posts support multi-locale translations, SEO metadata, featured images, and per-locale slugs with `hreflang` tags.

See [docs/blog-content-sync.md](blog-content-sync.md) for markdown conventions and YAML front matter.

---

## Testing

The kit includes a comprehensive PHPUnit test suite covering billing flows, authentication, authorization, and content management.

```bash
# Run all tests
php artisan test --compact

# Run a specific test file
php artisan test --compact tests/Feature/Billing/PricingPlanChangeTest.php

# Run with a filter
php artisan test --compact --filter=testCheckoutFlow
```

See [docs/testing.md](testing.md) for the manual smoke test checklist and CI integration notes.

---

## Production Checklist

Before launching, review both checklists:

1. **[Customer Release Checklist](customer-release-checklist.md)** — Environment, security, deploy reliability, smoke tests
2. **[Billing Go-Live Checklist](billing-go-live-checklist.md)** — Provider mapping, E2E payment flows, webhook verification, observability

### Readiness Commands

```bash
# General application readiness
php artisan app:check-readiness

# Billing-specific readiness
php artisan billing:check-readiness
```

---

## Documentation Index

| Document                                                       | Purpose                                                     |
| -------------------------------------------------------------- | ----------------------------------------------------------- |
| [getting-started.md](getting-started.md)                       | First-time setup walkthrough                                |
| [architecture.md](architecture.md)                             | System design, domain boundaries, folder structure          |
| [billing.md](billing.md)                                       | Billing architecture, provider adapters, catalog management |
| [billing-go-live-checklist.md](billing-go-live-checklist.md)   | Pre-production billing validation                           |
| [security.md](security.md)                                     | Production hardening checklist                              |
| [testing.md](testing.md)                                       | Test suite, manual smoke tests, CI                          |
| [theming.md](theming.md)                                       | CSS variables, presets, admin branding                      |
| [localization.md](localization.md)                             | i18n setup, locale management, blog translations            |
| [blog-content-sync.md](blog-content-sync.md)                   | Markdown blog workflow                                      |
| [email-client-qa.md](email-client-qa.md)                       | Email template testing                                      |
| [customer-release-checklist.md](customer-release-checklist.md) | Launch readiness gate                                       |
| [features.md](features.md)                                     | Feature matrix with implementation status                   |
| [versions.md](versions.md)                                     | Dependency versions and compatibility                       |

---

## Support

If you encounter issues or have questions:

- Review the documentation index above — most topics are covered in detail
- Check `php artisan app:check-readiness` for environment issues
- Use Telescope (`/telescope`) in local development for debugging
- Run the test suite to validate your changes: `php artisan test --compact`

---

## Open-Source / Dogfooding

This kit can be published as an open-source project while still accepting voluntary revenue through the built-in **Pay What You Want** billing feature.

**How it works:**

1. Create a product in Admin → Products (e.g. "Support this project")
2. Add a price with **Allow custom amount** enabled, a minimum (e.g. $5), and suggested amounts ($10, $25, $50)
3. Link that price on your pricing page — the checkout adapts automatically with a friendly contribution prompt
4. Contributors pay via Stripe; you receive real revenue with zero friction

This is ideal for open-source SaaS kits: the product is free to clone and use, but the checkout flow demonstrates the billing system while generating optional income for the maintainer.

See [Billing → Pay What You Want](billing.md) for the complete setup reference.
