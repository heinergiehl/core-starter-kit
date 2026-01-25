# Architecture

This document explains the architecture of the SaaS kit: boundaries, core domains, and the event-driven billing model.

## 1) Principles
- SSR-first by default (Blade + Filament/Livewire)
- Clear domain boundaries with minimal coupling
- Billing is event-driven; webhooks are the source of truth
- Entitlements are centralized and drive access
- User is the billing entity

---

## 2) Core domain map

### Identity
- `User`
- Session auth, password reset, optional Socialite providers

### Billing (canonical)
Canonical tables (provider-agnostic):
- `billing_customers`
- `products`, `plans`, `prices`
- `subscriptions`
- `orders`
- `webhook_events`
- `discounts`, `discount_redemptions`

Provider adapters:
- StripeAdapter
- PaddleAdapter
- LemonSqueezyAdapter

Catalog source:
- `config/saas.php` (default) or database-backed catalog (`BILLING_CATALOG=database`).

### Content
- Blog posts, categories/tags
- Sitemap + RSS

### Settings
- Theme tokens via CSS variables
- Branding settings (logo, support links)

## 3) Request lifecycle (SSR)
1) Authenticate session
2) Authorize action (policies, gates, Filament)
3) Execute domain service
4) Render SSR response

---

## 4) Entitlements

### 4.1 What are entitlements?
Entitlements are a computed capability set based on:
- active subscription (or one-time purchase)
- plan configuration
- feature flags

Examples:
- `can_use_feature_x`
- `storage_limit_mb`

### 4.2 Single source of truth
Implement one service:
- `EntitlementService::forUser(User $user): EntitlementsDTO`

Everything else reads from it:
- UI gating in Blade/Filament
- middleware protection for routes/actions
- API gating (if you expose any endpoints)

---

## 5) Billing webhook pipeline

### 5.1 Pipeline overview
1) Provider sends webhook to `/webhooks/{provider}`
2) Signature is verified
3) Event payload is saved to `webhook_events` (status = `received`)
4) A job is dispatched to process the event (idempotent)
5) Canonical subscription/order state is updated
6) Entitlements recompute on next request (or cached and invalidated)

### 5.2 Idempotency
- `webhook_events` has a unique `(provider, event_id)` constraint
- If the same event is delivered twice, processing is skipped or safely repeated
- Jobs must be safe to retry

### 5.3 Processing page
After checkout redirect, show a page that:
- confirms the user is authenticated
- polls an endpoint like `/billing/status` until canonical state reflects the new subscription/order
- provides a fallback "contact support" link

---

## 6) Background jobs and scheduling
- Webhook processing runs on the queue
- Use scheduler for recurring jobs (e.g., cleanup, emails)

---

## 7) Observability and audit
- `webhook_events` provides an audit log and retry surface
- Admin Panel should expose event status and error messages
- Domain services should log important transitions (minimal PII)

---

## 8) Folder structure (suggested)

```
app/
  Domain/
    Identity/
    Access/
    Billing/
    Content/
    Settings/
  Filament/
  Http/
  Jobs/
  Policies/
  Providers/
config/
docs/
database/
routes/
tests/
```

---

## 9) Extension guidelines
- Add new domains under `app/Domain` and keep dependencies explicit
- Avoid plan-name checks; use entitlements instead
- Keep provider-specific logic inside adapters
- Prefer DTOs for cross-domain data contracts
- Add tests for critical flows (billing, access control)
