# Billing (Stripe, Paddle, Lemon Squeezy)

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
- `products`, `plans`, `prices`
- `subscriptions`
- `orders` (one-time purchases)
- `invoices`
- `webhook_events` (idempotency and audit)
- `discounts`, `discount_redemptions`

Admin Panel resources:
- Products, Plans, Prices
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

---

## 4) Configuration

### 4.1 `config/saas.php`
Central config should include enabled providers and plan definitions.

Example shape:
```php
return [
  'billing' => [
    'providers' => ['stripe', 'paddle', 'lemonsqueezy'],
    'default_provider' => env('BILLING_DEFAULT_PROVIDER', 'stripe'),
    'success_url' => env('BILLING_SUCCESS_URL'),
    'cancel_url' => env('BILLING_CANCEL_URL'),
    'plans' => [
      'starter' => [
        'name' => 'Starter',
        'type' => 'subscription',
        'entitlements' => [
          'storage_limit_mb' => 2048,
        ],
        'prices' => [
          'monthly' => [
            'amount' => 29,
            'interval' => 'month',
            'providers' => [
              'stripe' => env('BILLING_STARTER_MONTHLY_STRIPE_ID'),
              'paddle' => env('BILLING_STARTER_MONTHLY_PADDLE_ID'),
              'lemonsqueezy' => env('BILLING_STARTER_MONTHLY_LEMON_SQUEEZY_ID'),
            ],
          ],
        ],
      ],
    ],
  ],
  'support' => [
    'email' => env('SUPPORT_EMAIL'),
    'discord' => env('SUPPORT_DISCORD_URL'),
  ],
];
```

Notes:
- `amount` and `interval` are display-only.

### 4.2 Catalog source
By default the kit reads plan data from `config/saas.php`. You can switch to the database-backed catalog:

- `BILLING_CATALOG=config` (default)
- `BILLING_CATALOG=database` (use Admin Panel resources)

When using the database catalog:
1) Run migrations.
2) Create `Products`, `Plans`, and `Prices` in the Admin Panel.
3) Ensure every plan has at least one price per provider.
4) Provider IDs can be left blank until you publish the catalog.
5) Publish the catalog to Stripe to generate provider IDs:
   `php artisan billing:publish-catalog stripe --apply`
If no active plans exist, the kit falls back to `config/saas.php`.

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
BILLING_STARTER_MONTHLY_LEMON_SQUEEZY_ID=
BILLING_STARTER_YEARLY_STRIPE_ID=
BILLING_STARTER_YEARLY_PADDLE_ID=
BILLING_STARTER_YEARLY_LEMON_SQUEEZY_ID=
```
Add the `BILLING_GROWTH_*` and `BILLING_LIFETIME_*` IDs from `.env.example` for subscription and one-time plans.
When using the database catalog, provider IDs live in the `prices` table (`provider_id`), not `.env`.

### 5.1 Stripe
Typical keys:
- `STRIPE_KEY`
- `STRIPE_SECRET`
- `STRIPE_WEBHOOK_SECRET`

### 5.2 Paddle
Typical keys:
- `PADDLE_VENDOR_ID`
- `PADDLE_API_KEY`
- `PADDLE_WEBHOOK_SECRET`

### 5.3 Lemon Squeezy
Typical keys:
- `LEMON_SQUEEZY_API_KEY`
- `LEMON_SQUEEZY_WEBHOOK_SECRET`
- `LEMON_SQUEEZY_STORE_ID` (if required by your implementation)

Exact keys depend on the adapter package you use. Keep them documented here.

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
- `/webhooks/lemonsqueezy`

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
Entitlements are computed from canonical billing state and plan definitions. Do not branch on plan names.

---

## 9) Discounts & coupons
- Manage coupons in the Admin Panel (`discounts` table).
- Redemptions are recorded on webhook confirmation (`discount_redemptions`).
- Coupons are supported for Stripe, Paddle, and Lemon Squeezy checkout flows.

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
- Stripe products become Plans, Stripe prices become Prices.
- For stable keys, set Stripe product metadata `plan_key` and optional `product_key`.

---

## 12.1 Catalog publish (app-first, Stripe)
If you prefer to create products/plans/prices in the Admin Panel first, you can publish them to Stripe and populate provider IDs:

Preview:
```bash
php artisan billing:publish-catalog stripe
```

Apply changes:
```bash
php artisan billing:publish-catalog stripe --apply
```

Notes:
- The publish flow creates Stripe products per Plan and Stripe prices per Price.
- Existing Stripe prices are linked by lookup key when possible.
- Paddle and Lemon Squeezy publish flows are not implemented yet.

---

## 13) Troubleshooting
- If checkout redirect succeeds but subscription stays inactive:
  - verify webhook endpoint is reachable from the provider
  - verify signature secret
  - check `webhook_events` log in Admin Panel

---

## 14) Archive Products (Provider Cleanup)

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
| LemonSqueezy | ⚠️ Not supported via API - archive manually in dashboard |

### When to use
- **Development cleanup** - Clear out test products
- **Provider migration** - Archive old provider before switching
- **Fresh start** - Clean slate for your product catalog
