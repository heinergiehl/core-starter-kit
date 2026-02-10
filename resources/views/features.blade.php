@extends('layouts.marketing')

@section('title', __('SaaS features: auth, checkout, billing, and admin workflows') . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('Explore production-ready SaaS features: authentication, provider-aware checkout, product and user administration, branding controls, and content operations.'))
@section('og_image', asset('storage/marketing/checkout-provider-select-focus.png'))

@push('meta')
    <link rel="canonical" href="{{ route('features') }}">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="keywords" content="laravel saas features, laravel stripe paddle billing starter, filament admin panel saas operations, laravel blog seo starter, onboarding localization saas">
    <meta name="twitter:title" content="{{ __('SaaS features for real production launches') }}">
    <meta name="twitter:description" content="{{ __('From login and checkout to product operations and content workflows, this starter ships complete execution surfaces.') }}">

    @php
        $featureNames = [
            __('Authentication and social login'),
            __('Provider-aware checkout flow'),
            __('Stripe and Paddle billing support'),
            __('Product and pricing operations'),
            __('User and role administration'),
            __('Blog and documentation publishing'),
            __('Roadmap voting and announcements'),
            __('Sitemap, RSS, and Open Graph endpoints'),
            __('Localization and multi-language marketing'),
            __('Branding and theme settings'),
            __('Order and invoice operations'),
        ];
    @endphp

    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => __('SaaS Starter Features'),
            'url' => route('features'),
            'numberOfItems' => count($featureNames),
            'itemListElement' => collect($featureNames)->values()->map(fn ($name, $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $name,
            ])->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => __('Home'),
                    'item' => route('home'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => __('Features'),
                    'item' => route('features'),
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
@endpush

@section('content')
    @php
        $conversionSuite = [
            [
                'eyebrow' => __('Authentication'),
                'title' => __('Clean login flow with social sign-in support'),
                'description' => __('Reduce onboarding friction with a focused auth screen and built-in social continuation options.'),
                'image' => asset('storage/marketing/auth-login.png'),
                'preview' => asset('storage/marketing/auth-login-focus.png'),
                'alt' => __('Login experience with email and social login options'),
                'callout' => __('Email + social sign-in'),
            ],
            [
                'eyebrow' => __('Checkout Routing'),
                'title' => __('Customer chooses billing provider before payment'),
                'description' => __('Present Stripe or Paddle clearly, with plan context and transparent next action before charge.'),
                'image' => asset('storage/marketing/checkout-provider-select.png'),
                'preview' => asset('storage/marketing/checkout-provider-select-focus.png'),
                'alt' => __('Checkout screen displaying available payment provider options'),
                'callout' => __('Stripe or Paddle routing'),
            ],
            [
                'eyebrow' => __('Payment Completion'),
                'title' => __('Structured checkout form for high-intent buyers'),
                'description' => __('Collect core customer details, promo input, and payment handoff in one polished subscription flow.'),
                'image' => asset('storage/marketing/checkout-form-stripe.png'),
                'preview' => asset('storage/marketing/checkout-form-stripe-focus.png'),
                'alt' => __('Checkout form with customer details and continue to payment action'),
                'callout' => __('Profile data before payment'),
            ],
        ];

        $opsSuite = [
            [
                'eyebrow' => __('Catalog Ops'),
                'title' => __('Product and price governance in one place'),
                'description' => __('Handle product states, provider mappings, and rollout controls from a single operational table.'),
                'image' => asset('storage/marketing/admin-products.png'),
                'preview' => asset('storage/marketing/admin-products-focus.png'),
                'alt' => __('Admin products listing with status and provider columns'),
                'callout' => __('Product + provider status'),
            ],
            [
                'eyebrow' => __('User Ops'),
                'title' => __('User administration with role-aware actions'),
                'description' => __('Review accounts, roles, and support actions quickly with operational clarity for your team.'),
                'image' => asset('storage/marketing/admin-users.png'),
                'preview' => asset('storage/marketing/admin-users-focus.png'),
                'alt' => __('User administration table with role and action columns'),
                'callout' => __('Roles and support actions'),
            ],
            [
                'eyebrow' => __('Content Ops'),
                'title' => __('Deep blog editing and publishing workflow'),
                'description' => __('Move from post list to rich long-form editing without adopting an external CMS stack.'),
                'image' => asset('storage/marketing/admin-blog-editor.png'),
                'preview' => asset('storage/marketing/admin-blog-editor-focus.png'),
                'alt' => __('Blog editor interface with long-form article content and controls'),
                'callout' => __('Long-form editor built-in'),
            ],
            [
                'eyebrow' => __('Branding'),
                'title' => __('Control identity and theme from admin settings'),
                'description' => __('Adjust logos, templates, and brand defaults centrally so marketing and product stay aligned.'),
                'image' => asset('storage/marketing/localhost_8000_admin_manage-branding.png'),
                'preview' => asset('storage/marketing/admin-manage-branding-focus.png'),
                'alt' => __('Brand management settings with template and identity controls'),
                'callout' => __('Theme + brand governance'),
            ],
        ];

        $featureInventory = [
            [
                'eyebrow' => __('Billing and Commerce'),
                'description' => __('Production billing surfaces that cover catalog modeling, checkout orchestration, and lifecycle operations.'),
                'items' => [
                    __('Subscription and one-time product support'),
                    __('Stripe and Paddle provider adapters'),
                    __('Provider-aware checkout routing'),
                    __('Discount and coupon management'),
                    __('Order and invoice resources in admin'),
                    __('Billing portal access and invoice downloads'),
                ],
                'link_label' => __('See pricing and checkout'),
                'link_url' => route('pricing'),
            ],
            [
                'eyebrow' => __('Admin and Operations'),
                'description' => __('A unified operations surface for support, governance, and day-to-day SaaS execution.'),
                'items' => [
                    __('User, role, and permission administration'),
                    __('Product, price, and payment-provider resources'),
                    __('Subscription and customer records'),
                    __('Feature request and roadmap management'),
                    __('Brand settings and platform defaults'),
                    __('Operational widgets for revenue and usage metrics'),
                ],
                'link_label' => __('Explore roadmap and feedback'),
                'link_url' => route('roadmap'),
            ],
            [
                'eyebrow' => __('Content and Growth'),
                'description' => __('Built-in growth surfaces so publishing and discoverability stay close to product operations.'),
                'items' => [
                    __('Blog posts with categories, tags, and read-time'),
                    __('Rich editor with media attachments'),
                    __('SEO fields for title and meta description'),
                    __('Documentation routes and content pages'),
                    __('Announcement management for release communication'),
                    __('Public blog, docs, and roadmap navigation'),
                ],
                'link_label' => __('Visit blog and docs'),
                'link_url' => route('blog.index'),
            ],
            [
                'eyebrow' => __('SEO and Platform Infrastructure'),
                'description' => __('Technical foundations for organic traffic, social sharing, and multi-locale experiences.'),
                'items' => [
                    __('Sitemap.xml and RSS.xml endpoints'),
                    __('Open Graph image generation routes'),
                    __('Canonical tags and structured metadata patterns'),
                    __('Locale switching for marketing and app routes'),
                    __('Authentication and onboarding flows'),
                    __('Webhook ingestion pipeline for billing events'),
                ],
                'link_label' => __('Read implementation docs'),
                'link_url' => route('docs.index'),
            ],
        ];

        $screenLibrary = [
            [
                'title' => __('Payment provider operations'),
                'copy' => __('Enable and manage Stripe/Paddle providers with clear operational controls.'),
                'image' => asset('storage/marketing/localhost_8000_admin_payment-providers.png'),
                'alt' => __('Admin payment providers listing and activation controls'),
                'size' => 'featured',
            ],
            [
                'title' => __('Catalog management'),
                'copy' => __('Operate products, statuses, and provider mapping from one table.'),
                'image' => asset('storage/marketing/admin-products-focus.png'),
                'alt' => __('Products admin table with status and provider columns'),
                'size' => 'compact',
            ],
            [
                'title' => __('Customer support workflows'),
                'copy' => __('User list with roles and support actions for operational response.'),
                'image' => asset('storage/marketing/admin-users-focus.png'),
                'alt' => __('Users admin table with roles and actions'),
                'size' => 'compact',
            ],
            [
                'title' => __('Editorial execution'),
                'copy' => __('Long-form post editing with structured content and metadata support.'),
                'image' => asset('storage/marketing/admin-blog-editor-focus.png'),
                'alt' => __('Blog editor interface with rich content'),
                'size' => 'wide',
            ],
            [
                'title' => __('Brand governance'),
                'copy' => __('Centralized controls for identity, theme template, and defaults.'),
                'image' => asset('storage/marketing/admin-manage-branding-focus.png'),
                'alt' => __('Brand settings and template management screen'),
                'size' => 'compact',
            ],
            [
                'title' => __('Metrics visibility'),
                'copy' => __('Operational stats dashboard for SaaS performance monitoring.'),
                'image' => asset('storage/marketing/localhost_8000_admin_stats.png'),
                'alt' => __('Admin dashboard with SaaS metrics cards and charts'),
                'size' => 'compact',
            ],
        ];

        $solutionClusters = [
            [
                'title' => __('Laravel Stripe and Paddle billing starter'),
                'copy' => __('Targeting billing-intent keywords around multi-provider checkout, subscriptions, and invoice operations.'),
                'url' => route('solutions.show', 'laravel-stripe-paddle-billing-starter'),
            ],
            [
                'title' => __('Filament admin operations for SaaS'),
                'copy' => __('Targeting operations-intent keywords around user governance, product catalog, and admin execution.'),
                'url' => route('solutions.show', 'filament-admin-operations-for-saas'),
            ],
            [
                'title' => __('Laravel SaaS blog and SEO starter'),
                'copy' => __('Targeting organic growth keywords around blog workflows, metadata, sitemap, RSS, and OG routes.'),
                'url' => route('solutions.show', 'laravel-saas-blog-and-seo-starter'),
            ],
            [
                'title' => __('Laravel onboarding and localization starter'),
                'copy' => __('Targeting activation and internationalization keywords around sign-in, onboarding, and locale switching.'),
                'url' => route('solutions.show', 'laravel-saas-onboarding-and-localization'),
            ],
        ];
    @endphp

    <section class="relative overflow-hidden py-12 sm:py-16">
        <div class="absolute inset-0 -z-10 pointer-events-none bg-[radial-gradient(circle_at_top_left,_rgba(168,85,247,0.18),_transparent_52%)]"></div>
        <div class="grid gap-10 lg:grid-cols-[1fr_0.95fr] lg:items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">{{ __('Feature Overview') }}</p>
                <h1 class="mt-4 max-w-3xl font-display text-4xl font-bold leading-tight text-ink sm:text-6xl">
                    {{ __('Production features that move users from first visit to paid account.') }}
                </h1>
                <p class="mt-6 max-w-2xl text-base leading-7 text-ink/70 sm:text-lg">
                    {{ __('This feature map covers conversion and operations together, so your team can launch, monetize, and scale without assembling disconnected tools.') }}
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="{{ route('pricing') }}" class="btn-primary text-center">{{ __('See Pricing') }}</a>
                    <a href="{{ route('docs.index') }}" class="btn-secondary text-center">{{ __('Read Docs') }}</a>
                </div>
                <div class="mt-7 flex flex-wrap gap-2">
                    <span class="rounded-full border border-ink/15 px-3 py-1 text-xs font-semibold text-ink/70">{{ __('Authentication') }}</span>
                    <span class="rounded-full border border-ink/15 px-3 py-1 text-xs font-semibold text-ink/70">{{ __('Checkout Flow') }}</span>
                    <span class="rounded-full border border-ink/15 px-3 py-1 text-xs font-semibold text-ink/70">{{ __('Billing Providers') }}</span>
                    <span class="rounded-full border border-ink/15 px-3 py-1 text-xs font-semibold text-ink/70">{{ __('Operations') }}</span>
                    <span class="rounded-full border border-ink/15 px-3 py-1 text-xs font-semibold text-ink/70">{{ __('SEO Ready') }}</span>
                </div>
            </div>

            <figure class="glass-panel rounded-3xl p-3 shadow-xl shadow-ink/10">
                <img
                    src="{{ asset('storage/marketing/checkout-form-stripe-focus.png') }}"
                    alt="{{ __('Checkout form with plan details and payment continuation') }}"
                    class="h-full w-full rounded-2xl border border-ink/10 bg-white/95 p-2 object-contain"
                    loading="eager"
                    fetchpriority="high"
                >
            </figure>
        </div>
    </section>

    <section class="py-10 sm:py-12">
        <div class="mb-7">
            <h2 class="font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('Acquisition and conversion flow') }}</h2>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-ink/70 sm:text-base">
                {{ __('These screens represent the critical pre-revenue journey: authentication, provider-aware checkout, and successful payment handoff.') }}
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            @foreach ($conversionSuite as $card)
                <article class="group glass-panel rounded-[26px] p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-primary/10">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">{{ $card['eyebrow'] }}</p>
                    <h3 class="mt-3 font-display text-2xl font-semibold text-ink">{{ $card['title'] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-ink/70">{{ $card['description'] }}</p>

                    <a href="{{ $card['image'] }}" target="_blank" rel="noopener noreferrer" class="mt-4 block overflow-hidden rounded-xl border border-ink/10 bg-gradient-to-b from-ink/5 to-transparent p-2 dark:from-white/5">
                        <div class="relative aspect-[16/10] overflow-hidden rounded-lg border border-ink/10 bg-white shadow-sm shadow-ink/10 dark:bg-white/5">
                            <img
                                src="{{ $card['preview'] }}"
                                alt="{{ $card['alt'] }}"
                                class="h-full w-full object-contain p-2"
                                loading="lazy"
                                decoding="async"
                            >
                            <div class="absolute bottom-2 left-2 rounded-full bg-black/70 px-2.5 py-1 text-[10px] font-semibold tracking-wide text-white backdrop-blur">
                                {{ $card['callout'] }}
                            </div>
                        </div>
                    </a>
                    <a href="{{ $card['image'] }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex text-[11px] font-semibold uppercase tracking-[0.14em] text-primary transition hover:text-primary/80">{{ __('Open full screenshot') }}</a>
                </article>
            @endforeach
        </div>
    </section>

    <section class="space-y-8 py-8 sm:space-y-10 sm:py-12">
        <div>
            <h2 class="font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('Operations and growth toolkit') }}</h2>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-ink/70 sm:text-base">
                {{ __('Once customers are active, these admin workflows keep product catalog, users, and content execution organized at scale.') }}
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($opsSuite as $item)
                <article class="grid gap-6 rounded-[28px] border border-ink/10 bg-white/75 p-5 shadow-sm backdrop-blur-sm dark:bg-white/5 sm:p-6">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">{{ $item['eyebrow'] }}</p>
                        <h3 class="mt-2 font-display text-2xl font-semibold text-ink">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-sm leading-7 text-ink/70">{{ $item['description'] }}</p>
                    </div>

                    <a href="{{ $item['image'] }}" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-xl border border-ink/10 bg-gradient-to-b from-ink/5 to-transparent p-2 dark:from-white/5">
                        <div class="relative aspect-[16/10] overflow-hidden rounded-lg border border-ink/10 bg-white shadow-sm shadow-ink/10 dark:bg-white/5">
                            <img
                                src="{{ $item['preview'] }}"
                                alt="{{ $item['alt'] }}"
                                class="h-full w-full object-contain p-2"
                                loading="lazy"
                                decoding="async"
                            >
                            <div class="absolute bottom-2 left-2 rounded-full bg-black/70 px-2.5 py-1 text-[10px] font-semibold tracking-wide text-white backdrop-blur">
                                {{ $item['callout'] }}
                            </div>
                        </div>
                    </a>
                </article>
            @endforeach
        </div>
    </section>

    <section class="py-12 sm:py-16">
        <div class="glass-panel rounded-[32px] p-8 sm:p-10">
            <div class="mb-8">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">{{ __('Complete Feature Inventory') }}</p>
                <h2 class="mt-3 font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('Everything currently shipped in this SaaS starter') }}</h2>
                <p class="mt-3 max-w-4xl text-sm leading-7 text-ink/70 sm:text-base">
                    {{ __('This inventory maps the production feature set across billing, admin operations, publishing, and SEO infrastructure so teams can evaluate technical coverage before implementation begins.') }}
                </p>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                @foreach ($featureInventory as $cluster)
                    <article class="rounded-[24px] border border-ink/10 bg-white/85 p-5 shadow-sm dark:bg-white/5 sm:p-6">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">{{ $cluster['eyebrow'] }}</p>
                        <p class="mt-2 text-sm leading-7 text-ink/70">{{ $cluster['description'] }}</p>
                        <ul class="mt-4 space-y-2.5">
                            @foreach ($cluster['items'] as $item)
                                <li class="flex items-start gap-2 text-sm text-ink/80">
                                    <span class="mt-[0.42rem] h-1.5 w-1.5 rounded-full bg-primary"></span>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ $cluster['link_url'] }}" class="mt-5 inline-flex text-xs font-semibold uppercase tracking-[0.14em] text-primary transition hover:text-primary/80">
                            {{ $cluster['link_label'] }}
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-6 sm:py-10">
        <div class="rounded-[32px] border border-ink/10 bg-gradient-to-br from-white/85 via-white/70 to-primary/5 p-8 shadow-lg shadow-ink/5 dark:from-white/[0.04] dark:via-white/[0.03] dark:to-primary/15 sm:p-10">
            <div class="mb-8">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">{{ __('Real Screen Library') }}</p>
                <h2 class="mt-3 font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('Visual proof across operations, billing, and growth workflows') }}</h2>
                <p class="mt-3 max-w-4xl text-sm leading-7 text-ink/70 sm:text-base">
                    {{ __('Adding more images helps when each image proves a distinct workflow. These screenshots are selected to show coverage breadth and reduce ambiguity for technical buyers.') }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($screenLibrary as $shot)
                    @php
                        $sizeClass = match ($shot['size']) {
                            'featured' => 'sm:col-span-2 lg:col-span-2',
                            'wide' => 'sm:col-span-2 lg:col-span-2',
                            default => '',
                        };
                        $aspectClass = match ($shot['size']) {
                            'featured' => 'aspect-[16/9]',
                            'wide' => 'aspect-[16/9]',
                            default => 'aspect-[4/3]',
                        };
                    @endphp
                    <article class="{{ $sizeClass }} overflow-hidden rounded-2xl border border-ink/10 bg-white/85 p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/10 dark:bg-white/[0.04]">
                        <a href="{{ $shot['image'] }}" target="_blank" rel="noopener noreferrer" class="block">
                            <div class="relative {{ $aspectClass }} overflow-hidden rounded-xl border border-ink/10 bg-white dark:bg-white/5">
                                <img
                                    src="{{ $shot['image'] }}"
                                    alt="{{ $shot['alt'] }}"
                                    class="h-full w-full object-contain p-2"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </div>
                            <div class="px-1 pb-1 pt-4">
                                <h3 class="text-lg font-semibold text-ink">{{ $shot['title'] }}</h3>
                                <p class="mt-1 text-sm leading-6 text-ink/70">{{ $shot['copy'] }}</p>
                                <span class="mt-3 inline-flex text-[11px] font-semibold uppercase tracking-[0.14em] text-primary">{{ __('Open full screenshot') }}</span>
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-8 sm:py-12">
        <div class="glass-panel rounded-[30px] p-8 sm:p-10">
            <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">{{ __('Long-tail SEO Clusters') }}</p>
                    <h2 class="mt-3 font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('Dedicated landing pages for high-intent search topics') }}</h2>
                    <p class="mt-3 text-sm leading-7 text-ink/70 sm:text-base">
                        {{ __('Use these focused pages to rank for specific buying-intent queries while keeping internal links connected to pricing, docs, and core feature content.') }}
                    </p>
                </div>
                <a href="{{ route('solutions.index') }}" class="rounded-full border border-ink/15 px-5 py-2 text-sm font-semibold text-ink/70 transition hover:border-primary/40 hover:text-ink">
                    {{ __('Open Solution Hub') }}
                </a>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($solutionClusters as $cluster)
                    <article class="rounded-2xl border border-ink/10 bg-white/85 p-5 shadow-sm dark:bg-white/5">
                        <h3 class="text-lg font-semibold text-ink">{{ $cluster['title'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-ink/70">{{ $cluster['copy'] }}</p>
                        <a href="{{ $cluster['url'] }}" class="mt-4 inline-flex text-xs font-semibold uppercase tracking-[0.14em] text-primary transition hover:text-primary/80">
                            {{ __('Visit page') }}
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="pb-12 pt-8 sm:pb-16">
        <div class="rounded-[34px] border border-ink/10 bg-gradient-to-br from-secondary/10 via-white/70 to-primary/10 p-8 text-center shadow-xl shadow-ink/10 sm:p-12 dark:from-secondary/20 dark:via-white/5 dark:to-primary/20">
            <h2 class="font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('Ready to build on top of this feature set?') }}</h2>
            <p class="mx-auto mt-4 max-w-2xl text-ink/70">
                {{ __('Start from an integrated stack where authentication, checkout, billing, operations, and marketing already work together.') }}
            </p>
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                @auth
                    <a href="{{ url('/app') }}" class="btn-primary">{{ __('Open App') }}</a>
                @else
                    <a href="{{ route('register') }}" class="btn-primary">{{ __('Create Account') }}</a>
                    <a href="{{ route('pricing') }}" class="btn-secondary">{{ __('Compare Plans') }}</a>
                @endauth
            </div>
        </div>
    </section>
@endsection
