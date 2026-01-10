# Upgrading

This file documents upgrade steps between releases.

## Policy
- Follow SemVer (MAJOR.MINOR.PATCH)
- Every release that changes DB schema must include:
  - migration notes
  - any required backfills
  - rollback notes if possible
- Every release that changes billing behavior must include:
  - webhook changes
  - plan/entitlement changes
  - provider configuration changes
- Keep this file updated on every release

---

## How to upgrade (recommended)
1) Read the release notes in this file and `docs/versions.md`
2) Backup the database
3) Pull the new code
4) Install dependencies (`composer install`, `npm install`)
5) Run migrations (`php artisan migrate --force`)
6) Rebuild assets (`npm run build`)
7) Clear and rebuild caches if needed (`php artisan optimize`)
8) Verify background workers and webhooks

---

## Unreleased
- Add changes here during development.
- Use the template below to keep upgrades predictable.

---

## Release template

## x.y.z (YYYY-MM-DD)
### Breaking changes
- None

### Migrations and backfills
- None

### Billing changes
- None

### Config changes
- None

### New features
- None

### Fixes
- None

---

## 0.1.0 (2026-01-06)
Initial release.
- Auth + teams + invitations
- RBAC
- Billing domain with provider adapters
- Filament Admin/App panels
- Blog + SEO
- Theming
- Test suite + CI
