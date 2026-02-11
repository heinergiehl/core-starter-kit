@extends('layouts.marketing')

@section('title', __('Laravel SaaS starter kit with auth, checkout, and admin workflows') . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('Launch faster with built-in authentication, checkout flows, product catalog management, user administration, and SEO-ready marketing pages.'))
@section('og_image', asset('marketing/checkout-form-stripe-focus.png'))

@push('meta')
    <link rel="canonical" href="{{ route('home') }}">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="keywords" content="{{ __('laravel saas starter kit, laravel stripe paddle billing starter, filament admin panel saas starter, laravel blog seo starter, onboarding localization saas') }}">
    <meta name="twitter:title" content="{{ __('Laravel SaaS starter kit with auth, checkout, and admin workflows') }}">
    <meta name="twitter:description" content="{{ __('Built-in login, checkout, billing, admin, and marketing surfaces so teams can ship production SaaS faster.') }}">

    @php
        $brandName = $appBrandName ?? config('app.name', 'SaaS Kit');
        $homeDescription = __('Launch faster with built-in authentication, checkout flows, product catalog management, user administration, and SEO-ready marketing pages.');
        $faqItems = [
            [
                'question' => __('What is included in this Laravel SaaS starter?'),
                'answer' => __('Authentication, billing flows, checkout with Stripe and Paddle, admin resources, content surfaces, and a polished marketing layer.'),
            ],
            [
                'question' => __('Can I launch with one-time and subscription pricing?'),
                'answer' => __('Yes. The starter supports one-time and recurring plans with configurable products, prices, and provider mappings.'),
            ],
            [
                'question' => __('Which payment providers are integrated out of the box?'),
                'answer' => __('Stripe and Paddle are integrated, including provider-aware checkout and billing operations.'),
            ],
            [
                'question' => __('Can customers choose their billing provider during checkout?'),
                'answer' => __('Yes. The checkout flow can present available providers before payment so customers understand the path and next step.'),
            ],
            [
                'question' => __('Does the starter include a real blog creation workflow?'),
                'answer' => __('Yes. You can create posts with title, slug, excerpt, rich content, featured image, category, tags, author, status, and publish date in the admin panel.'),
            ],
            [
                'question' => __('Can I optimize blog posts for SEO from admin?'),
                'answer' => __('Yes. Blog posts support SEO title and meta description fields, along with clean slugs, structured excerpts, and featured images.'),
            ],
            [
                'question' => __('Is user and role administration included for post-launch operations?'),
                'answer' => __('Yes. The admin area includes user management with role and permission workflows so support and operations teams can work from one system.'),
            ],
            [
                'question' => __('Can I customize branding without rebuilding the frontend?'),
                'answer' => __('Yes. Branding controls support logo, template selection, and related visual settings so you can adapt identity quickly.'),
            ],
            [
                'question' => __('Are docs and roadmap pages part of the starter?'),
                'answer' => __('Yes. Documentation and roadmap surfaces are built in so you can communicate product direction and usage guidance from day one.'),
            ],
            [
                'question' => __('Does this starter support multiple languages on marketing pages?'),
                'answer' => __('Yes. The starter includes localization support and locale-aware content structure for marketing and UI text.'),
            ],
            [
                'question' => __('Is this starter SEO-ready for marketing pages?'),
                'answer' => __('Yes. It includes metadata, Open Graph support, sitemap and RSS routes, and page structures optimized for discoverability.'),
            ],
        ];
    @endphp

    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $brandName,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'url' => route('home'),
            'description' => $homeDescription,
            'image' => asset('marketing/checkout-form-stripe-focus.png'),
            'screenshot' => [
                asset('marketing/auth-login-focus.png'),
                asset('marketing/checkout-provider-select-focus.png'),
                asset('marketing/admin-products-focus.png'),
            ],
            'featureList' => [
                __('Authentication and social login'),
                __('Checkout with Stripe and Paddle'),
                __('Admin product and pricing workflows'),
                __('Customer and user management'),
                __('Blog, docs, and roadmap pages'),
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => route('pricing'),
                'priceCurrency' => 'USD',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(
                fn ($item) => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ],
                $faqItems,
            ),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
@endpush

@push('preloads')
    <link rel="preload" as="image" href="{{ asset('marketing/localhost_8000_admin.png') }}" fetchpriority="high">
@endpush

@section('content')
    @php
        $journeyCards = [
            [
                'step' => '01',
                'kicker' => __('Authentication'),
                'title' => __('Start with conversion-ready login and social sign-in'),
                'description' => __('Reduce drop-off at first touch with clean login UX, remember-me support, and social authentication entry points.'),
                'seo' => __('Improves top-of-funnel activation for SaaS onboarding.'),
                'image' => asset('marketing/auth-login.png'),
                'preview' => asset('marketing/auth-login-focus.png'),
                'alt' => __('Login screen with email, password, and social sign-in options'),
                'callout' => __('Email + social sign-in in one step'),
            ],
            [
                'step' => '02',
                'kicker' => __('Checkout Routing'),
                'title' => __('Let users choose Stripe or Paddle in one flow'),
                'description' => __('Expose provider selection and plan context before payment so customers understand price, billing mode, and next step.'),
                'seo' => __('Supports multi-provider billing without split implementations.'),
                'image' => asset('marketing/checkout-provider-select.png'),
                'preview' => asset('marketing/checkout-provider-select-focus.png'),
                'alt' => __('Checkout screen showing Stripe and Paddle provider options'),
                'callout' => __('Provider choice before charge'),
            ],
            [
                'step' => '03',
                'kicker' => __('Payment Capture'),
                'title' => __('Complete checkout with structured customer details'),
                'description' => __('Capture email, name, and promo input in a polished payment handoff that keeps your checkout funnel trustworthy.'),
                'seo' => __('Structured checkout UX improves paid conversion quality.'),
                'image' => asset('marketing/checkout-form-stripe.png'),
                'preview' => asset('marketing/checkout-form-stripe-focus.png'),
                'alt' => __('Checkout form with customer details and continue to payment action'),
                'callout' => __('Guided details -> payment handoff'),
            ],
            [
                'step' => '04',
                'kicker' => __('Operations'),
                'title' => __('Manage products, plans, and lifecycle in admin'),
                'description' => __('Operate monetization from one panel: active catalog, provider imports, statuses, and quick edit actions for product rollout.'),
                'seo' => __('Keeps pricing and catalog governance centralized for teams.'),
                'image' => asset('marketing/admin-products.png'),
                'preview' => asset('marketing/admin-products-focus.png'),
                'alt' => __('Admin products table with filters, provider mapping, and status badges'),
                'callout' => __('Catalog, provider, status in one table'),
            ],
        ];

        $opsHighlights = [
            [
                'title' => __('User lifecycle control'),
                'copy' => __('Review accounts, roles, verification state, and support actions from one operational view so your team can resolve customer requests quickly.'),
                'image' => asset('marketing/admin-users.png'),
                'preview' => asset('marketing/admin-users-focus.png'),
                'alt' => __('Admin users table with role tags and actions'),
                'callout' => __('Roles + support actions'),
            ],
            [
                'title' => __('Blog creation and publishing workflow'),
                'copy' => __('Create long-form posts with structured metadata, media handling, status control, and SEO fields in the same content workflow.'),
                'image' => asset('marketing/admin-blog-editor.png'),
                'preview' => asset('marketing/admin-blog-editor-focus.png'),
                'alt' => __('Blog editor screen for long-form content in admin panel'),
                'callout' => __('Draft -> Schedule -> Publish'),
            ],
        ];

        $postLaunchPillars = [
            [
                'tag' => __('Support'),
                'title' => __('Customer operations stay visible'),
                'copy' => __('User verification, role context, and account actions stay in one operational surface so support resolution remains fast.'),
            ],
            [
                'tag' => __('Content'),
                'title' => __('Publishing stays inside product'),
                'copy' => __('Write updates and educational content without moving to a separate CMS, which reduces context-switching across teams.'),
            ],
            [
                'tag' => __('Governance'),
                'title' => __('Repeatable execution patterns'),
                'copy' => __('Billing, users, and editorial flows share consistent UI patterns, which improves onboarding and lowers operational mistakes.'),
            ],
        ];

        $blogCreationFlow = [
            [
                'step' => '01',
                'title' => __('Compose with structure'),
                'detail' => __('Start with title, excerpt, and rich body content in the built-in editor.'),
            ],
            [
                'step' => '02',
                'title' => __('Set slug and taxonomy'),
                'detail' => __('Refine URL slug and assign category/tags, including create-on-the-fly options.'),
            ],
            [
                'step' => '03',
                'title' => __('Attach visual context'),
                'detail' => __('Upload a featured image with a 16:9 crop for consistent listings and share previews.'),
            ],
            [
                'step' => '04',
                'title' => __('Control publish timing'),
                'detail' => __('Keep as draft, publish immediately, or schedule with a specific publish date.'),
            ],
            [
                'step' => '05',
                'title' => __('Tune for SEO'),
                'detail' => __('Set SEO title and meta description before publishing for better search visibility.'),
            ],
            [
                'step' => '06',
                'title' => __('Manage in one table'),
                'detail' => __('Track status, category, date, and read-time signals directly in the posts index.'),
            ],
        ];

        $proofChips = [
            [
                'value' => (string) (count($journeyCards) + count($opsHighlights)),
                'label' => __('Verified workflow previews'),
                'detail' => __('All screenshots shown are captured from this starter.'),
            ],
            [
                'value' => '2',
                'label' => __('Billing providers built-in'),
                'detail' => __('Stripe and Paddle flows are already wired.'),
            ],
            [
                'value' => '1',
                'label' => __('Unified stack'),
                'detail' => __('Marketing, app, and admin live in one codebase.'),
            ],
        ];

        $solutionLandingLinks = [
            [
                'title' => __('Laravel Stripe and Paddle billing starter'),
                'copy' => __('Dual-provider checkout, subscriptions, invoicing, and billing lifecycle operations.'),
                'url' => route('solutions.show', ['slug' => 'laravel-stripe-paddle-billing-starter']),
            ],
            [
                'title' => __('Filament admin operations for SaaS'),
                'copy' => __('User governance, catalog management, and day-to-day operations in one admin panel.'),
                'url' => route('solutions.show', ['slug' => 'filament-admin-operations-for-saas']),
            ],
            [
                'title' => __('Laravel SaaS blog and SEO starter'),
                'copy' => __('Editorial workflow with metadata controls, sitemap, RSS, and Open Graph support.'),
                'url' => route('solutions.show', ['slug' => 'laravel-saas-blog-and-seo-starter']),
            ],
            [
                'title' => __('Onboarding and localization for SaaS'),
                'copy' => __('Activation flow, social login, onboarding routes, and locale-aware UX surfaces.'),
                'url' => route('solutions.show', ['slug' => 'laravel-saas-onboarding-and-localization']),
            ],
        ];

        $comparisonRows = [
            [
                'topic' => __('Checkout delivery'),
                'generic' => __('Manual integration, fragmented provider logic, and custom edge-case handling.'),
                'starter' => __('Provider-aware checkout, plan context, and conversion-focused payment handoff already designed.'),
            ],
            [
                'topic' => __('Operations'),
                'generic' => __('Product, user, and content tooling assembled from multiple plugins over time.'),
                'starter' => __('Catalog, user administration, and editorial workflows ship with one coherent admin workspace.'),
            ],
            [
                'topic' => __('Marketing readiness'),
                'generic' => __('SEO and proof sections are often postponed until after launch pressure.'),
                'starter' => __('Metadata, discoverability routes, and workflow proof screens are launch-ready from day one.'),
            ],
        ];

        $visibleFaqItems = $faqItems;
    @endphp

    <section class="relative overflow-hidden py-12 sm:py-16">
        <div class="absolute inset-0 -z-10 pointer-events-none bg-[radial-gradient(circle_at_top_right,_rgba(99,102,241,0.20),_transparent_50%)]"></div>
        <div class="grid items-center gap-10 lg:grid-cols-[1.05fr_0.95fr]">
            <div class="animate-fade-up">
                <div class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                    <span class="inline-flex h-2 w-2 rounded-full bg-primary"></span>
                    {{ __('Production-ready foundation for ambitious SaaS teams') }}
                </div>

                <h1 class="mt-6 max-w-3xl font-display text-4xl font-bold leading-tight text-ink sm:text-6xl">
                    {{ __('Launch your SaaS with code quality that feels senior from day one.') }}
                </h1>

                <p class="mt-6 max-w-2xl text-base leading-7 text-ink/70 sm:text-lg">
                    {{ __('Authentication, billing, admin operations, and growth pages are already integrated so your team can focus on product strategy, not plumbing.') }}
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                    @auth
                        <a href="{{ url('/app') }}" class="btn-primary text-center">{{ __('Open App') }}</a>
                    @else
                        <a href="{{ route('register') }}" class="btn-primary text-center">{{ __('Start Building') }}</a>
                        <a href="{{ route('pricing') }}" class="btn-secondary text-center">{{ __('See Pricing') }}</a>
                    @endauth
                    <a href="{{ route('features') }}" class="text-sm font-semibold text-ink/70 transition hover:text-ink">
                        {{ __('Explore all features') }} ->
                    </a>
                </div>

                <div class="mt-10 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-ink/10 bg-white/70 p-4 backdrop-blur-md dark:bg-white/5">
                        <p class="text-xs uppercase tracking-[0.18em] text-ink/45">{{ __('Architecture') }}</p>
                        <p class="mt-2 text-sm font-semibold text-ink">{{ __('Domain-driven + modular') }}</p>
                    </div>
                    <div class="rounded-2xl border border-ink/10 bg-white/70 p-4 backdrop-blur-md dark:bg-white/5">
                        <p class="text-xs uppercase tracking-[0.18em] text-ink/45">{{ __('Billing') }}</p>
                        <p class="mt-2 text-sm font-semibold text-ink">{{ __('Stripe and Paddle ready') }}</p>
                    </div>
                    <div class="rounded-2xl border border-ink/10 bg-white/70 p-4 backdrop-blur-md dark:bg-white/5">
                        <p class="text-xs uppercase tracking-[0.18em] text-ink/45">{{ __('Admin UX') }}</p>
                        <p class="mt-2 text-sm font-semibold text-ink">{{ __('Filament-powered control center') }}</p>
                    </div>
                </div>
            </div>

            <div class="relative">
                <figure class="glass-panel rounded-3xl p-3 shadow-2xl shadow-ink/10">
                    <img
                        src="{{ asset('marketing/localhost_8000_admin.png') }}"
                        alt="{{ __('Main admin dashboard with business metrics and management shortcuts') }}"
                        class="h-full w-full rounded-2xl border border-ink/10 object-cover"
                        loading="eager"
                        fetchpriority="high"
                    >
                </figure>

                <figure class="absolute -bottom-6 -left-6 hidden w-56 overflow-hidden rounded-2xl border border-ink/10 bg-white/80 p-2 shadow-xl shadow-ink/10 backdrop-blur-md lg:block dark:bg-white/10">
                    <img
                        src="{{ asset('marketing/localhost_8000_admin_stats.png') }}"
                        alt="{{ __('Admin analytics view showing SaaS performance statistics') }}"
                        class="rounded-xl border border-ink/10"
                        loading="lazy"
                    >
                </figure>

            </div>
        </div>
    </section>

    <section class="py-8 sm:py-10">
        <div class="rounded-[28px] border border-ink/10 bg-white/75 p-6 shadow-sm backdrop-blur-md dark:bg-white/5 sm:p-8">
            <div class="grid gap-6 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">{{ __('Proof Layer') }}</p>
                    <h2 class="mt-3 font-display text-2xl font-bold text-ink sm:text-3xl">
                        {{ __('Real product surfaces, not decorative mockups.') }}
                    </h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-ink/70 sm:text-base">
                        {{ __('A strong SaaS landing page should prove execution quality quickly. These previews are pulled from working authentication, checkout, billing, and admin screens.') }}
                    </p>

                    <div class="mt-5 flex items-center gap-4">
                        <div class="flex -space-x-3">
                            <img src="{{ asset('marketing/auth-login-focus.png') }}" alt="{{ __('Authentication screenshot preview') }}" class="h-11 w-11 rounded-full border-2 border-white object-cover dark:border-ink/40">
                            <img src="{{ asset('marketing/checkout-provider-select-focus.png') }}" alt="{{ __('Checkout screenshot preview') }}" class="h-11 w-11 rounded-full border-2 border-white object-cover dark:border-ink/40">
                            <img src="{{ asset('marketing/admin-products-focus.png') }}" alt="{{ __('Operations screenshot preview') }}" class="h-11 w-11 rounded-full border-2 border-white object-cover dark:border-ink/40">
                        </div>
                        <p class="text-sm font-medium text-ink/75">{{ __('Each card links to its full screenshot for inspection.') }}</p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($proofChips as $chip)
                        <article class="rounded-2xl border border-ink/10 bg-white/85 p-4 dark:bg-white/5">
                            <p class="font-display text-3xl font-bold text-ink">{{ $chip['value'] }}</p>
                            <p class="mt-1 text-sm font-semibold text-ink">{{ $chip['label'] }}</p>
                            <p class="mt-1 text-xs leading-5 text-ink/60">{{ $chip['detail'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="py-8 sm:py-10">
        <div class="glass-panel rounded-[30px] p-6 sm:p-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">{{ __('SEO Solution Pages') }}</p>
                    <h2 class="mt-3 font-display text-2xl font-bold text-ink sm:text-3xl">{{ __('Long-tail landing pages for high-intent SaaS keywords') }}</h2>
                    <p class="mt-3 text-sm leading-7 text-ink/70 sm:text-base">
                        {{ __('These pages target specific implementation intents and route traffic into pricing, docs, and feature workflows through focused content clusters.') }}
                    </p>
                </div>
                <a href="{{ route('solutions.index') }}" class="rounded-full border border-ink/15 px-5 py-2 text-sm font-semibold text-ink/70 transition hover:border-primary/40 hover:text-ink">
                    {{ __('Open Solution Hub') }}
                </a>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($solutionLandingLinks as $link)
                    <article class="rounded-2xl border border-ink/10 bg-white/85 p-4 shadow-sm dark:bg-white/5">
                        <h3 class="text-base font-semibold text-ink">{{ $link['title'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-ink/70">{{ $link['copy'] }}</p>
                        <a href="{{ $link['url'] }}" class="mt-4 inline-flex text-xs font-semibold uppercase tracking-[0.14em] text-primary transition hover:text-primary/80">
                            {{ __('Visit page') }}
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="features" class="py-16 sm:py-20" x-data="{ activeStep: 0 }">
        <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">{{ __('Launch Flow') }}</p>
                <h2 class="mt-3 font-display text-3xl font-bold text-ink sm:text-4xl">
                    {{ __('From first sign-in to paid customer, every critical step is already designed.') }}
                </h2>
                <p class="mt-4 text-ink/70">
                    {{ __('Switch between steps to inspect details in a larger proof view instead of tiny screenshot grids.') }}
                </p>
            </div>
            <a href="{{ route('features') }}" class="rounded-full border border-ink/15 px-5 py-2 text-sm font-semibold text-ink/70 transition hover:border-primary/40 hover:text-ink">
                {{ __('View Full Feature Breakdown') }}
            </a>
        </div>

        <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
            <div class="space-y-3">
                @foreach ($journeyCards as $card)
                    <button
                        type="button"
                        @click="activeStep = {{ $loop->index }}"
                        class="w-full rounded-2xl border p-4 text-left transition-all"
                        :class="activeStep === {{ $loop->index }} ? 'border-primary/50 bg-primary/10 shadow-lg shadow-primary/10' : 'border-ink/10 bg-white/70 hover:border-primary/30 dark:bg-white/5'"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-full bg-primary/15 px-2.5 py-1 text-xs font-bold text-primary">{{ __('Step :step', ['step' => $card['step']]) }}</span>
                            <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-ink/50">{{ $card['kicker'] }}</span>
                        </div>
                        <h3 class="mt-3 font-display text-xl font-semibold text-ink">{{ $card['title'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-ink/70">{{ $card['description'] }}</p>
                    </button>
                @endforeach
            </div>

            <div class="glass-panel rounded-[30px] p-4 sm:p-6">
                <div class="relative min-h-[28rem] sm:min-h-[30rem] lg:min-h-[34rem]">
                    @foreach ($journeyCards as $card)
                        <figure
                            x-show="activeStep === {{ $loop->index }}"
                            x-transition:enter="transition ease-out duration-180"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-120"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            x-cloak
                            :class="activeStep === {{ $loop->index }} ? 'relative z-10 space-y-4' : 'absolute inset-0 z-0 pointer-events-none space-y-4'"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-primary/15 px-2.5 py-1 text-xs font-bold text-primary">{{ __('Step :step', ['step' => $card['step']]) }}</span>
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-ink/50">{{ $card['kicker'] }}</span>
                                </div>
                                <a href="{{ $card['image'] }}" target="_blank" rel="noopener noreferrer" class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink/55 transition hover:text-primary">{{ __('Open full screenshot') }}</a>
                            </div>

                            <a href="{{ $card['image'] }}" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-2xl border border-ink/10 bg-gradient-to-b from-ink/5 to-transparent p-2 dark:from-white/5">
                                <div class="relative aspect-[16/9] overflow-hidden rounded-xl border border-ink/10 bg-white shadow-sm shadow-ink/10 dark:bg-white/5">
                                    <img
                                        src="{{ $card['preview'] }}"
                                        alt="{{ $card['alt'] }}"
                                        class="h-full w-full object-contain p-2"
                                        loading="eager"
                                        decoding="async"
                                    >
                                    <div class="pointer-events-none absolute inset-0 ring-1 ring-black/10 dark:ring-white/10"></div>
                                    <div class="absolute bottom-3 left-3 rounded-full bg-black/70 px-3 py-1.5 text-[11px] font-semibold tracking-wide text-white backdrop-blur">
                                        {{ $card['callout'] }}
                                    </div>
                                </div>
                            </a>

                            <figcaption class="min-h-[6.5rem] rounded-xl border border-ink/10 bg-white/70 p-4 text-sm text-ink/75 dark:bg-white/5">
                                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Why this matters') }}</span>
                                <p class="mt-2 leading-6">{{ $card['seo'] }}</p>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 sm:py-16">
        <div class="relative overflow-hidden rounded-[34px] border border-ink/10 bg-gradient-to-br from-white/90 via-white/75 to-secondary/10 p-6 shadow-xl shadow-ink/5 sm:p-10 dark:from-white/5 dark:via-white/[0.04] dark:to-secondary/20">
            <div class="pointer-events-none absolute -right-24 top-0 h-72 w-72 rounded-full bg-primary/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-secondary/10 blur-3xl"></div>

            @php
                $editorialShot = $opsHighlights[1] ?? null;
                $userOpsShot = $opsHighlights[0] ?? null;
                $editorialCapabilities = [
                    __('Rich editor'),
                    __('Featured images'),
                    __('Category and tags'),
                    __('Scheduling'),
                    __('SEO metadata'),
                    __('Read-time tracking'),
                ];
            @endphp

            <div class="relative grid gap-10 lg:grid-cols-[1.05fr_0.95fr]">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">{{ __('Post-Launch Operations') }}</p>
                    <h2 class="mt-3 font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('Your post-launch engine for support, publishing, and operational quality') }}</h2>
                    <p class="mt-4 text-sm leading-7 text-ink/70 sm:text-base">
                        {{ __('After customers start paying, growth depends on execution quality. This starter keeps user operations and content publishing inside one system so your team can ship updates and support users without tool fragmentation.') }}
                    </p>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('docs.index') }}" class="btn-secondary text-center">{{ __('Read Documentation') }}</a>
                        <a href="{{ route('blog.index') }}" class="rounded-full border border-ink/15 px-6 py-3 text-center text-sm font-semibold text-ink/75 transition hover:border-primary/40 hover:text-ink">{{ __('Visit Blog') }}</a>
                    </div>

                    <div class="mt-7 space-y-3">
                        @foreach ($postLaunchPillars as $pillar)
                            <article class="rounded-2xl border border-ink/10 bg-white/85 p-4 dark:bg-white/5">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-primary/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-primary">{{ $pillar['tag'] }}</span>
                                    <h3 class="text-sm font-semibold text-ink">{{ $pillar['title'] }}</h3>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-ink/70">{{ $pillar['copy'] }}</p>
                            </article>
                        @endforeach
                    </div>

                    <article class="mt-6 rounded-2xl border border-ink/10 bg-white/85 p-5 dark:bg-white/5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Blog publishing pipeline') }}</p>
                            <a href="{{ route('blog.index') }}" class="text-xs font-semibold uppercase tracking-[0.14em] text-ink/55 transition hover:text-primary">{{ __('See public blog') }} -></a>
                        </div>
                        <ol class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($blogCreationFlow as $flow)
                                <li class="rounded-xl border border-ink/10 bg-white/80 p-3 dark:bg-white/[0.04]">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-primary/10 px-2 text-[11px] font-bold text-primary">{{ $flow['step'] }}</span>
                                        <p class="text-sm font-semibold text-ink">{{ $flow['title'] }}</p>
                                    </div>
                                    <p class="mt-2 text-xs leading-5 text-ink/65">{{ $flow['detail'] }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </article>
                </div>

                <div class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                    @if ($editorialShot)
                        <article class="overflow-hidden rounded-[26px] border border-ink/10 bg-white/90 p-3 shadow-lg shadow-ink/10 dark:bg-white/5">
                            <a href="{{ $editorialShot['image'] }}" target="_blank" rel="noopener noreferrer" class="block">
                                <div class="relative aspect-[4/3] overflow-hidden rounded-2xl border border-ink/10 bg-white dark:bg-white/5">
                                    <img
                                        src="{{ $editorialShot['preview'] }}"
                                        alt="{{ $editorialShot['alt'] }}"
                                        class="h-full w-full object-contain p-2"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                    <div class="absolute bottom-2 left-2 rounded-full bg-black/70 px-2.5 py-1 text-[10px] font-semibold tracking-wide text-white backdrop-blur">
                                        {{ $editorialShot['callout'] }}
                                    </div>
                                </div>
                            </a>
                            <div class="px-1 pb-1 pt-4">
                                <h3 class="text-xl font-semibold text-ink">{{ $editorialShot['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-ink/70">{{ $editorialShot['copy'] }}</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @foreach ($editorialCapabilities as $capability)
                                        <span class="rounded-full border border-ink/10 bg-white/80 px-2.5 py-1 text-[11px] font-medium text-ink/70 dark:bg-white/[0.04]">{{ $capability }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </article>
                    @endif

                    @if ($userOpsShot)
                        <article class="rounded-2xl border border-ink/10 bg-white/85 p-3 shadow-sm dark:bg-white/5">
                            <a href="{{ $userOpsShot['image'] }}" target="_blank" rel="noopener noreferrer" class="grid gap-3 sm:grid-cols-[0.48fr_0.52fr] sm:items-center">
                                <div class="relative aspect-[4/3] overflow-hidden rounded-xl border border-ink/10 bg-white dark:bg-white/5">
                                    <img
                                        src="{{ $userOpsShot['preview'] }}"
                                        alt="{{ $userOpsShot['alt'] }}"
                                        class="h-full w-full object-contain p-2"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Support workflow') }}</p>
                                    <h4 class="mt-1 text-base font-semibold text-ink">{{ $userOpsShot['title'] }}</h4>
                                    <p class="mt-2 text-sm leading-6 text-ink/70">{{ $userOpsShot['copy'] }}</p>
                                </div>
                            </a>
                        </article>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 sm:py-16">
        <div class="glass-panel rounded-[32px] p-8 sm:p-10">
            <h2 class="font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('How teams ship faster with this starter') }}</h2>
            <div class="mt-8 grid gap-6 md:grid-cols-3">
                <article class="rounded-2xl border border-ink/10 bg-white/80 p-5 dark:bg-white/5">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Step 01') }}</p>
                    <h3 class="mt-3 text-lg font-semibold text-ink">{{ __('Configure') }}</h3>
                    <p class="mt-2 text-sm text-ink/70">{{ __('Set branding, providers, products, and pricing in minutes.') }}</p>
                </article>
                <article class="rounded-2xl border border-ink/10 bg-white/80 p-5 dark:bg-white/5">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Step 02') }}</p>
                    <h3 class="mt-3 text-lg font-semibold text-ink">{{ __('Convert') }}</h3>
                    <p class="mt-2 text-sm text-ink/70">{{ __('Guide users through login and checkout with structured, confidence-building flows.') }}</p>
                </article>
                <article class="rounded-2xl border border-ink/10 bg-white/80 p-5 dark:bg-white/5">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Step 03') }}</p>
                    <h3 class="mt-3 text-lg font-semibold text-ink">{{ __('Scale') }}</h3>
                    <p class="mt-2 text-sm text-ink/70">{{ __('Manage customers, content, and billing operations from one control center.') }}</p>
                </article>
            </div>
        </div>
    </section>

    <section class="py-12 sm:py-16">
        <div class="glass-panel rounded-[32px] p-8 sm:p-10">
            <div class="mb-8">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">{{ __('Decision Clarity') }}</p>
                <h2 class="mt-3 font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('What changes when teams start from this stack') }}</h2>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-ink/70 sm:text-base">
                    {{ __('The biggest design and engineering advantage is coherence: one system from acquisition to billing operations, instead of disconnected surfaces assembled under deadline pressure.') }}
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <article class="rounded-2xl border border-rose-300/30 bg-rose-50/40 p-5 dark:bg-rose-400/5">
                    <h3 class="font-display text-xl font-semibold text-ink">{{ __('Typical starter path') }}</h3>
                    <ul class="mt-4 space-y-4">
                        @foreach ($comparisonRows as $row)
                            <li class="rounded-xl border border-rose-300/30 bg-white/70 p-4 dark:bg-white/5">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-500/90">{{ $row['topic'] }}</p>
                                <p class="mt-2 text-sm leading-6 text-ink/70">{{ $row['generic'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </article>

                <article class="rounded-2xl border border-emerald-300/30 bg-emerald-50/40 p-5 dark:bg-emerald-400/5">
                    <h3 class="font-display text-xl font-semibold text-ink">{{ __('This starter approach') }}</h3>
                    <ul class="mt-4 space-y-4">
                        @foreach ($comparisonRows as $row)
                            <li class="rounded-xl border border-emerald-300/30 bg-white/70 p-4 dark:bg-white/5">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-600">{{ $row['topic'] }}</p>
                                <p class="mt-2 text-sm leading-6 text-ink/75">{{ $row['starter'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </article>
            </div>
        </div>
    </section>

    <section id="faq" class="py-12 sm:py-16">
        <div class="glass-panel rounded-[32px] p-8 sm:p-10">
            <div class="text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">{{ __('FAQ') }}</p>
                <h2 class="mt-3 font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('Questions teams ask before shipping') }}</h2>
                <p class="mx-auto mt-3 max-w-3xl text-sm leading-7 text-ink/70 sm:text-base">
                    {{ __('These are the practical concerns founders and product teams usually validate before committing to a starter foundation.') }}
                </p>
            </div>

            <div class="mx-auto mt-8 grid max-w-4xl gap-3">
                @foreach ($visibleFaqItems as $faq)
                    <details class="group rounded-2xl border border-ink/10 bg-white/80 p-5 transition hover:border-primary/35 dark:bg-white/5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-ink">
                            <span>{{ $faq['question'] }}</span>
                            <span class="text-lg leading-none text-ink/45 transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-3 text-sm leading-7 text-ink/70">{{ $faq['answer'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="pb-12 pt-8 sm:pb-16">
        <div class="relative overflow-hidden rounded-[34px] border border-ink/10 bg-gradient-to-br from-primary/10 via-white/70 to-secondary/10 p-8 text-center shadow-xl shadow-primary/10 sm:p-12 dark:from-primary/20 dark:via-white/5 dark:to-secondary/20">
            <h2 class="font-display text-3xl font-bold text-ink sm:text-4xl">
                {{ __('Build your SaaS on a foundation that already feels production-grade') }}
            </h2>
            <p class="mx-auto mt-4 max-w-2xl text-ink/70">
                {{ __('Ship product value faster by starting with an integrated stack for authentication, monetization, and operations.') }}
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
