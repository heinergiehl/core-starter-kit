@extends('layouts.marketing')

@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $metaDescription = $post->meta_description ?: ($post->excerpt ?: Str::limit(strip_tags($post->body_html ?? ''), 160));
    $metaTitle = $post->meta_title ?: $post->title;
    $articleUrl = route('blog.show', ['locale' => app()->getLocale(), 'slug' => $post->slug], true);
    $blogIndexUrl = route('blog.index', ['locale' => app()->getLocale()], true);
    $organizationId = route('home', ['locale' => app()->getLocale()], true).'#organization';
    $featuredImageUrl = $post->featured_image
        ? url(Storage::disk('public')->url($post->featured_image))
        : route('og.blog', ['locale' => app()->getLocale(), 'slug' => $post->slug], true);
    $wordCount = str_word_count(strip_tags($post->body_html ?: Str::markdown($post->body_markdown ?? '')));
    $articleKeywords = $post->tags->pluck('name')->implode(', ');
@endphp

@section('title', $metaTitle . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', $metaDescription)
@section('canonical_url', $articleUrl)
@section('og_type', 'article')
@section('og_image', route('og.blog', ['locale' => app()->getLocale(), 'slug' => $post->slug], true))

@push('meta')
    <meta name="author" content="{{ $post->author?->name ?? ($appBrandName ?? config('app.name', 'SaaS Kit')) }}">
    @if ($post->published_at)
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
    @endif
    @if ($post->updated_at)
        <meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}">
    @endif
    @if ($post->author)
        <meta property="article:author" content="{{ $post->author->name }}">
    @endif
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
            'author' => [
                '@type' => 'Person',
                'name' => $post->author?->name ?? ($appBrandName ?? config('app.name', 'SaaS Kit')),
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
    <article class="py-10">
        <div class="glass-panel rounded-3xl p-8 relative">
            <a href="{{ $blogIndexUrl }}" class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:text-primary/80 mb-6">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                {{ __('Back to blog') }}
            </a>

            @if ($post->featured_image)
                <div class="rounded-2xl overflow-hidden mb-8 -mx-2 sm:-mx-4">
                    <img
                        src="{{ Storage::disk('public')->url($post->featured_image) }}"
                        alt="{{ $post->title }}"
                        class="w-full h-64 sm:h-80 object-cover"
                    />
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.2em] text-ink/50">
                <span>{{ optional($post->published_at)->format('M d, Y') }}</span>
                @if ($post->reading_time)
                    <span>&bull;</span>
                    <span>{{ $post->reading_time }} {{ __('min read') }}</span>
                @endif
                @if ($post->category)
                    <span class="rounded-full bg-primary/10 px-2 py-1 text-primary">{{ $post->category->name }}</span>
                @endif
            </div>

            <h1 class="mt-4 font-display text-4xl leading-tight">{{ $post->title }}</h1>

            @if ($post->excerpt)
                <p class="mt-3 text-lg text-ink/70 border-l-4 border-primary/30 pl-4 italic">{{ $post->excerpt }}</p>
            @endif

            @if ($post->author)
                <div class="mt-6 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-semibold">
                        {{ strtoupper(substr($post->author->name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-ink">{{ $post->author->name }}</p>
                        <p class="text-xs text-ink/50">{{ __('Author') }}</p>
                    </div>
                </div>
            @endif

            <div class="prose prose-lg mt-8 max-w-none text-ink/80
                prose-headings:text-ink prose-headings:font-display
                prose-a:text-primary prose-a:no-underline hover:prose-a:underline
                prose-code:bg-ink/5 prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded
                prose-pre:bg-ink/5 prose-pre:border prose-pre:border-ink/10
                prose-blockquote:border-l-primary/50 prose-blockquote:bg-primary/5 prose-blockquote:py-1 prose-blockquote:px-4 prose-blockquote:rounded-r-lg
                prose-img:rounded-xl prose-img:shadow-lg">
                @if ($post->body_html)
                    {!! $post->body_html !!}
                @elseif ($post->body_markdown)
                    {!! Str::markdown($post->body_markdown) !!}
                @endif
            </div>

            @if ($post->tags->count() > 0)
                <div class="mt-8 pt-6 border-t border-ink/10">
                    <p class="text-xs uppercase tracking-widest text-ink/50 mb-3">{{ __('Tags') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($post->tags as $tag)
                            <span class="rounded-full border border-ink/10 px-3 py-1.5 text-xs text-ink/70 bg-surface/50 hover:border-primary/30 transition">
                                {{ $tag->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </article>
@endsection
