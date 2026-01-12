<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active Template
    |--------------------------------------------------------------------------
    |
    | The currently active frontend template. This affects all customer-facing
    | views: welcome, pricing, auth pages, blog, and dashboard.
    |
    | Available: "default", "void", "aurora", "prism", "velvet"
    |
    */
    'active' => env('SAAS_TEMPLATE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Template Definitions
    |--------------------------------------------------------------------------
    |
    | Each template defines a unique visual identity for customer-facing pages.
    | Templates share the same HTML structure but apply different CSS tokens.
    |
    */
    'templates' => [
        'default' => [
            'name' => 'Default',
            'description' => 'The original glassmorphic design with indigo/purple gradients.',
            'fonts' => [
                'sans' => 'Plus Jakarta Sans',
                'display' => 'Outfit',
            ],
        ],
        'void' => [
            'name' => 'Void',
            'description' => 'Dark futuristic aesthetic with cyberpunk vibes. Glowing borders and electric accents.',
            'fonts' => [
                'sans' => 'JetBrains Mono',
                'display' => 'Orbitron',
            ],
        ],
        'aurora' => [
            'name' => 'Aurora',
            'description' => 'Organic gradients inspired by nature. Soft, flowing, and calming.',
            'fonts' => [
                'sans' => 'DM Sans',
                'display' => 'Fraunces',
            ],
        ],
        'prism' => [
            'name' => 'Prism',
            'description' => 'Bold geometric brutalism. High contrast, sharp edges, statement-making.',
            'fonts' => [
                'sans' => 'Space Grotesk',
                'display' => 'Bebas Neue',
            ],
        ],
        'velvet' => [
            'name' => 'Velvet',
            'description' => 'Luxury editorial minimalism. Elegant serifs and generous whitespace.',
            'fonts' => [
                'sans' => 'Inter',
                'display' => 'Playfair Display',
            ],
        ],
    ],
];
