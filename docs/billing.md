# Billing (Stripe, Paddle)

This kit implements a provider-agnostic billing domain with adapter implementations per provider.

## 1) Overview
- Billing is attached to the User
- Provider webhooks are the source of truth
- Feature access is determined by Entitlements, not plan-name checks
- Provider-specific logic lives in adapters, not core domain code

---

## 2) Canonical data model
Canonical tables (do not leak provider-specific logic into your app):
- `billing_customers`
- `products`, `prices`
- `subscriptions`
- `orders` (one-time purchases)
- `invoices`
- `webhook_events` (idempotency and audit)
- `discounts`, `discount_redemptions`

Admin Panel resources:
- Products (plans), Prices
- Subscriptions, Orders, Invoices, Customers, Webhook Events
- Discounts

---

## 3) Adapter responsibilities
Each provider adapter should:
- create checkout sessions
- verify webhook signatures
- map provider IDs to canonical records
- handle cancellations, refunds, and pauses
- apply discounts/coupons during checkout (if supported)

Note: Ensure the API keys are configured for each provider.

### 3.1 Production Setup
On production environments, you must manually seed the payment providers once to enable them in the Admin Panel:
```bash
php artisan db:seed --class=PaymentProviderSeeder --force
```

### 3.2 Supported providers (where to configure)
Supported billing providers are code-defined, not free-form database entries.

- Enum list: `app/Enums/BillingProvider.php`
- Adapter/runtime wiring: `app/Domain/Billing/Services/BillingProviderManager.php`
- Admin page lets you add only these supported providers.

---

## 4) Configuration

### 4.1 `config/saas.php`
Central config should include enabled providers and catalog behavior.

Example shape:
```php
return [
  'billing' => [
    'providers' => ['stripe', 'paddle'],
    'default_provider' => env('BILLING_DEFAULT_PROVIDER', 'stripe'),
    'default_plan' => env('BILLING_DEFAULT_PLAN', 'starter'),
    'catalog' => env('BILLING_CATALOG', 'database'),
    'success_url' => env('BILLING_SUCCESS_URL'),
    'cancel_url' => env('BILLING_CANCEL_URL'),
    'pricing' => [
      // product keys shown on /pricing
      'shown_plans' => ['hobbyist', 'indie', 'agency'],
      'provider_choice_enabled' => env('BILLING_PROVIDER_CHOICE_ENABLED', true),
    ],
  ],
  'support' => [
    'email' => env('SUPPORT_EMAIL'),
    'discord' => env('SUPPORT_DISCORD_URL'),
  ],
];
```

Notes:
- `shown_plans` contains product keys (`products.key`).
- In domain terms, a "plan" is a Product record plus one or more related Prices.

### 4.2 Catalog source
The active catalog is database-backed by default:

- `BILLING_CATALOG=database` (default, use Admin Panel resources)
- `BILLING_CATALOG=config` (legacy/static setups)

When using the database catalog:
1) Run migrations.
2) Create `Products` and `Prices` in the Admin Panel (`Product` = plan definition).
3) Ensure every product/plan has at least one active price per provider.
4) Provider IDs can be left blank until you publish the catalog.
5) Publish the catalog to providers to generate/link provider IDs:
   `php artisan billing:publish-catalog stripe --apply --update`
   `php artisan billing:publish-catalog paddle --apply --update`

Discount providers are controlled via `saas.billing.discounts.providers`.

---

## 5) Environment variables (template)
Keep provider secrets in `.env` only. Never store secrets in DB.

### 5.0 Billing plan IDs
Billing price IDs are keyed per provider and plan:
```dotenv
BILLING_DEFAULT_PROVIDER=stripe
BILLING_CATALOG=config
BILLING_SUCCESS_URL=
BILLING_CANCEL_URL=

BILLING_STARTER_MONTHLY_STRIPE_ID=
BILLING_STARTER_MONTHLY_PADDLE_ID=
BILLING_STARTER_YEARLY_STRIPE_ID=
BILLING_STARTER_YEARLY_PADDLE_ID=
```
Add the `BILLING_GROWTH_*` and `BILLING_LIFETIME_*` IDs from `.env.example` for subscription and one-time plans.
When using the database catalog, provider IDs live on `price_provider_mappings.provider_id` (linked from `prices`), not `.env`.

### 5.1 Stripe
Typical keys:
- `STRIPE_KEY`
- `STRIPE_SECRET`
- `STRIPE_WEBHOOK_SECRET`

### 5.2 Paddle
Typical keys:
- `PADDLE_VENDOR_ID`
- `PADDLE_API_KEY`
- `PADDLE_CLIENT_SIDE_TOKEN` (recommended)
- `PADDLE_WEBHOOK_SECRET`

Exact keys depend on the adapter package you use. Keep them documented here.

### 5.3 Secret requirements by flow
- Stripe checkout/catalog actions require: `STRIPE_SECRET`
- Stripe webhook verification requires: `STRIPE_WEBHOOK_SECRET`
- Stripe hosted checkout page UX uses: `STRIPE_KEY` (recommended)
- Paddle checkout/catalog actions require: `PADDLE_API_KEY`
- Paddle inline checkout page requires: `PADDLE_VENDOR_ID`
- Paddle webhook verification requires: `PADDLE_WEBHOOK_SECRET`
- Paddle client token (`PADDLE_CLIENT_SIDE_TOKEN`) is recommended and validated by readiness checks as a warning if missing

If any required key is missing, provider actions should fail with a clear configuration error, not an SDK/type error.

---

## 6) Checkout flows

### 6.1 Subscription checkout
Requirements:
- user selects plan/price (monthly/yearly)
- checkout session is created for the user
- session metadata includes: `user_id`, `plan_key`, `price_key`, and `quantity`

### 6.2 One-time purchase checkout
Requirements:
- same metadata patterns
- canonical `orders` record is created/updated on webhook confirmation

### 6.3 Post-checkout redirect
Redirect does not confirm payment. Show a processing screen that waits for webhook confirmation.

---

## 7) Webhook handling

### 7.1 Endpoints
- `/webhooks/stripe`
- `/webhooks/paddle`

### 7.2 Mandatory behavior
- verify signature
- persist event to `webhook_events` (status `received`)
- enqueue a job for processing
- process idempotently:
  - unique constraint on `(provider, event_id)`
  - safe to retry jobs

### 7.3 Failed events
- mark event `failed`
- store error message
- provide Admin Panel action "retry"

---

## 8) Entitlements
Entitlements are computed from canonical billing state and plan definitions (`products` + `prices`). Do not branch on plan names.

---

## 9) Discounts & coupons
- Manage coupons in the Admin Panel (`discounts` table).
- Redemptions are recorded on webhook confirmation (`discount_redemptions`).
- Coupons are supported for Stripe and Paddle checkout flows.

Required fields for a Stripe coupon:
- `provider = stripe`
- `provider_type = coupon` (or `promotion_code`)
- `provider_id = Stripe coupon or promo code ID`

---

## 10) Testing billing
Minimum tests:
- webhook idempotency (same event twice)
- subscription activation via webhook
- cancellation/resume flows
- order paid/refunded flows
- coupon redemption recorded on checkout

---

## 11) Catalog import (Stripe)
If you prefer to create products/prices in Stripe first, you can import them into the DB catalog.

Preview only:
```bash
php artisan billing:import-catalog stripe
```

Apply changes:
```bash
php artisan billing:import-catalog stripe --apply
```

To overwrite existing records (not recommended unless you want Stripe to drive copy):
```bash
php artisan billing:import-catalog stripe --apply --update
```

Notes:
- The import never deletes records.
- Stripe products become Products (plans), Stripe prices become Prices.
- For stable keys, set Stripe product metadata `plan_key` and optional `product_key`.

---

## 12.1 Catalog publish (app-first)
If you prefer to create products/prices in the Admin Panel (or via Seeder) first, you can publish them to the providers. This creates the products on the provider side and saves the resulting IDs to your database, linking them.

> **Crucial:** You must run this command to avoid "Price not configured" errors.

Preview:
```bash
php artisan billing:publish-catalog stripe
php artisan billing:publish-catalog paddle
```

Apply changes:
```bash
php artisan billing:publish-catalog stripe --apply --update
php artisan billing:publish-catalog paddle --apply --update
```

Notes:
- Creates products/prices on the provider.
- Links existing records if keys match.

---

## 12.2 Production Update Workflow

When you need to add or change products/prices (plans/prices) on production, follow this sequence to ensure everything stays in sync:

1.  **Update Code:** Modify `BillingProductSeeder.php` (or use Admin Panel on local).
2.  **Deploy:** Push your changes to production.
3.  **Seed (Optional):** Run the seeder to update your local database values.
    ```bash
    php artisan db:seed --class=PaymentProviderSeeder --force
    php artisan db:seed --class=BillingProductSeeder --force
    ```
4.  **Publish (CRITICAL):** Push the changes to your providers to generate/link IDs.
    ```bash
    php artisan billing:publish-catalog stripe --apply --update
    php artisan billing:publish-catalog paddle --apply --update
    ```

---

## 13) Troubleshooting
- If checkout redirect succeeds but subscription stays inactive:
  - verify webhook endpoint is reachable from the provider
  - verify signature secret
  - check `webhook_events` log in Admin Panel

---

## 14) Staging / Production Readiness Check

Run the built-in checklist command before go-live:

```bash
php artisan billing:check-readiness
```

Use strict mode in CI to fail on warnings too:

```bash
php artisan billing:check-readiness --strict
```

What it validates:
- `APP_URL` and webhook URL shape
- `APP_KEY` presence
- queue mode for webhook processing
- failed-job persistence configuration
- active provider secrets (`Stripe` / `Paddle`, including `PADDLE_VENDOR_ID`)
- route availability for `/webhooks/{provider}`

Recommended release gate:
1. `php artisan migrate --force`
2. `php artisan db:seed --class=PaymentProviderSeeder --force`
3. `php artisan billing:publish-catalog stripe --apply --update`
4. `php artisan billing:publish-catalog paddle --apply --update`
5. `php artisan billing:check-readiness --strict`
6. Confirm queue worker(s) are running and consuming jobs
7. Send one Stripe + one Paddle test webhook and confirm both are marked `processed`

---

## 15) Archive Products (Provider Cleanup)

Use the `billing:archive-all` command to archive (soft-delete) products directly on billing provider dashboards. Archived products won't sync into the local database.

### Usage

```bash
# Preview what would be archived (no changes made)
php artisan billing:archive-all --provider=stripe --dry-run

# Archive all Stripe products
php artisan billing:archive-all --provider=stripe

# Archive all Paddle products and prices
php artisan billing:archive-all --provider=paddle

# Archive across all providers
php artisan billing:archive-all --provider=all

# Include prices (Stripe only)
php artisan billing:archive-all --provider=stripe --include-prices
```

### Provider behavior

| Provider | Action |
|----------|--------|
| Stripe | Sets `active: false` on products (and optionally prices) |
| Paddle | Sets `status: archived` on products and prices |

### When to use
- **Development cleanup** - Clear out test products
- **Provider migration** - Archive old provider before switching
- **Fresh start** - Clean slate for your product catalog

---

## 16) Error handling and DX

### 16.1 Runtime behavior for missing secrets
- Billing runtime adapters throw `BillingException::missingConfiguration(...)` for missing required keys.
- Checkout and portal controllers catch provider failures and show user-safe messages.
- Invoice download falls back cleanly when provider secrets are missing (redirects back to billing with an error).
- Social auth callback/redirect now catches provider misconfiguration errors and returns to login with a clear message.

### 16.2 Diagnostics flow for developers
1. Run `php artisan billing:check-readiness` locally/staging.
2. Fix every `FAIL` result first (especially missing provider secrets).
3. For production pipelines, use `php artisan billing:check-readiness --strict`.
4. Verify one real webhook per provider reaches `processed` state.

### 16.3 Common misconfiguration symptoms
- `... is not configured` from billing services:
  A required provider key is missing; check `.env`, config cache, and active provider settings.
- Checkout page loads but provider widget fails:
  Most often missing/invalid `PADDLE_VENDOR_ID` or provider environment mismatch.
- Social login redirects back to `/login` with a social error:
  Check OAuth client id/secret/redirect URI in `config/services.php` values.
