@extends('layouts.marketing')

@section('title', $solutionPage['seo_title'] . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', $solutionPage['meta_description'])
@section('og_image', asset($solutionPage['hero_image']))

@push('meta')
    <link rel="canonical" href="{{ $solutionPage['url'] }}">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="keywords" content="{{ implode(', ', $solutionPage['keywords']) }}">

    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $solutionPage['seo_title'],
            'url' => $solutionPage['url'],
            'description' => $solutionPage['meta_description'],
            'inLanguage' => app()->getLocale(),
            'about' => $solutionPage['keywords'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($solutionPage['faq'])->values()->map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['a'],
                ],
            ])->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
@endpush

@push('preloads')
    <link rel="preload" as="image" href="{{ asset($solutionPage['hero_image']) }}" fetchpriority="high">
@endpush

@section('content')
    <section class="relative overflow-hidden py-12 sm:py-16">
        <div class="absolute inset-0 -z-10 pointer-events-none bg-[radial-gradient(circle_at_top_left,_rgba(168,85,247,0.16),_transparent_55%)]"></div>
        <div class="grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">{{ $solutionPage['hero_eyebrow'] }}</p>
                <h1 class="mt-4 max-w-3xl font-display text-4xl font-bold leading-tight text-ink sm:text-6xl">
                    {{ $solutionPage['hero_title'] }}
                </h1>
                <p class="mt-6 max-w-2xl text-base leading-7 text-ink/70 sm:text-lg">
                    {{ $solutionPage['hero_description'] }}
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="{{ route('pricing') }}" class="btn-primary text-center">{{ __('See Pricing') }}</a>
                    <a href="{{ route('features') }}" class="btn-secondary text-center">{{ __('Explore All Features') }}</a>
                    <a href="{{ route('docs.index') }}" class="text-sm font-semibold text-ink/70 transition hover:text-ink">
                        {{ __('Read Docs') }} ->
                    </a>
                </div>
                <div class="mt-7 flex flex-wrap gap-2">
                    @foreach ($solutionPage['keywords'] as $keyword)
                        <span class="rounded-full border border-ink/15 px-3 py-1 text-xs font-medium text-ink/65">{{ $keyword }}</span>
                    @endforeach
                </div>
            </div>

            <figure class="glass-panel rounded-3xl p-3 shadow-xl shadow-ink/10">
                <img
                    src="{{ asset($solutionPage['hero_image']) }}"
                    alt="{{ $solutionPage['hero_image_alt'] }}"
                    class="h-full w-full rounded-2xl border border-ink/10 bg-white/95 p-2 object-contain"
                    loading="eager"
                    fetchpriority="high"
                >
            </figure>
        </div>
    </section>

    <section class="py-8 sm:py-12">
        <div class="grid gap-5 md:grid-cols-3">
            @foreach ($solutionPage['pillars'] as $pillar)
                <article class="rounded-2xl border border-ink/10 bg-white/85 p-5 shadow-sm dark:bg-white/5">
                    <h2 class="font-display text-2xl font-semibold text-ink">{{ $pillar['title'] }}</h2>
                    <p class="mt-3 text-sm leading-7 text-ink/70">{{ $pillar['copy'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="py-8 sm:py-12">
        <div class="glass-panel rounded-[30px] p-8 sm:p-10">
            <h2 class="font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('Workflow proof with real screens') }}</h2>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-ink/70 sm:text-base">
                {{ __('Each screenshot below maps to an existing workflow in this starter. Click any card to inspect the full-resolution screen.') }}
            </p>

            <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($solutionPage['screens'] as $shot)
                    @php
                        $sizeClass = $shot['size'] === 'wide' ? 'sm:col-span-2 lg:col-span-2' : '';
                    @endphp
                    <article class="{{ $sizeClass }} overflow-hidden rounded-2xl border border-ink/10 bg-white/85 p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/10 dark:bg-white/5">
                        <a href="{{ asset($shot['image']) }}" target="_blank" rel="noopener noreferrer" class="block">
                            <div class="relative {{ $shot['size'] === 'wide' ? 'aspect-[16/9]' : 'aspect-[4/3]' }} overflow-hidden rounded-xl border border-ink/10 bg-white dark:bg-white/5">
                                <img
                                    src="{{ asset($shot['image']) }}"
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
        <div class="grid gap-6 lg:grid-cols-[1fr_0.9fr]">
            <article class="rounded-2xl border border-ink/10 bg-white/85 p-6 shadow-sm dark:bg-white/5">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Implementation Coverage') }}</p>
                <h2 class="mt-2 font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('What this solution includes right now') }}</h2>
                <ul class="mt-5 space-y-3">
                    @foreach ($solutionPage['coverage'] as $item)
                        <li class="flex items-start gap-2 text-sm leading-6 text-ink/80">
                            <span class="mt-2 h-1.5 w-1.5 rounded-full bg-primary"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </article>

            <article class="rounded-2xl border border-ink/10 bg-white/85 p-6 shadow-sm dark:bg-white/5">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Internal Links') }}</p>
                <h2 class="mt-2 font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('Continue your evaluation') }}</h2>
                <div class="mt-5 space-y-3">
                    <a href="{{ route('features') }}" class="block rounded-xl border border-ink/10 bg-white/85 px-4 py-3 text-sm font-semibold text-ink/75 transition hover:border-primary/35 hover:text-ink dark:bg-white/[0.04]">{{ __('Browse full feature map') }}</a>
                    <a href="{{ route('pricing') }}" class="block rounded-xl border border-ink/10 bg-white/85 px-4 py-3 text-sm font-semibold text-ink/75 transition hover:border-primary/35 hover:text-ink dark:bg-white/[0.04]">{{ __('Review pricing and plans') }}</a>
                    <a href="{{ route('docs.index') }}" class="block rounded-xl border border-ink/10 bg-white/85 px-4 py-3 text-sm font-semibold text-ink/75 transition hover:border-primary/35 hover:text-ink dark:bg-white/[0.04]">{{ __('Read docs and implementation notes') }}</a>
                    <a href="{{ route('solutions.index') }}" class="block rounded-xl border border-ink/10 bg-white/85 px-4 py-3 text-sm font-semibold text-ink/75 transition hover:border-primary/35 hover:text-ink dark:bg-white/[0.04]">{{ __('See all solution pages') }}</a>
                </div>
            </article>
        </div>
    </section>

    <section class="py-8 sm:py-12">
        <div class="glass-panel rounded-[30px] p-8 sm:p-10">
            <h2 class="font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('FAQ') }}</h2>
            <div class="mt-6 grid gap-3">
                @foreach ($solutionPage['faq'] as $faq)
                    <details class="group rounded-2xl border border-ink/10 bg-white/85 p-5 transition hover:border-primary/35 dark:bg-white/5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-ink">
                            <span>{{ $faq['q'] }}</span>
                            <span class="text-lg leading-none text-ink/45 transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-3 text-sm leading-7 text-ink/70">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    @if (! empty($relatedSolutions))
        <section class="py-8 sm:py-12">
            <div class="rounded-[30px] border border-ink/10 bg-gradient-to-br from-white/85 via-white/70 to-primary/10 p-8 shadow-lg shadow-ink/5 dark:from-white/[0.04] dark:via-white/[0.03] dark:to-primary/15 sm:p-10">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('Related Long-tail Pages') }}</p>
                <h2 class="mt-2 font-display text-3xl font-bold text-ink sm:text-4xl">{{ __('Explore adjacent solution clusters') }}</h2>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($relatedSolutions as $related)
                        <article class="overflow-hidden rounded-2xl border border-ink/10 bg-white/85 p-3 shadow-sm dark:bg-white/5">
                            <a href="{{ $related['url'] }}" class="block">
                                <div class="relative aspect-[4/3] overflow-hidden rounded-xl border border-ink/10 bg-white dark:bg-white/5">
                                    <img
                                        src="{{ asset($related['hero_image']) }}"
                                        alt="{{ $related['hero_image_alt'] }}"
                                        class="h-full w-full object-contain p-2"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </div>
                                <h3 class="mt-4 text-lg font-semibold text-ink">{{ $related['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-ink/70">{{ $related['summary'] }}</p>
                            </a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
