<?php

namespace App\Http\Controllers\Content;

use Illuminate\Contracts\View\View;

class SolutionPageController
{
    public function index(): View
    {
        $solutionPages = collect($this->pages())
            ->map(function (array $page, string $slug): array {
                return array_merge($page, [
                    'slug' => $slug,
                    'url' => route('solutions.show', $slug),
                ]);
            })
            ->values()
            ->all();

        return view('solutions.index', [
            'solutionPages' => $solutionPages,
        ]);
    }

    public function show(string $slug): View
    {
        $pages = $this->pages();

        abort_unless(isset($pages[$slug]), 404);

        $solutionPage = array_merge($pages[$slug], [
            'slug' => $slug,
            'url' => route('solutions.show', $slug),
        ]);

        $relatedSolutions = collect($solutionPage['related'] ?? [])
            ->map(function (string $relatedSlug) use ($pages): ?array {
                if (!isset($pages[$relatedSlug])) {
                    return null;
                }

                $related = $pages[$relatedSlug];

                return [
                    'slug' => $relatedSlug,
                    'title' => $related['card_title'],
                    'summary' => $related['summary'],
                    'url' => route('solutions.show', $relatedSlug),
                    'hero_image' => $related['hero_image'],
                    'hero_image_alt' => $related['hero_image_alt'],
                ];
            })
            ->filter()
            ->values()
            ->all();

        return view('solutions.show', [
            'solutionPage' => $solutionPage,
            'relatedSolutions' => $relatedSolutions,
        ]);
    }

    public static function slugs(): array
    {
        return array_keys((new self())->pages());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function pages(): array
    {
        return [
            'laravel-stripe-paddle-billing-starter' => [
                'card_title' => 'Laravel Stripe and Paddle billing starter',
                'summary' => 'Provider-aware checkout, catalog operations, coupons, portals, and invoice flows for SaaS billing in one stack.',
                'seo_title' => 'Laravel Stripe and Paddle Billing Starter for SaaS Checkout',
                'meta_description' => 'Launch Laravel SaaS billing with Stripe and Paddle: provider-aware checkout, pricing catalog, subscriptions, discounts, invoices, and portal workflows.',
                'keywords' => [
                    'laravel stripe checkout starter',
                    'laravel paddle billing integration',
                    'laravel saas billing starter kit',
                    'stripe paddle dual provider checkout',
                    'laravel subscriptions and one-time payments',
                ],
                'hero_eyebrow' => 'Billing Use Case',
                'hero_title' => 'Laravel SaaS billing with Stripe and Paddle in one production flow',
                'hero_description' => 'This solution page maps the exact billing stack: provider-aware checkout, catalog and pricing controls, subscription lifecycle operations, and invoice-oriented customer workflows.',
                'hero_image' => 'storage/marketing/checkout-provider-select-focus.png',
                'hero_image_alt' => 'Checkout flow with Stripe and Paddle provider options and plan details',
                'pillars' => [
                    [
                        'title' => 'Provider-aware checkout orchestration',
                        'copy' => 'Customers can select Stripe or Paddle before payment, with clear plan and billing context before handoff.',
                    ],
                    [
                        'title' => 'Catalog and pricing governance',
                        'copy' => 'Products, prices, provider mappings, and active states are managed from admin resources built for operations.',
                    ],
                    [
                        'title' => 'Lifecycle and recovery coverage',
                        'copy' => 'Subscriptions, cancellations, resumes, plan changes, webhooks, and invoice downloads are part of one billing surface.',
                    ],
                ],
                'screens' => [
                    [
                        'title' => 'Provider selection before charge',
                        'copy' => 'Show Stripe/Paddle options up front so users understand billing path early.',
                        'image' => 'storage/marketing/checkout-provider-select-focus.png',
                        'alt' => 'Checkout page with Stripe and Paddle options',
                        'size' => 'wide',
                    ],
                    [
                        'title' => 'Structured payment handoff',
                        'copy' => 'Capture customer details with a clean conversion-focused form.',
                        'image' => 'storage/marketing/checkout-form-stripe-focus.png',
                        'alt' => 'Checkout form with plan details and continue to payment action',
                        'size' => 'compact',
                    ],
                    [
                        'title' => 'Provider operations panel',
                        'copy' => 'Toggle and manage supported providers from admin.',
                        'image' => 'storage/marketing/localhost_8000_admin_payment-providers.png',
                        'alt' => 'Admin payment provider list with activation controls',
                        'size' => 'compact',
                    ],
                    [
                        'title' => 'Product and price execution',
                        'copy' => 'Operate catalog state and provider mapping from one table.',
                        'image' => 'storage/marketing/admin-products-focus.png',
                        'alt' => 'Product operations table with statuses and provider columns',
                        'size' => 'wide',
                    ],
                ],
                'coverage' => [
                    'Subscriptions and one-time purchases',
                    'Stripe and Paddle adapters with checkout routes',
                    'Discount and coupon resources',
                    'Billing portal and invoice download endpoints',
                    'Webhook ingestion route for provider events',
                ],
                'faq' => [
                    [
                        'q' => 'Can I support both Stripe and Paddle on the same Laravel SaaS?',
                        'a' => 'Yes. The checkout surface can present both providers and route payment flow according to active configuration.',
                    ],
                    [
                        'q' => 'Does the billing stack include one-time and recurring models?',
                        'a' => 'Yes. Product and price resources are designed to support one-time and subscription billing models.',
                    ],
                    [
                        'q' => 'Can customers access invoices and portal workflows?',
                        'a' => 'Yes. Invoice download surfaces and provider portal routes are part of the billing operations layer.',
                    ],
                    [
                        'q' => 'Is this flow suitable for production SaaS launches?',
                        'a' => 'The architecture ships with provider adapters, webhooks, and admin governance intended for production workflows.',
                    ],
                ],
                'related' => [
                    'filament-admin-operations-for-saas',
                    'laravel-saas-blog-and-seo-starter',
                    'laravel-saas-onboarding-and-localization',
                ],
            ],
            'filament-admin-operations-for-saas' => [
                'card_title' => 'Filament admin operations for SaaS teams',
                'summary' => 'User, role, product, provider, subscription, and billing operations in a unified Filament admin workspace.',
                'seo_title' => 'Filament Admin Panel SaaS Starter for Operations and Billing',
                'meta_description' => 'Manage SaaS operations with Filament: users, roles, products, prices, subscriptions, providers, invoices, and support workflows in one admin panel.',
                'keywords' => [
                    'filament admin panel saas starter',
                    'laravel filament billing admin',
                    'filament user role permission management',
                    'saas operations dashboard laravel',
                    'filament product and price management',
                ],
                'hero_eyebrow' => 'Admin Operations Use Case',
                'hero_title' => 'Filament admin operations designed for real SaaS execution',
                'hero_description' => 'This page highlights the operational layer: support actions, product governance, billing records, and team permissions in one coherent panel.',
                'hero_image' => 'storage/marketing/admin-products-focus.png',
                'hero_image_alt' => 'Filament product operations table with filters and status badges',
                'pillars' => [
                    [
                        'title' => 'Support and access control',
                        'copy' => 'User resources, role assignments, permission management, and impersonation workflows are built for day-to-day operator tasks.',
                    ],
                    [
                        'title' => 'Monetization governance',
                        'copy' => 'Products, prices, payment providers, discounts, and subscriptions are administered from dedicated resources.',
                    ],
                    [
                        'title' => 'Operational reporting and visibility',
                        'copy' => 'Dashboard widgets and billing metrics help teams monitor MRR, churn, usage, and plan health over time.',
                    ],
                ],
                'screens' => [
                    [
                        'title' => 'Catalog operations',
                        'copy' => 'Control product lifecycle and provider mapping with one table view.',
                        'image' => 'storage/marketing/admin-products-focus.png',
                        'alt' => 'Admin products table with provider and status fields',
                        'size' => 'wide',
                    ],
                    [
                        'title' => 'User administration',
                        'copy' => 'Handle roles, account status, and support actions quickly.',
                        'image' => 'storage/marketing/admin-users-focus.png',
                        'alt' => 'Admin users table with roles and support actions',
                        'size' => 'compact',
                    ],
                    [
                        'title' => 'Brand settings governance',
                        'copy' => 'Maintain identity and theme defaults from one settings screen.',
                        'image' => 'storage/marketing/admin-manage-branding-focus.png',
                        'alt' => 'Admin branding and theme settings interface',
                        'size' => 'compact',
                    ],
                    [
                        'title' => 'Metrics dashboard',
                        'copy' => 'Track key SaaS performance indicators for operations.',
                        'image' => 'storage/marketing/localhost_8000_admin_stats.png',
                        'alt' => 'Admin stats dashboard for SaaS metrics',
                        'size' => 'wide',
                    ],
                ],
                'coverage' => [
                    'User, Role, and Permission resources',
                    'Product, Price, and Discount resources',
                    'Payment Provider and Subscription resources',
                    'Order and Invoice resources',
                    'Brand, settings, and email configuration pages',
                ],
                'faq' => [
                    [
                        'q' => 'Is this suitable for non-technical operations teams?',
                        'a' => 'The Filament admin layer is structured around clear resources and workflows, reducing custom tool sprawl for operators.',
                    ],
                    [
                        'q' => 'Can I manage both customer support and billing from one panel?',
                        'a' => 'Yes. User resources and billing resources are integrated so teams can handle support and monetization together.',
                    ],
                    [
                        'q' => 'Are roles and permissions included by default?',
                        'a' => 'Yes. Role and permission resources are available for access governance.',
                    ],
                    [
                        'q' => 'Does this include operational metrics for SaaS KPIs?',
                        'a' => 'Yes. Admin widgets include billing and SaaS metrics to monitor platform performance.',
                    ],
                ],
                'related' => [
                    'laravel-stripe-paddle-billing-starter',
                    'laravel-saas-blog-and-seo-starter',
                    'laravel-saas-onboarding-and-localization',
                ],
            ],
            'laravel-saas-blog-and-seo-starter' => [
                'card_title' => 'Laravel SaaS blog and SEO starter',
                'summary' => 'Built-in blog publishing, metadata controls, sitemap/RSS/OG endpoints, and content workflows for organic traffic growth.',
                'seo_title' => 'Laravel SaaS Blog and SEO Starter with Sitemap RSS and Open Graph',
                'meta_description' => 'Grow organic traffic with built-in Laravel SaaS blog features: rich editor, categories, tags, SEO fields, sitemap.xml, rss.xml, and Open Graph image routes.',
                'keywords' => [
                    'laravel saas blog starter',
                    'laravel seo starter kit',
                    'laravel sitemap rss open graph',
                    'filament blog cms laravel',
                    'laravel content marketing stack',
                ],
                'hero_eyebrow' => 'Content and SEO Use Case',
                'hero_title' => 'Laravel blog and SEO infrastructure for SaaS organic growth',
                'hero_description' => 'This page focuses on long-tail content execution: publish with structured metadata, maintain taxonomy, and expose technical SEO endpoints out of the box.',
                'hero_image' => 'storage/marketing/admin-blog-editor-focus.png',
                'hero_image_alt' => 'Blog editor with long-form content and media support',
                'pillars' => [
                    [
                        'title' => 'Editorial workflow in admin',
                        'copy' => 'Create posts with title, slug, excerpt, rich body content, featured image, category, tags, author, status, and publish date.',
                    ],
                    [
                        'title' => 'SEO-ready publishing controls',
                        'copy' => 'Set SEO title and meta description directly in post workflows for improved search result relevance.',
                    ],
                    [
                        'title' => 'Technical discoverability endpoints',
                        'copy' => 'Sitemap.xml, rss.xml, and Open Graph image routes are available to support search and social indexing.',
                    ],
                ],
                'screens' => [
                    [
                        'title' => 'Long-form content editor',
                        'copy' => 'Write and structure posts with media and formatting controls.',
                        'image' => 'storage/marketing/admin-blog-editor-focus.png',
                        'alt' => 'Blog editor interface with rich text and featured image',
                        'size' => 'wide',
                    ],
                    [
                        'title' => 'Post list operations',
                        'copy' => 'Manage status, category, date, and read-time from one table.',
                        'image' => 'storage/marketing/localhost_8000_admin_blog-posts.png',
                        'alt' => 'Blog posts list with filters and status indicators',
                        'size' => 'compact',
                    ],
                    [
                        'title' => 'Public blog rendering',
                        'copy' => 'Publish articles to public-facing routes that integrate with nav and sitemap.',
                        'image' => 'storage/marketing/localhost_8000_blog.png',
                        'alt' => 'Public blog layout with article cards and images',
                        'size' => 'compact',
                    ],
                    [
                        'title' => 'SEO fields inside editor flow',
                        'copy' => 'Optimize metadata before publish without extra CMS plugins.',
                        'image' => 'storage/marketing/admin-blog-editor.png',
                        'alt' => 'Blog editor detail with metadata and content controls',
                        'size' => 'wide',
                    ],
                ],
                'coverage' => [
                    'Blog, category, and tag resources',
                    'Rich editor with file attachments',
                    'Featured image and excerpt workflows',
                    'SEO title and description fields',
                    'Sitemap, RSS, and OG image routes',
                ],
                'faq' => [
                    [
                        'q' => 'Can this starter replace a separate blog CMS early on?',
                        'a' => 'Yes. The built-in editorial stack covers core publishing, metadata, and taxonomy workflows for many SaaS teams.',
                    ],
                    [
                        'q' => 'Are technical SEO endpoints included?',
                        'a' => 'Yes. Sitemap.xml, rss.xml, and Open Graph image routes are implemented.',
                    ],
                    [
                        'q' => 'Can I organize content by category and tags?',
                        'a' => 'Yes. Category and tag resources are part of the blog operations surface.',
                    ],
                    [
                        'q' => 'Is publish scheduling supported?',
                        'a' => 'Yes. Posts support status and publish date controls for draft and scheduled release workflows.',
                    ],
                ],
                'related' => [
                    'filament-admin-operations-for-saas',
                    'laravel-stripe-paddle-billing-starter',
                    'laravel-saas-onboarding-and-localization',
                ],
            ],
            'laravel-saas-onboarding-and-localization' => [
                'card_title' => 'Laravel SaaS onboarding and localization starter',
                'summary' => 'Authentication, social login, onboarding, locale switching, and account flow surfaces for global SaaS products.',
                'seo_title' => 'Laravel SaaS Onboarding and Localization Starter with Social Login',
                'meta_description' => 'Launch multilingual Laravel SaaS onboarding with email/social login, locale switching, onboarding routes, and account flow integration.',
                'keywords' => [
                    'laravel saas onboarding starter',
                    'laravel social login saas',
                    'laravel locale switcher marketing pages',
                    'multilingual laravel saas starter',
                    'laravel auth onboarding flow',
                ],
                'hero_eyebrow' => 'Onboarding and i18n Use Case',
                'hero_title' => 'Onboarding and localization flows for global SaaS products',
                'hero_description' => 'This solution page targets activation-stage workflows: authentication, social sign-in, onboarding progression, and locale-aware UX across marketing and app surfaces.',
                'hero_image' => 'storage/marketing/auth-login-focus.png',
                'hero_image_alt' => 'Authentication screen with email login and social sign-in options',
                'pillars' => [
                    [
                        'title' => 'Activation-ready authentication',
                        'copy' => 'Email/password login and social continuation paths are built into the first-touch experience.',
                    ],
                    [
                        'title' => 'Onboarding route structure',
                        'copy' => 'Dedicated onboarding routes support setup progression and account initialization before normal app usage.',
                    ],
                    [
                        'title' => 'Locale-aware marketing and app',
                        'copy' => 'Locale switching and translation support are included for customer-facing pages and product experience.',
                    ],
                ],
                'screens' => [
                    [
                        'title' => 'Auth entry experience',
                        'copy' => 'Guide new users through sign-in with clear primary actions.',
                        'image' => 'storage/marketing/auth-login-focus.png',
                        'alt' => 'Login screen with social providers and credentials',
                        'size' => 'wide',
                    ],
                    [
                        'title' => 'Checkout continuation',
                        'copy' => 'Move users from activation into monetization without losing context.',
                        'image' => 'storage/marketing/checkout-form-stripe-focus.png',
                        'alt' => 'Checkout details form in subscription flow',
                        'size' => 'compact',
                    ],
                    [
                        'title' => 'Settings and feature flags',
                        'copy' => 'Control optional marketing surfaces and defaults through app settings.',
                        'image' => 'storage/marketing/localhost_8000_admin_manage-settings.png',
                        'alt' => 'Admin settings page with feature flags and billing options',
                        'size' => 'compact',
                    ],
                    [
                        'title' => 'Brand and template controls',
                        'copy' => 'Align onboarding visuals with platform branding from admin.',
                        'image' => 'storage/marketing/admin-manage-branding-focus.png',
                        'alt' => 'Brand management screen for theme and identity settings',
                        'size' => 'wide',
                    ],
                ],
                'coverage' => [
                    'Email and social authentication flows',
                    'Onboarding show, update, and skip routes',
                    'Locale switcher and locale middleware',
                    'Marketing routes aligned with translated UI',
                    'Profile and account settings surfaces',
                ],
                'faq' => [
                    [
                        'q' => 'Can I run multilingual marketing pages with this starter?',
                        'a' => 'Yes. Locale switching and translation support are included for customer-facing marketing and app experiences.',
                    ],
                    [
                        'q' => 'Are social login paths included by default?',
                        'a' => 'Yes. Social sign-in support is available alongside email/password authentication.',
                    ],
                    [
                        'q' => 'Does onboarding have dedicated routes and logic?',
                        'a' => 'Yes. The starter includes onboarding routes for show, update, and skip progression workflows.',
                    ],
                    [
                        'q' => 'Can onboarding connect directly into checkout?',
                        'a' => 'Yes. The stack includes activation and checkout surfaces so teams can build continuous first-session journeys.',
                    ],
                ],
                'related' => [
                    'laravel-stripe-paddle-billing-starter',
                    'filament-admin-operations-for-saas',
                    'laravel-saas-blog-and-seo-starter',
                ],
            ],
        ];
    }
}
