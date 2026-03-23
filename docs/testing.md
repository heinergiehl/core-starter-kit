# Testing Guide

This guide covers a practical testing workflow for the kit: automated tests plus a focused manual smoke pass.

## 1) Prep
- Ensure `.env` is configured (DB, `APP_URL`, `MAIL_MAILER=log`).
- Run migrations + seed data:
```bash
php artisan migrate --seed
php artisan storage:link
```
- Clear caches after env changes:
  - `php artisan optimize:clear`
- Demo billing catalog + discounts seed only in `local`/`testing`. Set `BILLING_CATALOG=database` to see DB-backed plans on `/pricing`.

---

## 2) Automated tests
Run the default test suite:
```bash
php artisan test
```

Or via Composer (clears config first):
```bash
composer test
```

Optional frontend build check:
```bash
npm run build
```

---

## 3) Manual smoke checklist

### Public pages
- `/` redirects to the default locale marketing page.
- `/{locale}` marketing page renders, no console errors.
- `/{locale}/pricing` shows plans and provider tabs.
- `/{locale}/blog` and `/{locale}/blog/{slug}` render.
- Long-form blog posts show a working author card, active table of contents, and scrolling TOC on desktop.
- `/{locale}/roadmap` renders, voting requires auth.
- `/{locale}/rss.xml`, `/sitemap.xml`, and `/og` endpoints return successfully.
- `/branding/shipsolid-s-mark.svg` returns 200.
- `/branding/does-not-exist.png` should not return 500 (fallback asset expected).

### Multilingual blog flow
- Create an English post and confirm it renders at `/{locale}/blog/{slug}`.
- Create a second locale translation from the admin and confirm it renders at its translated slug.
- Switch locale from the post page and confirm the redirect lands on the translated slug, not the source slug.
- Confirm a locale without a translation does not render a fake alternate page.
- Confirm filtered blog archive pages (`?category=...`, `?tag=...`, `?search=...`) are `noindex,follow` and canonicalize back to the main archive.

### Auth + profile
- Register new user.
- Verify email (check `storage/logs/laravel.log`).
- Login / logout.
- Update profile info and password.

### Locale flow
- Switch language via the selector (marketing + `/dashboard` + `/app`).
- Reload and confirm the choice persists.

### App Panel (`/app`)
- Confirm account details render for the signed-in user.
- Verify entitlements are reflected in the UI.

### Admin Panel (`/admin`)
- Create products (plans) and prices.
- Confirm pricing page reflects DB catalog when `BILLING_CATALOG=database`.
- Create a discount and verify the coupon field appears.
- Create a blog post translation draft and confirm locale-specific slug/SEO state works as expected.
- Update a user's public author byline/title/bio and confirm blog posts assigned to that author render the new public profile.

### Billing test flow
- Configure Stripe/Paddle test keys in `.env`.
- Start checkout from `/pricing`.
- Verify webhook logs in Admin.

---

## 4) Known platform notes
- Windows: use `composer dev:windows` (Pail needs `pcntl`).
- Vite requires Node `20.19+` or `22.12+`.
