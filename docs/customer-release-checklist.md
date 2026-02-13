# Customer Release Checklist (v1)

Use this as a one-page launch gate before selling or handing off your SaaS built on this starter.

## 1) Environment baseline
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` points to your HTTPS domain
- [ ] `APP_KEY` is set and stable (with `APP_PREVIOUS_KEYS` if rotating keys)
- [ ] `SESSION_DRIVER`, `CACHE_STORE`, and `QUEUE_CONNECTION` are non-ephemeral for production

## 2) Runtime readiness commands
- [ ] `php artisan app:check-readiness`
- [ ] `php artisan app:check-readiness --strict`
- [ ] `php artisan billing:check-readiness`
- [ ] `php artisan billing:check-readiness --strict`

## 3) Billing + webhook readiness
- [ ] Stripe/Paddle secrets are configured and validated
- [ ] Webhook endpoints are reachable:
  - [ ] `POST /webhooks/stripe`
  - [ ] `POST /webhooks/paddle`
- [ ] Queue workers are supervised and running continuously
- [ ] Failed jobs storage is configured and monitored

## 4) Security baseline
- [ ] HTTPS is enforced at the edge/load balancer
- [ ] Session cookies are secure in production (`SESSION_SECURE_COOKIE=true`)
- [ ] CSP posture is explicitly chosen (`CSP_ALLOW_UNSAFE_EVAL=true|false`)
- [ ] Admin access policy is defined (roles, operators, emergency access)

## 5) Deploy reliability
- [ ] `php artisan migrate --force` rehearsed on staging with production-like data volume
- [ ] `php artisan config:cache`, `route:cache`, and `view:cache` succeed in the release artifact
- [ ] Rollback plan is documented and tested
- [ ] Backups are verified (DB + critical storage paths)

## 6) Product smoke pass (post-deploy)
- [ ] New user registration and login
- [ ] Password reset email flow
- [ ] Pricing page renders expected plans/providers
- [ ] One Stripe test checkout completes and settles
- [ ] One Paddle test checkout completes and settles
- [ ] Billing portal/invoice links load for paid customers

## 7) Buyer trust assets
- [ ] README matches actual shipped behavior
- [ ] Known limitations are documented clearly
- [ ] Support contact and response expectations are published
- [ ] Changelog/version tag created for this release

---

For billing-specific deep validation, also run `docs/billing-go-live-checklist.md`.

