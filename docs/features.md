# Feature Matrix

This document is a high-level map of what ships in the kit today and what is still planned.

Status legend:

- Done: implemented in the core kit
- Partial: shipped, but missing some provider/UI coverage
- Planned: documented or on the roadmap, not implemented yet

## 1) Billing + Commerce

| Feature                            | Status | Notes                                                                        |
| ---------------------------------- | ------ | ---------------------------------------------------------------------------- |
| Subscriptions + one-time purchases | Done   | Webhook-driven subscriptions + orders.                                       |
| Stripe / Paddle adapters           | Done   | Checkout and webhook ingestion implemented for supported providers.          |
| Plan catalog in Admin              | Done   | `products` (plan definitions) + `prices` resources, DB-backed catalog.       |
| Discounts / coupons                | Done   | Coupons supported across providers and recorded in redemptions.              |
| Customer portal                    | Done   | Stripe portal supported; Paddle uses management URLs from webhook payloads.  |
| Transactions view                  | Done   | Orders + invoices resources available.                                       |
| Pay what you want (PWYW)           | Done   | Custom amount with min/max/default, suggested amounts, Stripe ad-hoc prices. |

## 2) Admin + Dashboard

| Feature                         | Status | Notes                                                  |
| ------------------------------- | ------ | ------------------------------------------------------ |
| Admin panel                     | Done   | Filament Admin Panel with billing + catalog resources. |
| SaaS metrics (MRR, churn, ARPU) | Done   | Admin dashboard widget with MRR/ARR/churn/ARPU.        |
| Roadmap / feedback board        | Done   | Public roadmap page + admin management.                |

## 3) Marketing + Content

| Feature                              | Status                 | Notes                                                                                                                                        |
| ------------------------------------ | ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| Blog + SEO (RSS, sitemap, OG tags)   | Done                   | Locale-aware blog posts, sitemap, RSS, OG tags, translation-aware `hreflang`, public author profiles, and shared article TOC rendering live. |
| Open Graph image generator           | Done                   | `/og` and blog OG images supported.                                                                                                          |
| Localization (marketing + dashboard) | Done                   | Locale switcher, localized marketing routes, customer-facing translations, admin stays English.                                              |
| Multilingual blog workflow           | Done                   | Blog posts support linked locale variants with per-locale slug, SEO fields, and publish state.                                               |
| Multilingual taxonomy landing pages  | Not planned by default | Categories and tags currently stay shared filter facets instead of separate localized SEO pages.                                             |

## 4) Auth + Email

| Feature             | Status  | Notes                                              |
| ------------------- | ------- | -------------------------------------------------- |
| Email/password auth | Done    | Laravel Breeze.                                    |
| Social login        | Done    | Google, GitHub, LinkedIn via Socialite.            |
| Email providers     | Partial | Mail drivers supported; branded templates minimal. |

## 5) Theming + Settings

| Feature                 | Status | Notes                           |
| ----------------------- | ------ | ------------------------------- |
| Branding + theme tokens | Done   | CSS tokens + brand settings UI. |
| Invoice settings        | Done   | Stored in branding settings.    |

## 6) DevOps

| Feature                  | Status  | Notes                                                                                             |
| ------------------------ | ------- | ------------------------------------------------------------------------------------------------- |
| Deploy workflow template | Partial | `.github/workflows/deploy.yml` ships as a VPS template; infra-specific customization is required. |
| Upgrade guide            | Done    | `UPGRADING.md` present.                                                                           |

---

## Next targets (recommended)

1. Harden webhook verification for Paddle.
2. Expand branded email templates + fixtures.
3. Add an optional one-command deploy script/runbook for common hosts.
