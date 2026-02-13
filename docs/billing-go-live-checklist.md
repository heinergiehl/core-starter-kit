# Billing Go-Live Checklist

Use this checklist before promoting billing changes to production.

## 1) Environment and Infra
- [ ] `APP_ENV=production` and `APP_DEBUG=false`.
- [ ] `php artisan config:cache` and `php artisan route:cache` succeed on release build.
- [ ] Queue workers are running and supervised (`php artisan queue:work`).
- [ ] Failed jobs storage is configured (`QUEUE_FAILED_DRIVER` not null).
- [ ] `php artisan app:check-readiness --strict` passes.
- [ ] `php artisan billing:check-readiness --strict` passes.

## 2) Catalog and Provider Mapping
- [ ] Every sellable price has a provider mapping in `price_provider_mappings`.
- [ ] `php artisan billing:publish-catalog stripe --apply --update` completed.
- [ ] `php artisan billing:publish-catalog paddle --apply --update` completed.
- [ ] `/pricing` shows expected plans and provider availability.

## 3) Stripe E2E (Staging)
- [ ] New subscription checkout completes and creates active `subscriptions` row.
- [ ] Subscription plan switch (upgrade/downgrade) works and updates `plan_key`.
- [ ] One-time purchase checkout creates paid `orders` row.
- [ ] One-time upgrade applies prior credit and charges delta only.
- [ ] One-time downgrade is blocked in self-serve with support CTA.

## 4) Paddle E2E (Staging)
- [ ] New subscription checkout completes and creates active `subscriptions` row.
- [ ] Subscription plan switch (upgrade/downgrade) works and updates `plan_key`.
- [ ] One-time purchase checkout creates paid `orders` row.
- [ ] One-time upgrade applies prior credit and charges delta only.
- [ ] One-time downgrade is blocked in self-serve with support CTA.

## 5) Webhook Safety and Replay
- [ ] Duplicate webhook delivery does not create duplicate canonical records.
- [ ] `webhook_events` transitions to `processed` for happy path.
- [ ] Failed event can be retried and reaches `processed`.
- [ ] Stale `processing` events are recoverable and redispatched.

## 6) One-Time Upgrade Credit Hardening
- [ ] Upgrade credit amount equals prior owned one-time tier value.
- [ ] Retrying checkout reuses existing auto-upgrade credit discount when still valid.
- [ ] Coupon + auto-upgrade credit combination is rejected with clear UI message.
- [ ] Billing success UI shows charged amount and plan value when they differ.

## 7) Observability and Alerting
- [ ] Checkout logs contain correlation context (`request_id`, `user_id`, `provider`, `plan_key`, `price_key`).
- [ ] Webhook logs include `provider`, `event_id`, `event_type`, dispatch decision.
- [ ] Alert on sustained webhook failure rate (for example: >5 failed events in 10 minutes).
- [ ] Alert on checkout failure spikes (for example: provider checkout failures >2% over 15 minutes).

## 8) Regression Suite Gate
- [ ] `vendor/bin/pint --test` passes.
- [ ] `php artisan test tests/Feature/Billing tests/Unit/Domain/Billing` passes.
- [ ] `php artisan test tests/Feature/Auth/SocialAuthTest.php` passes.

## 9) Rollback Plan
- [ ] Previous deploy artifact is available for immediate rollback.
- [ ] Rollback command/runbook is documented for your platform.
- [ ] Database migrations in this release are backward-safe or have rollback steps documented.
- [ ] Team knows who owns incident response during release window.
