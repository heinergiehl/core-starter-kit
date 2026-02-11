<?php

return [
    'billing' => [
        'providers' => ['stripe', 'paddle'],
        'default_provider' => env('BILLING_DEFAULT_PROVIDER', 'stripe'),
        'default_plan' => env('BILLING_DEFAULT_PLAN', 'starter'),
        'sync_catalog_via_webhooks' => env('BILLING_SYNC_CATALOG_VIA_WEBHOOKS', true),
        // Catalog source: 'database' uses products/prices synced from billing providers
        // All plan management happens in Stripe/Paddle dashboards
        // Features & entitlements are edited via Filament /admin/products
        'catalog' => env('BILLING_CATALOG', 'database'),
        'discounts' => [
            'providers' => ['stripe', 'paddle'],
        ],
        'provider_api' => [
            'timeouts' => [
                'stripe' => env('BILLING_STRIPE_TIMEOUT', env('BILLING_PROVIDER_TIMEOUT', 15)),
                'paddle' => env('BILLING_PADDLE_TIMEOUT', env('BILLING_PROVIDER_TIMEOUT', 15)),
            ],
            'connect_timeouts' => [
                'stripe' => env('BILLING_STRIPE_CONNECT_TIMEOUT', env('BILLING_PROVIDER_CONNECT_TIMEOUT', 5)),
                'paddle' => env('BILLING_PADDLE_CONNECT_TIMEOUT', env('BILLING_PROVIDER_CONNECT_TIMEOUT', 5)),
            ],
            'retries' => [
                'stripe' => env('BILLING_STRIPE_RETRIES', env('BILLING_PROVIDER_RETRIES', 2)),
                'paddle' => env('BILLING_PADDLE_RETRIES', env('BILLING_PROVIDER_RETRIES', 2)),
            ],
            'retry_delay_ms' => env('BILLING_PROVIDER_RETRY_DELAY_MS', 500),
        ],
        'webhooks' => [
            'tries' => env('BILLING_WEBHOOK_TRIES', 5),
            'max_exceptions' => env('BILLING_WEBHOOK_MAX_EXCEPTIONS', 3),
            'timeout' => env('BILLING_WEBHOOK_TIMEOUT', 120),
            'backoff_seconds' => array_values(array_filter(array_map(
                'intval',
                preg_split('/\s*,\s*/', (string) env('BILLING_WEBHOOK_BACKOFF_SECONDS', '5,15,60')) ?: []
            ), static fn (int $value): bool => $value >= 0)),
            'unique_for_seconds' => env('BILLING_WEBHOOK_UNIQUE_FOR', 300),
            'redispatch_received_after_seconds' => env('BILLING_WEBHOOK_REDISPATCH_RECEIVED_AFTER', 30),
            'processing_stale_after_minutes' => env('BILLING_WEBHOOK_PROCESSING_STALE_AFTER', 15),
        ],
        'outbox' => [
            // Reserved for future use
        ],
        'success_url' => env('BILLING_SUCCESS_URL'),
        'cancel_url' => env('BILLING_CANCEL_URL'),

        // Pricing page display options
        'pricing' => [
            // Currency for all billing operations
            'currency' => env('SAAS_CURRENCY', 'USD'),

            // List of plan keys (product keys) to display on the pricing page.
            // If empty, all active plans are shown.
            // Use this to curate your unified pricing page (e.g., ['starter', 'pro', 'business'])
            'shown_plans' => ['hobbyist', 'indie', 'agency'],

            // Allow customers to choose their preferred payment provider
            // Useful when serving international customers who may prefer different
            // payment methods (PayPal via Paddle, local methods, etc.)
            // Set to false to use only the default_provider
            'provider_choice_enabled' => env('BILLING_PROVIDER_CHOICE_ENABLED', true),

            // Provider display labels (customer-friendly names)
            // Customize these based on what payment methods you've enabled
            'provider_labels' => [
                'stripe' => 'Stripe',
                'paddle' => 'Paddle',
            ],
        ],
    ],
    'support' => [
        'email' => env('SUPPORT_EMAIL'),
        'discord' => env('SUPPORT_DISCORD_URL'),
    ],
    'invites' => [
        'expires_days' => env('INVITE_EXPIRES_DAYS', 7),
    ],
    'branding' => [
        'app_name' => env('APP_NAME', 'SaaS Kit'),
        'logo_path' => env('APP_LOGO_PATH') ?: 'branding/shipsolid-s-mark.svg',
        'favicon_path' => env('APP_FAVICON_PATH') ?: (env('APP_LOGO_PATH') ?: 'branding/shipsolid-s-mark.svg'),
        'fonts' => [
            'sans' => env('BRAND_FONT_SANS', 'Instrument Sans'),
            'display' => env('BRAND_FONT_DISPLAY', 'Instrument Serif'),
        ],
        'email' => [
            'primary' => env('BRAND_EMAIL_PRIMARY', '#4F46E5'),
            'secondary' => env('BRAND_EMAIL_SECONDARY', '#A855F7'),
        ],
    ],
    'auth' => [
        'social_providers' => ['google', 'github', 'linkedin'],
        'socialite_stateless' => env('SOCIALITE_STATELESS'),
    ],
    'locales' => [
        'default' => env('APP_LOCALE', 'en'),
        'supported' => [
            'en' => 'English',
            'de' => 'Deutsch',
            'es' => 'Espanol',
            'fr' => 'Francais',
        ],
    ],
    'seo' => [
        'sitemap_cache_seconds' => env('SEO_SITEMAP_CACHE_SECONDS', 3600),
        'rss_cache_seconds' => env('SEO_RSS_CACHE_SECONDS', 900),
    ],
    'features' => [
        'blog' => true,
        'roadmap' => true,
        'announcements' => true,
        'onboarding' => true,
    ],
    'security' => [
        // Alpine/Livewire expression evaluation uses dynamic function evaluation.
        // Keep this true unless your frontend is fully CSP-safe without Alpine eval.
        'allow_unsafe_eval' => env('CSP_ALLOW_UNSAFE_EVAL', true),
        'csp_domains' => [
            'script' => ['https://*.paddle.com', 'https://cdn.paddle.com', 'https://js.stripe.com'],
            'style' => ['https://fonts.googleapis.com'],
            'font' => ['https://fonts.gstatic.com', 'data:'],
            'img' => ['data:', 'https:', 'blob:'],
            'connect' => ['https://*.paddle.com', 'https://api.stripe.com', 'wss:'],
            'frame' => ['https://*.paddle.com', 'https://js.stripe.com', 'https://hooks.stripe.com'],
        ],
    ],
];
