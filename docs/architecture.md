# Architecture

This document explains the architecture of the SaaS kit: boundaries, core domains, and the event-driven billing model.

## 1) Principles
- SSR-first by default (Blade + Filament/Livewire)
- Clear domain boundaries with minimal coupling
- Billing is event-driven; webhooks are the source of truth
- Entitlements are centralized and drive access
- Team/workspace is the billing entity

---

## 2) Core domain map

### Identity
- `User`
- Session auth, password reset, optional Socialite providers

### Organization
- `Team` (workspace)
- `team_user` membership pivot
- `TeamInvitation`

### Access control
- Team-scoped roles: `owner`, `admin`, `member`, `billing`
- Policies/gates and Filament authorization

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

### Tenancy (add-on)
- Tenant resolution (subdomain/custom domain)
- Isolation rules
- Per-tenant branding overrides

---

## 3) Request lifecycle (SSR)
1) Resolve tenant (if enabled)
2) Authenticate session
3) Resolve current team/workspace
4) Authorize action (policies, gates, Filament)
5) Execute domain service
6) Render SSR response

---

## 4) Entitlements

### 4.1 What are entitlements?
Entitlements are a computed capability set based on:
- active subscription (or one-time purchase)
- plan configuration
- seat count and limits
- feature flags

Examples:
- `max_seats`
- `seats_in_use`
- `can_invite_members`
- `can_use_feature_x`
- `storage_limit_mb`

### 4.2 Single source of truth
Implement one service:
- `EntitlementService::forTeam(Team $team): EntitlementsDTO`

Everything else reads from it:
- UI gating in Blade/Filament
- middleware protection for routes/actions
- API gating (if you expose any endpoints)

### 4.3 Seat counting rules (default)
- count active members (accepted, not soft-deleted)
- invites do not consume seats (configurable)
- over-seat behavior:
  - allow billing/settings access
  - block new invites and seat-consuming actions
  - show a banner with an upgrade CTA

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
- confirms the user is associated with the team
- polls an endpoint like `/billing/status` until canonical state reflects the new subscription/order
- provides a fallback "contact support" link

---

## 6) Background jobs and scheduling
- Webhook processing runs on the queue
- Seat changes enqueue a `SyncSeatQuantityJob(team_id)`
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
    Organization/
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
- Add tests for critical flows (billing, access control, tenancy)
