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
    | Available: "default", "void", "aurora", "prism", "velvet", "frost",
    | "ember", "ocean"
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
            'palette' => [
                'primary' => '#6366F1',
                'secondary' => '#A855F7',
                'accent' => '#EC4899',
            ],
            'fonts' => [
                'sans' => 'Plus Jakarta Sans',
                'display' => 'Outfit',
            ],
        ],
        'void' => [
            'name' => 'Void',
            'description' => 'Dark futuristic aesthetic with cyberpunk vibes. Glowing borders and electric accents.',
            'palette' => [
                'primary' => '#06B6D4',
                'secondary' => '#D946EF',
                'accent' => '#FB7185',
            ],
            'fonts' => [
                'sans' => 'JetBrains Mono',
                'display' => 'Orbitron',
            ],
        ],
        'aurora' => [
            'name' => 'Aurora',
            'description' => 'Organic gradients inspired by nature. Soft, flowing, and calming.',
            'palette' => [
                'primary' => '#10B981',
                'secondary' => '#06B6D4',
                'accent' => '#8B5CF6',
            ],
            'fonts' => [
                'sans' => 'Inter',
                'display' => 'Outfit',
            ],
        ],
        'prism' => [
            'name' => 'Prism',
            'description' => 'Bold geometric brutalism. High contrast, sharp edges, statement-making.',
            'palette' => [
                'primary' => '#F59E0B',
                'secondary' => '#EF4444',
                'accent' => '#000000',
            ],
            'fonts' => [
                'sans' => 'Space Grotesk',
                'display' => 'Bebas Neue',
            ],
        ],
        'velvet' => [
            'name' => 'Velvet',
            'description' => 'Luxury editorial minimalism. Elegant serifs and generous whitespace.',
            'palette' => [
                'primary' => '#D4AF37',
                'secondary' => '#1C1917',
                'accent' => '#78716C',
            ],
            'fonts' => [
                'sans' => 'Cormorant Garamond',
                'display' => 'Cinzel',
            ],
        ],
        'frost' => [
            'name' => 'Frost',
            'description' => 'Arctic glass aesthetic with icy highlights and crisp depth.',
            'palette' => [
                'primary' => '#38BDF8',
                'secondary' => '#BAE6FD',
                'accent' => '#0EA5E9',
            ],
            'fonts' => [
                'sans' => 'Inter',
                'display' => 'Outfit',
            ],
        ],
        'ember' => [
            'name' => 'Ember',
            'description' => 'Warm fire-inspired gradients with cozy, high-contrast surfaces.',
            'palette' => [
                'primary' => '#FB923C',
                'secondary' => '#EF4444',
                'accent' => '#FDE047',
            ],
            'fonts' => [
                'sans' => 'Nunito',
                'display' => 'Poppins',
            ],
        ],
        'ocean' => [
            'name' => 'Ocean',
            'description' => 'Deep-sea palette with fluid motion and bioluminescent accents.',
            'palette' => [
                'primary' => '#22D3EE',
                'secondary' => '#3B82F6',
                'accent' => '#10B981',
            ],
            'fonts' => [
                'sans' => 'DM Sans',
                'display' => 'Sora',
            ],
        ],
    ],
];
