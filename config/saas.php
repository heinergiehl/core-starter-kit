<?php

return [
    'billing' => [
        'providers' => ['stripe', 'paddle', 'lemonsqueezy'],
        'default_provider' => env('BILLING_DEFAULT_PROVIDER', 'stripe'),
        'default_plan' => env('BILLING_DEFAULT_PLAN', 'starter'),
        'sync_catalog_via_webhooks' => env('BILLING_SYNC_CATALOG_VIA_WEBHOOKS', true),
        'catalog' => env('BILLING_CATALOG', 'config'),
        'discounts' => [
            'providers' => ['stripe', 'paddle', 'lemonsqueezy'],
        ],
        'success_url' => env('BILLING_SUCCESS_URL'),
        'cancel_url' => env('BILLING_CANCEL_URL'),
        'plans' => [
            'starter' => [
                'name' => 'Starter',
                'summary' => 'Solo founders validating demand.',
                'type' => 'subscription',
                'seat_based' => false,
                'entitlements' => [
                    'max_seats' => 3,
                    'storage_limit_mb' => 2048,
                    'support_sla' => 'community',
                ],
                'features' => [
                    'Up to 3 team members',
                    '2 GB storage',
                    'Email support',
                    'Core analytics',
                ],
                'prices' => [
                    'monthly' => [
                        'label' => 'Monthly',
                        'amount' => 29,
                        'currency' => 'USD',
                        'interval' => 'month',
                        'providers' => [
                            'stripe' => env('BILLING_STARTER_MONTHLY_STRIPE_ID'),
                            'paddle' => env('BILLING_STARTER_MONTHLY_PADDLE_ID'),
                            'lemonsqueezy' => env('BILLING_STARTER_MONTHLY_LEMON_SQUEEZY_ID'),
                        ],
                    ],
                    'yearly' => [
                        'label' => 'Yearly',
                        'amount' => 290,
                        'currency' => 'USD',
                        'interval' => 'year',
                        'providers' => [
                            'stripe' => env('BILLING_STARTER_YEARLY_STRIPE_ID'),
                            'paddle' => env('BILLING_STARTER_YEARLY_PADDLE_ID'),
                            'lemonsqueezy' => env('BILLING_STARTER_YEARLY_LEMON_SQUEEZY_ID'),
                        ],
                    ],
                ],
            ],
            'team' => [
                'name' => 'Team',
                'summary' => 'Seat-based billing for growing teams.',
                'type' => 'subscription',
                'seat_based' => true,
                'highlight' => true,
                'entitlements' => [
                    'max_seats' => null,
                    'storage_limit_mb' => 10240,
                    'support_sla' => 'priority',
                ],
                'features' => [
                    'Seat-based pricing that scales',
                    '10 GB storage',
                    'Audit log + team roles',
                    'Priority email support',
                ],
                'prices' => [
                    'monthly' => [
                        'label' => 'Monthly',
                        'amount' => 59,
                        'currency' => 'USD',
                        'interval' => 'month',
                        'providers' => [
                            'stripe' => env('BILLING_TEAM_MONTHLY_STRIPE_ID'),
                            'paddle' => env('BILLING_TEAM_MONTHLY_PADDLE_ID'),
                            'lemonsqueezy' => env('BILLING_TEAM_MONTHLY_LEMON_SQUEEZY_ID'),
                        ],
                    ],
                    'yearly' => [
                        'label' => 'Yearly',
                        'amount' => 590,
                        'currency' => 'USD',
                        'interval' => 'year',
                        'providers' => [
                            'stripe' => env('BILLING_TEAM_YEARLY_STRIPE_ID'),
                            'paddle' => env('BILLING_TEAM_YEARLY_PADDLE_ID'),
                            'lemonsqueezy' => env('BILLING_TEAM_YEARLY_LEMON_SQUEEZY_ID'),
                        ],
                    ],
                ],
            ],
            'lifetime' => [
                'name' => 'Lifetime',
                'summary' => 'One-time purchase for indie teams.',
                'type' => 'one_time',
                'seat_based' => false,
                'entitlements' => [
                    'max_seats' => 5,
                    'storage_limit_mb' => 5120,
                    'support_sla' => 'email',
                ],
                'features' => [
                    'Pay once, keep updates',
                    'Up to 5 team members',
                    '5 GB storage',
                    'Priority bug fixes',
                ],
                'prices' => [
                    'lifetime' => [
                        'label' => 'One-time',
                        'amount' => 499,
                        'currency' => 'USD',
                        'interval' => 'once',
                        'providers' => [
                            'stripe' => env('BILLING_LIFETIME_STRIPE_ID'),
                            'paddle' => env('BILLING_LIFETIME_PADDLE_ID'),
                            'lemonsqueezy' => env('BILLING_LIFETIME_LEMON_SQUEEZY_ID'),
                        ],
                    ],
                ],
            ],
        ],
    ],
    'seats' => [
        'count_pending_invites' => false,
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
        'logo_path' => env('APP_LOGO_PATH'),
        'fonts' => [
            'sans' => env('BRAND_FONT_SANS', 'Instrument Sans'),
            'display' => env('BRAND_FONT_DISPLAY', 'Instrument Serif'),
        ],
        'colors' => [
            'primary' => env('BRAND_COLOR_PRIMARY', '14 116 144'),
            'secondary' => env('BRAND_COLOR_SECONDARY', '245 158 11'),
            'accent' => env('BRAND_COLOR_ACCENT', '239 68 68'),
            'bg' => env('BRAND_COLOR_BG', '250 250 249'),
            'fg' => env('BRAND_COLOR_FG', '15 23 42'),
        ],
    ],
    'tenancy' => [
        'base_domain' => env('TENANCY_BASE_DOMAIN'),
        'reserved_subdomains' => [
            'www',
            'app',
            'admin',
            'api',
            'billing',
            'dashboard',
            'login',
            'logout',
            'register',
            'support',
            'www1',
            'mail',
            'smtp',
            'pop',
            'imap',
        ],
    ],
    'auth' => [
        'social_providers' => ['google', 'github', 'linkedin'],
    ],
    'locales' => [
        'default' => env('APP_LOCALE', 'en'),
        'supported' => [
            'en' => 'English',
            'de' => 'Deutsch',
        ],
    ],
];
