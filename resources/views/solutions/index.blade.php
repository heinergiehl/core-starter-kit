@extends('layouts.marketing')

@section('title', __('Laravel SaaS solution pages for billing, admin operations, and SEO growth') . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('Explore long-tail Laravel SaaS solution pages: Stripe and Paddle billing, Filament admin operations, blog SEO workflows, and onboarding localization implementation.'))
@section('og_image', asset('marketing/localhost_8000_admin.webp'))

@push('meta')
    <meta name="keywords" content="{{ __('laravel saas use cases, laravel stripe paddle billing starter, filament admin saas operations, laravel blog seo starter, laravel onboarding localization') }}">

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
            'name' => __('Laravel SaaS Solution Pages'),
            'url' => route('solutions.index'),
            'numberOfItems' => count($solutionPages),
            'itemListElement' => $solutionList,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
@endpush

@push('preloads')
    <link rel="preload" as="image" href="{{ asset('marketing/localhost_8000_admin.webp') }}" fetchpriority="high">
@endpush

@section('content')
    <section class="relative overflow-hidden py-16 sm:py-24">
        <div class="absolute inset-0 -z-10 pointer-events-none">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(99,102,241,0.18),_transparent_52%)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,_rgba(168,85,247,0.10),_transparent_45%)]"></div>
        </div>
        <div class="grid gap-12 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">{{ __('Solution Hub') }}</p>
                <h1 class="mt-5 max-w-3xl font-display text-4xl font-bold leading-[1.1] text-ink sm:text-5xl lg:text-6xl">
                    {{ __('Long-tail SEO pages mapped to') }}
                    <span class="text-gradient">{{ __('real Laravel SaaS') }}</span>
                    {{ __('workflows.') }}
                </h1>
                <p class="mt-6 max-w-2xl text-base leading-7 text-ink/60 sm:text-lg sm:leading-8">
                    {{ __('Each page targets a distinct high-intent search topic and proves implementation using real screens from this starter.') }}
                    {{ __('This structure helps organic traffic while giving technical buyers clear implementation confidence.') }}
                </p>
                <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="{{ route('features') }}" class="btn-primary text-center">{{ __('See Full Feature Map') }}</a>
                    <a href="{{ route('pricing') }}" class="btn-secondary text-center">{{ __('View Pricing') }}</a>
                </div>
            </div>

            <div class="relative">
                <div class="absolute -inset-4 rounded-[36px] bg-gradient-to-br from-primary/15 via-transparent to-secondary/15 blur-2xl opacity-60 pointer-events-none"></div>
                <figure class="relative glass-panel rounded-3xl p-3 shadow-xl shadow-ink/10">
                    <img
                        src="{{ asset('marketing/localhost_8000_admin.webp') }}"
                        alt="{{ __('Admin dashboard with operations and billing controls') }}"
                        class="h-full w-full rounded-2xl border border-ink/10 object-cover"
                        loading="eager"
                        fetchpriority="high"
                    >
                </figure>
            </div>
        </div>
    </section>

    <section class="py-8 sm:py-12">
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($solutionPages as $page)
                <article class="group overflow-hidden rounded-[26px] border border-ink/8 bg-white/85 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary/20 hover:shadow-xl hover:shadow-primary/10 dark:bg-white/5 sm:p-6">
                    <a href="{{ $page['url'] }}" class="block">
                        <div class="relative aspect-[16/9] overflow-hidden rounded-xl border border-ink/10 bg-white dark:bg-white/5">
                            <img
                                src="{{ asset($page['hero_image']) }}"
                                alt="{{ $page['hero_image_alt'] }}"
                                class="h-full w-full object-contain p-2 transition-transform duration-500 group-hover:scale-[1.03]"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                        <h2 class="mt-5 font-display text-xl font-semibold text-ink sm:text-2xl group-hover:text-primary transition-colors">{{ $page['card_title'] }}</h2>
                        <p class="mt-2 text-sm leading-7 text-ink/60">{{ $page['summary'] }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach (collect($page['keywords'])->take(2) as $keyword)
                                <span class="rounded-full border border-ink/8 bg-white/70 px-2.5 py-1 text-[11px] font-medium text-ink/55 dark:bg-white/[0.04]">{{ $keyword }}</span>
                            @endforeach
                        </div>
                        <span class="mt-4 inline-flex text-xs font-semibold uppercase tracking-[0.14em] text-primary group-hover:translate-x-1 transition-transform">{{ __('View use case') }} -></span>
                    </a>
                </article>
            @endforeach
        </div>
    </section>
@endsection
