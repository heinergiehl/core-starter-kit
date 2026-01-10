# Tenancy

This document describes the optional multi-tenancy add-on package (`saas-kit-tenancy`).

## 1) Goals
- Add tenancy without forking the core kit
- Support tenant identification by:
  - subdomain: `{tenant}.example.com`
  - custom domain: `customer.com`
- Ensure strict data isolation (no tenant leaks)
- Allow per-tenant branding overrides (optional)

---

## 2) Tenant model (recommended)
- `tenants` table: `id`, `name`, `slug`, `created_at`
- `tenant_domains` table: `tenant_id`, `host`, `verification_token`, `verified_at`

---

## 3) Tenant identification

### 3.1 Subdomains
- Resolve `{tenant}` from host
- Map to a tenant record (e.g., `tenants.slug`)
- Set current tenant context early in middleware

### 3.2 Custom domains
- Host matches a verified domain record for a tenant
- Domain must be verified before activation (see below)

### 3.3 Middleware order
Tenant resolution MUST happen before:
- auth decisions that depend on tenant
- route model binding for tenant-scoped models
- Filament panel boot (if tenant-scoped UI)

---

## 4) Domain verification (custom domains)

Before attaching `customer.com` to a tenant, require proof of ownership.

Choose one method (or implement both):
1) DNS TXT record verification
2) HTTP token verification (serve a token file or path)

Minimum requirements:
- store verification token
- store `verified_at` timestamp
- do not route traffic to tenant until verified

Security notes:
- protect against domain takeover by requiring verification on changes
- re-verify on domain updates

---

## 5) Data isolation

### 5.1 Default strategy: single DB + tenant_id
- Every tenant-scoped table includes `tenant_id`
- Queries are automatically tenant-scoped via:
  - global scopes, and/or
  - the tenancy package mechanisms

### 5.2 Preventing tenant leaks
- Route model binding must be tenant-aware
- Policies must check tenant_id matches current tenant
- Filament resources/pages must be scoped to tenant

### 5.3 Tests (mandatory)
- create two tenants, two sets of records
- assert tenant A cannot access tenant B records
- test both subdomain and custom domain routing

---

## 6) Tenant-aware authentication

### 6.1 Users across tenants
Users may belong to multiple tenants/teams.

If no tenant is resolved from the domain:
- redirect to a tenant chooser
- or auto-redirect to last-used tenant

### 6.2 Tenant switching UX
- Provide a switcher for users with multiple tenants
- Store last active tenant per user

---

## 7) Billing and tenancy
Billing is still attached to the Team/Tenant.

Requirements:
- checkout metadata includes tenant/team ids
- webhook processing maps back to the correct tenant/team
- subscription quantity sync runs in tenant context (or is context-free but tenant-safe)

---

## 8) Deployment notes
- wildcard DNS required for subdomains
- TLS certificates for custom domains (use your chosen ingress/ACME approach)
- document reverse proxy settings for tenant hostnames
