@extends('layouts.marketing')

@php
    use Illuminate\Support\Str;
    // Use SEO fields if available, fallback to auto-generated
    $metaDescription = $post->meta_description ?: ($post->excerpt ?: Str::limit(strip_tags($post->body_html ?? ''), 160));
    $metaTitle = $post->meta_title ?: $post->title;
@endphp

@section('title', $metaTitle . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', $metaDescription)
@section('og_type', 'article')
@section('og_image', route('og.blog', $post->slug, true))

@section('content')
    <article class="py-10">
        <div class="glass-panel rounded-3xl p-8 relative">
            {{-- Back link --}}
            <a href="{{ route('blog.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:text-primary/80 mb-6">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                {{ __('Back to blog') }}
            </a>

            {{-- Featured Image --}}
            @if($post->featured_image)
                <div class="rounded-2xl overflow-hidden mb-8 -mx-2 sm:-mx-4">
                    <img 
                        src="{{ asset('storage/' . $post->featured_image) }}" 
                        alt="{{ $post->title }}"
                        class="w-full h-64 sm:h-80 object-cover"
                    />
                </div>
            @endif

            {{-- Meta info --}}
            <div class="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.2em] text-ink/50">
                <span>{{ optional($post->published_at)->format('M d, Y') }}</span>
                @if($post->reading_time)
                    <span>•</span>
                    <span>{{ $post->reading_time }} {{ __('min read') }}</span>
                @endif
                @if ($post->category)
                    <span class="rounded-full bg-primary/10 px-2 py-1 text-primary">{{ $post->category->name }}</span>
                @endif
            </div>

            {{-- Title --}}
            <h1 class="mt-4 font-display text-4xl leading-tight">{{ $post->title }}</h1>

            {{-- Excerpt --}}
            @if ($post->excerpt)
                <p class="mt-3 text-lg text-ink/70 border-l-4 border-primary/30 pl-4 italic">{{ $post->excerpt }}</p>
            @endif

            {{-- Author --}}
            @if($post->author)
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

            {{-- Main Content - Render HTML from WYSIWYG --}}
            <div class="prose prose-lg mt-8 max-w-none text-ink/80 
                prose-headings:text-ink prose-headings:font-display
                prose-a:text-primary prose-a:no-underline hover:prose-a:underline
                prose-code:bg-ink/5 prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded
                prose-pre:bg-ink/5 prose-pre:border prose-pre:border-ink/10
                prose-blockquote:border-l-primary/50 prose-blockquote:bg-primary/5 prose-blockquote:py-1 prose-blockquote:px-4 prose-blockquote:rounded-r-lg
                prose-img:rounded-xl prose-img:shadow-lg">
                @if($post->body_html)
                    {!! $post->body_html !!}
                @elseif($post->body_markdown)
                    {!! Str::markdown($post->body_markdown) !!}
                @endif
            </div>

            {{-- Tags --}}
            @if($post->tags->count() > 0)
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
