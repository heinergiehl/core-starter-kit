# Feature Matrix

This document compares the current kit with the SaaSykit public feature list and highlights what is complete, partial, or still planned.

Status legend:
- Done: implemented in the core kit
- Partial: shipped, but missing some provider/UI coverage
- Planned: documented or on the roadmap, not implemented yet

## 1) Billing + Commerce
| Feature | Status | Notes |
| --- | --- | --- |
| Subscriptions + one-time purchases | Done | Webhook-driven subscriptions + orders. |
| Stripe / Paddle / Lemon Squeezy adapters | Done | Checkout, seat sync, and webhook ingestion implemented for all providers. |
| Product, plan, price catalog in Admin | Done | `products`, `plans`, `prices` resources + DB-backed catalog. |
| Discounts / coupons | Done | Coupons supported across providers and recorded in redemptions. |
| Customer portal | Done | Stripe + Lemon portal supported; Paddle uses management URLs from webhook payloads. |
| Transactions view | Done | Orders + invoices resources available. |

## 2) Teams + Access
| Feature | Status | Notes |
| --- | --- | --- |
| Team management + invites | Done | Teams, invitations, seat counting. |
| Team roles + permissions | Done | Owner/Admin/Billing/Member via policies. |
| Roles management UI | Planned | No dedicated admin role editor yet. |

## 3) Admin + Dashboard
| Feature | Status | Notes |
| --- | --- | --- |
| Admin panel | Done | Filament Admin Panel with billing + catalog resources. |
| SaaS metrics (MRR, churn, ARPU) | Done | Admin dashboard widget with MRR/ARR/churn/ARPU. |
| Roadmap / feedback board | Done | Public roadmap page + admin management. |

## 4) Marketing + Content
| Feature | Status | Notes |
| --- | --- | --- |
| Blog + SEO (RSS, sitemap, OG tags) | Done | Blog, sitemap, RSS live. |
| Open Graph image generator | Done | `/og` and blog OG images supported. |
| Localization (marketing + dashboard) | Done | Locale switcher, JSON translations, admin stays English. |

## 5) Auth + Email
| Feature | Status | Notes |
| --- | --- | --- |
| Email/password auth | Done | Laravel Breeze. |
| Social login | Done | Google, GitHub, LinkedIn via Socialite. |
| Email providers | Partial | Mail drivers supported; branded templates minimal. |

## 6) Theming + Settings
| Feature | Status | Notes |
| --- | --- | --- |
| Branding + theme tokens | Done | CSS tokens + brand settings UI. |
| Invoice settings | Done | Stored in branding settings. |

## 7) Tenancy
| Feature | Status | Notes |
| --- | --- | --- |
| Multi-tenancy (separate repo) | Partial | Documented in `docs/tenancy.md`, not in core kit. |

## 8) DevOps
| Feature | Status | Notes |
| --- | --- | --- |
| One-command deploy | Planned | No Deployer/Forge script included yet. |
| Upgrade guide | Done | `UPGRADING.md` present. |

---

## Next targets (based on SaaSykit parity)
1) Roles management UI.
2) Harden webhook verification for Paddle/Lemon.
3) Branded email templates.
4) One-command deploy workflow.
