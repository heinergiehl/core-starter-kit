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
- Enforce policies/gates on every privileged action
- Ensure Filament resources/pages also enforce authorization
- Never trust client input for `user_id`; derive from auth context

---

## 3) Billing webhooks (critical)
- Verify signature for each provider
- Store every webhook event in `webhook_events`
- Enforce idempotency with a unique `(provider, event_id)` constraint
- Process webhooks asynchronously via queue
- Provide retries and an audit trail in Admin Panel
- Never accept checkout redirect as proof of payment

---

## 4) Admin panel hardening
- Use a separate admin role or guard
- Restrict access by email domain or allowlist (optional)
- Log privileged actions
- Review admin accounts regularly

---

## 5) Data protection
- Encrypt sensitive columns where appropriate
- Store backups and test restores regularly
- Apply retention policies for PII
- Support user export/delete flows if required by compliance

---

## 6) File uploads
- Validate file size and MIME type
- Store uploads in a private disk when possible
- Serve via signed URLs for private content (optional)
- Virus scanning is optional but recommended for enterprise

---

## 7) Secrets management
- Keep baseline provider secrets in env vars
- Optional provider overrides may be stored in DB only if encrypted (for this kit: `payment_providers.configuration` is encrypted at cast level)
- Never store secrets in plaintext columns
- Rotate secrets regularly (document rotation steps)
- Avoid committing `.env` files

---

## 8) Logging and monitoring
- Do not log full webhook payloads if they include sensitive data (mask fields)
- Avoid logging secrets, tokens, credit card data
- Monitor failed webhooks, queue failures, and login abuse

---

## 9) Dependencies and updates
- Run `composer audit` and keep dependencies current
- Track security advisories
- Use `UPGRADING.md` for safe updates

---

## 10) Production checklist (minimum)
- `APP_DEBUG=false`
- HTTPS enabled
- Queue workers running
- Scheduler cron enabled
- DB backups configured
- Webhook endpoints accessible and verified
- Admin access restricted (IP allowlist optional)
- `php artisan app:check-readiness --strict` passes
- `php artisan billing:check-readiness --strict` passes

---

## 11) CSP + Alpine/Livewire note
- This kit uses Alpine/Livewire expressions (`x-data`, `x-show`, `@click`, etc.).
- Alpine expression evaluation typically needs `script-src 'unsafe-eval'` unless your frontend is fully CSP-safe without Alpine expression parsing.
- Toggle via env:
  - `CSP_ALLOW_UNSAFE_EVAL=true` (default in `.env.example`)
  - Set to `false` only after validating all interactive pages under your CSP policy.

If disabled too early, browser console will show `Alpine Expression Error` with CSP `unsafe-eval` violations.

---

## 12) Storage-backed brand assets
- Uploaded logos/favicons may be stored on the `public` disk (`storage/app/public/branding/...`).
- Ensure your deploy includes `php artisan storage:link` and correct file permissions.
- The app also provides a `/branding/{path}` fallback route to serve brand files when `/storage` is restricted by hosting config.
- If a referenced branding file is missing/unreadable, the fallback route now serves a default brand mark instead of returning a 500.

---

## 13) Production deploy guardrails
- Never allow deploys to leave the app in maintenance mode:
  - Use a shell trap/finalizer that always runs `php artisan up` on failure.
- Treat runtime filesystem state as part of the release:
  - Ensure `storage/framework/*`, `storage/logs`, `storage/app/public/branding`, and `bootstrap/cache` exist.
  - Ensure `storage/logs/laravel.log` exists and is writable.
- Align permissions with your web server group (for example `www-data`) and use setgid directories so new files remain group-writable.
- Run `php artisan app:check-readiness` during deploy to fail fast on runtime misconfiguration.
- Run `php artisan billing:check-readiness` during deploy to fail fast on missing critical billing secrets.
- Set CSP behavior explicitly per environment:
  - `CSP_ALLOW_UNSAFE_EVAL=true|false` in deployment env vars.
  - After changing CSP/env values, run `php artisan optimize:clear` and rebuild config cache.
