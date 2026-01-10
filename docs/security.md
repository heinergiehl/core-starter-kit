# Security

This document is a practical checklist for securing the SaaS kit in production.

## 1) Authentication and sessions
- Enable CSRF for all state-changing SSR routes
- Rate limit login, registration, password reset
- Require email verification before paid/billing actions
- Consider MFA for admins and owners
- Use secure session settings in production:
  - HTTPS-only cookies
  - SameSite=Lax (or Strict if compatible)
  - reasonable session lifetime

---

## 2) Authorization (RBAC)
- Enforce policies/gates on every team-scoped action
- Ensure Filament resources/pages also enforce authorization
- Never trust client input for `team_id`; derive from current team context
- Validate role changes and invitation flows carefully

---

## 3) Billing webhooks (critical)
- Verify signature for each provider
- Store every webhook event in `webhook_events`
- Enforce idempotency with a unique `(provider, event_id)` constraint
- Process webhooks asynchronously via queue
- Provide retries and an audit trail in Admin Panel
- Never accept checkout redirect as proof of payment

---

## 4) Tenant security (if using tenancy)
- Tenant resolution must be early middleware
- Prevent tenant leaks with:
  - tenant-aware route model binding
  - global scopes
  - policy checks comparing tenant_id
- Custom domains:
  - require verification (DNS TXT or HTTP token)
  - protect against domain takeover on changes
  - keep a history/audit log (recommended)

---

## 5) Admin panel hardening
- Use a separate admin role or guard
- Restrict access by email domain or allowlist (optional)
- Log privileged actions
- Review admin accounts regularly

---

## 6) Data protection
- Encrypt sensitive columns where appropriate
- Store backups and test restores regularly
- Apply retention policies for PII
- Support user export/delete flows if required by compliance

---

## 7) File uploads
- Validate file size and MIME type
- Store uploads in a private disk when possible
- Serve via signed URLs for private content (optional)
- Virus scanning is optional but recommended for enterprise

---

## 8) Secrets management
- Provider secrets in env vars only
- Never store secrets in DB
- Rotate secrets regularly (document rotation steps)
- Avoid committing `.env` files

---

## 9) Logging and monitoring
- Do not log full webhook payloads if they include sensitive data (mask fields)
- Avoid logging secrets, tokens, credit card data
- Monitor failed webhooks, queue failures, and login abuse

---

## 10) Dependencies and updates
- Run `composer audit` and keep dependencies current
- Track security advisories
- Use `UPGRADING.md` for safe updates

---

## 11) Production checklist (minimum)
- `APP_DEBUG=false`
- HTTPS enabled
- Queue workers running
- Scheduler cron enabled
- DB backups configured
- Webhook endpoints accessible and verified
- Admin access restricted (IP allowlist optional)
