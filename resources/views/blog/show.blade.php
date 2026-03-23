@extends('layouts.marketing')

@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $renderedContent = $content ?? ($post->body_html ?: Str::markdown($post->body_markdown ?? ''));
    $author = $post->author;
    $authorName = $author?->publicAuthorName() ?? ($appBrandName ?? config('app.name', 'SaaS Kit'));
    $authorTitle = $author?->publicAuthorTitle();
    $authorBio = $author?->publicAuthorBio();
    $authorAvatarUrl = $author?->publicAuthorAvatarUrl();
    $authorInitial = $author?->publicAuthorInitial() ?? Str::upper(Str::substr($authorName, 0, 1));
    $metaDescription = $post->meta_description ?: ($post->excerpt ?: Str::limit(strip_tags($renderedContent), 160));
    $metaTitle = $post->meta_title ?: $post->title;
    $articleUrl = route('blog.show', ['locale' => app()->getLocale(), 'slug' => $post->slug], true);
    $blogIndexUrl = route('blog.index', ['locale' => app()->getLocale()], true);
    $organizationId = route('home', ['locale' => app()->getLocale()], true).'#organization';
    $featuredImageUrl = $post->featured_image
        ? url(Storage::disk('public')->url($post->featured_image))
        : route('og.blog', ['locale' => app()->getLocale(), 'slug' => $post->slug], true);
    $wordCount = str_word_count(strip_tags($renderedContent));
    $articleKeywords = $post->tags->pluck('name')->implode(', ');
    $publishedLabel = $post->published_at?->locale(app()->getLocale())->isoFormat('LL');
    $hasToc = ! empty($toc);
@endphp

@section('title', $metaTitle . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', $metaDescription)
@section('canonical_url', $articleUrl)
@section('og_type', 'article')
@section('og_image', route('og.blog', ['locale' => app()->getLocale(), 'slug' => $post->slug], true))

@push('meta')
    <meta name="author" content="{{ $authorName }}">
    @if ($post->published_at)
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
    @endif
    @if ($post->updated_at)
        <meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}">
    @endif
    <meta property="article:author" content="{{ $authorName }}">
    @if ($post->category)
        <meta property="article:section" content="{{ $post->category->name }}">
    @endif
    @foreach ($post->tags as $tag)
        <meta property="article:tag" content="{{ $tag->name }}">
    @endforeach
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            '@id' => $articleUrl.'#article',
            'mainEntityOfPage' => $articleUrl,
            'headline' => $metaTitle,
            'description' => $metaDescription,
            'url' => $articleUrl,
            'inLanguage' => app()->getLocale(),
            'image' => [$featuredImageUrl],
            'datePublished' => optional($post->published_at)->toIso8601String(),
            'dateModified' => optional($post->updated_at ?? $post->published_at)->toIso8601String(),
            'author' => $post->author
                ? [
                    '@type' => 'Person',
                    'name' => $authorName,
                ]
                : [
                    '@type' => 'Organization',
                    'name' => $authorName,
                ],
            'publisher' => [
                '@id' => $organizationId,
            ],
            'articleSection' => $post->category?->name,
            'keywords' => $articleKeywords !== '' ? $articleKeywords : null,
            'wordCount' => $wordCount > 0 ? $wordCount : null,
            'timeRequired' => $post->reading_time ? 'PT'.$post->reading_time.'M' : null,
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
                    'item' => route('home', ['locale' => app()->getLocale()], true),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => __('Blog'),
                    'item' => $blogIndexUrl,
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $post->title,
                    'item' => $articleUrl,
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
@endpush

@section('content')
    <article class="py-10" x-data="blogToc(@js(collect($toc ?? [])->pluck('id')->values()->all()))" x-init="init()">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start">
            <div class="glass-panel relative rounded-3xl p-6 sm:p-8">
                <a href="{{ $blogIndexUrl }}" class="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-primary transition hover:text-primary/80">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    {{ __('Back to blog') }}
                </a>

                @if ($post->featured_image)
                    <div class="mb-8 overflow-hidden rounded-2xl">
                        <img
                            src="{{ Storage::disk('public')->url($post->featured_image) }}"
                            alt="{{ $post->title }}"
                            class="h-64 w-full object-cover sm:h-80"
                        />
                    </div>
                @endif

                <header class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.2em] text-ink/50">
                        @if ($publishedLabel)
                            <span>{{ $publishedLabel }}</span>
                        @endif
                        @if ($post->reading_time)
                            <span>&bull;</span>
                            <span>{{ $post->reading_time }} {{ __('min read') }}</span>
                        @endif
                        @if ($post->category)
                            <span class="rounded-full bg-primary/10 px-2 py-1 text-primary">{{ $post->category->name }}</span>
                        @endif
                    </div>

                    <h1 class="mt-4 font-display text-4xl leading-tight text-ink sm:text-5xl">{{ $post->title }}</h1>

                    @if ($post->excerpt)
                        <p class="mt-4 border-l-4 border-primary/30 pl-4 text-lg italic text-ink/70">{{ $post->excerpt }}</p>
                    @endif

                    <div class="mt-8 flex flex-col gap-4 rounded-2xl border border-ink/10 bg-white/70 p-4 sm:flex-row sm:items-start dark:border-white/10 dark:bg-white/5">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-base font-semibold text-primary">
                            @if ($authorAvatarUrl)
                                <img src="{{ $authorAvatarUrl }}" alt="{{ $authorName }}" class="h-full w-full object-cover" />
                            @else
                                <span>{{ $authorInitial }}</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-ink/45">{{ __('Written by') }}</p>
                            <p class="mt-2 text-base font-semibold text-ink">{{ $authorName }}</p>
                            @if ($authorTitle)
                                <p class="mt-1 text-sm text-ink/55">{{ $authorTitle }}</p>
                            @endif
                            @if ($authorBio)
                                <p class="mt-3 text-sm leading-6 text-ink/70">{{ $authorBio }}</p>
                            @endif
                        </div>
                    </div>
                </header>

                @if ($hasToc)
                    <div class="mt-8 rounded-2xl border border-ink/10 bg-white/60 p-4 xl:hidden dark:border-white/10 dark:bg-white/5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink/45">{{ __('On this page') }}</p>
                        <nav class="mt-3 grid gap-1 sm:grid-cols-2">
                            @foreach ($toc as $entry)
                                <a
                                    href="#{{ $entry['id'] }}"
                                    data-toc-heading="{{ $entry['id'] }}"
                                    class="{{ $entry['level'] === 3 ? 'docs-toc-link docs-toc-link-mobile docs-toc-link-nested' : 'docs-toc-link docs-toc-link-mobile' }}"
                                    :class="{ 'docs-toc-link-active': isActive('{{ $entry['id'] }}') }"
                                >
                                    {{ $entry['title'] }}
                                </a>
                            @endforeach
                        </nav>
                    </div>
                @endif

                <div class="docs-prose mt-10">
                    {!! $renderedContent !!}
                </div>

                @if ($post->tags->count() > 0)
                    <div class="mt-8 border-t border-ink/10 pt-6">
                        <p class="mb-3 text-xs uppercase tracking-widest text-ink/50">{{ __('Tags') }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($post->tags as $tag)
                                <a
                                    href="{{ route('blog.index', ['locale' => app()->getLocale(), 'tag' => $tag->slug]) }}"
                                    class="rounded-full border border-ink/10 bg-surface/50 px-3 py-1.5 text-xs text-ink/70 transition hover:border-primary/30 hover:text-primary"
                                >
                                    {{ $tag->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            @if ($hasToc)
                <aside class="docs-toc xl:mt-10 2xl:mt-12">
                    <p class="docs-toc-label">{{ __('On this page') }}</p>
                    <nav class="docs-toc-nav" x-ref="desktopTocNav">
                        @foreach ($toc as $entry)
                            <a
                                href="#{{ $entry['id'] }}"
                                data-toc-heading="{{ $entry['id'] }}"
                                class="{{ $entry['level'] === 3 ? 'docs-toc-link docs-toc-link-nested' : 'docs-toc-link' }}"
                                :class="{ 'docs-toc-link-active': isActive('{{ $entry['id'] }}') }"
                            >
                                {{ $entry['title'] }}
                            </a>
                        @endforeach
                    </nav>
                </aside>
            @endif
        </div>

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('blogToc', (headingIds = []) => ({
                    headingIds,
                    headings: [],
                    activeId: headingIds[0] ?? null,
                    boundUpdateActive: null,

                    init() {
                        this.headings = this.headingIds
                            .map((id) => document.getElementById(id))
                            .filter((heading) => heading instanceof HTMLElement);

                        if (this.headings.length === 0) {
                            return;
                        }

                        this.boundUpdateActive = () => this.updateActive();
                        this.updateActive();

                        window.addEventListener('scroll', this.boundUpdateActive, { passive: true });
                        window.addEventListener('resize', this.boundUpdateActive, { passive: true });

                        this.$watch('activeId', (id) => {
                            if (! id || ! this.$refs.desktopTocNav) {
                                return;
                            }

                            const activeLink = this.$refs.desktopTocNav.querySelector(`[data-toc-heading="${id}"]`);

                            activeLink?.scrollIntoView({
                                block: 'nearest',
                                inline: 'nearest',
                            });
                        });
                    },

                    destroy() {
                        if (! this.boundUpdateActive) {
                            return;
                        }

                        window.removeEventListener('scroll', this.boundUpdateActive);
                        window.removeEventListener('resize', this.boundUpdateActive);
                    },

                    isActive(id) {
                        return this.activeId === id;
                    },

                    updateActive() {
                        const threshold = 180;
                        let nextActiveId = this.headings[0]?.id ?? null;

                        this.headings.forEach((heading) => {
                            if (heading.getBoundingClientRect().top - threshold <= 0) {
                                nextActiveId = heading.id;
                            }
                        });

                        this.activeId = nextActiveId;
                    },
                }));
            });
        </script>
    </article>
@endsection
