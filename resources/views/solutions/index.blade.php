@extends('layouts.marketing')

@section('title', 'Laravel SaaS solution pages for billing, admin operations, and SEO growth - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', 'Explore long-tail Laravel SaaS solution pages: Stripe and Paddle billing, Filament admin operations, blog SEO workflows, and onboarding localization implementation.')
@section('og_image', asset('marketing/localhost_8000_admin.png'))

@push('meta')
    <link rel="canonical" href="{{ route('solutions.index') }}">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="keywords" content="laravel saas use cases, laravel stripe paddle billing starter, filament admin saas operations, laravel blog seo starter, laravel onboarding localization">

    @php
        $solutionList = collect($solutionPages)->map(fn ($page, $index) => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $page['card_title'],
            'url' => $page['url'],
        ])->all();
    @endphp
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Laravel SaaS Solution Pages',
            'url' => route('solutions.index'),
            'numberOfItems' => count($solutionPages),
            'itemListElement' => $solutionList,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
@endpush

@section('content')
    <section class="relative overflow-hidden py-12 sm:py-16">
        <div class="absolute inset-0 -z-10 pointer-events-none bg-[radial-gradient(circle_at_top_right,_rgba(99,102,241,0.18),_transparent_52%)]"></div>
        <div class="grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Solution Hub</p>
                <h1 class="mt-4 max-w-3xl font-display text-4xl font-bold leading-tight text-ink sm:text-6xl">
                    Long-tail SEO pages mapped to real Laravel SaaS workflows.
                </h1>
                <p class="mt-6 max-w-2xl text-base leading-7 text-ink/70 sm:text-lg">
                    Each page targets a distinct high-intent search topic and proves implementation using real screens from this starter.
                    This structure helps organic traffic while giving technical buyers clear implementation confidence.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="{{ route('features') }}" class="btn-primary text-center">See Full Feature Map</a>
                    <a href="{{ route('pricing') }}" class="btn-secondary text-center">View Pricing</a>
                </div>
            </div>

            <figure class="glass-panel rounded-3xl p-3 shadow-xl shadow-ink/10">
                <img
                    src="{{ asset('marketing/localhost_8000_admin.png') }}"
                    alt="Admin dashboard with operations and billing controls"
                    class="h-full w-full rounded-2xl border border-ink/10 object-cover"
                    loading="eager"
                    fetchpriority="high"
                >
            </figure>
        </div>
    </section>

    <section class="py-8 sm:py-12">
        <div class="grid gap-5 md:grid-cols-2">
            @foreach ($solutionPages as $page)
                <article class="overflow-hidden rounded-[26px] border border-ink/10 bg-white/85 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/10 dark:bg-white/5 sm:p-5">
                    <a href="{{ $page['url'] }}" class="block">
                        <div class="relative aspect-[16/9] overflow-hidden rounded-xl border border-ink/10 bg-white dark:bg-white/5">
                            <img
                                src="{{ asset($page['hero_image']) }}"
                                alt="{{ $page['hero_image_alt'] }}"
                                class="h-full w-full object-contain p-2"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                        <h2 class="mt-4 font-display text-2xl font-semibold text-ink">{{ $page['card_title'] }}</h2>
                        <p class="mt-2 text-sm leading-7 text-ink/70">{{ $page['summary'] }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach (collect($page['keywords'])->take(2) as $keyword)
                                <span class="rounded-full border border-ink/10 bg-white/80 px-2.5 py-1 text-[11px] font-medium text-ink/65 dark:bg-white/[0.04]">{{ $keyword }}</span>
                            @endforeach
                        </div>
                        <span class="mt-4 inline-flex text-xs font-semibold uppercase tracking-[0.14em] text-primary">Open solution page</span>
                    </a>
                </article>
            @endforeach
        </div>
    </section>
@endsection
