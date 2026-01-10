# Testing Guide

This guide covers a practical testing workflow for the kit: automated tests plus a focused manual smoke pass.

## 1) Prep
- Ensure `.env` is configured (DB, `APP_URL`, `MAIL_MAILER=log`).
- Run migrations + seed data:
- `php artisan migrate --seed`
- `php artisan storage:link`
- Clear caches after env changes:
  - `php artisan optimize:clear`
- Demo billing catalog + discounts seed only in `local`/`testing`. Set `BILLING_CATALOG=database` to see DB-backed plans on `/pricing`.

---

## 2) Automated tests
Run the default test suite:
```bash
php artisan test
```

Optional frontend build check:
```bash
npm run build
```

---

## 3) Manual smoke checklist

### Public pages
- `/` marketing page renders, no console errors.
- `/pricing` shows plans and provider tabs.
- `/blog` and `/blog/{slug}` render.
- `/roadmap` renders, voting requires auth.
- `/rss.xml`, `/sitemap.xml`, `/og` endpoints return successfully.

### Auth + profile
- Register new user.
- Verify email (check `storage/logs/laravel.log`).
- Login / logout.
- Update profile info and password.

### Locale flow
- Switch language via the selector (marketing + `/dashboard` + `/app`).
- Reload and confirm the choice persists.

### App Panel (`/app`)
- Select or create a team.
- Invite a member.
- Confirm seat limits update correctly.

### Admin Panel (`/admin`)
- Create products, plans, and prices.
- Confirm pricing page reflects DB catalog when `BILLING_CATALOG=database`.
- Create a discount and verify the coupon field appears.

### Billing test flow
- Configure Stripe/Paddle/Lemon test keys in `.env`.
- Start checkout from `/pricing`.
- Verify webhook logs in Admin.

---

## 4) Known platform notes
- Windows: use `composer dev:windows` (Pail needs `pcntl`).
- Vite requires Node `20.19+` or `22.12+`.
