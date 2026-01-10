@extends('layouts.marketing')

@section('title', __('Blog') . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('Release notes, billing insights, and architecture updates from the SaaS kit.'))

@section('content')
    <section class="py-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-secondary">{{ __('Journal') }}</p>
                <h1 class="mt-3 font-display text-4xl">{{ __('Shipping notes and product thinking') }}</h1>
            </div>
            <p class="text-sm text-ink/70">{{ __('Release notes, billing insights, and architecture updates.') }}</p>
        </div>

        <div class="mt-10 grid gap-6">
            @forelse ($posts as $post)
                <article class="glass-panel rounded-3xl p-6 relative group transition hover:border-primary/30">
                    <div class="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.2em] text-ink/50">
                        <span>{{ optional($post->published_at)->format('M d, Y') }}</span>
                        @if ($post->category)
                            <span class="rounded-full bg-primary/10 px-2 py-1 text-primary">{{ $post->category->name }}</span>
                        @endif
                    </div>
                    <h2 class="mt-4 text-2xl font-semibold text-ink">
                        <a href="{{ route('blog.show', $post->slug) }}" class="hover:text-primary transition">{{ $post->title }}</a>
                    </h2>
                    <p class="mt-2 text-sm text-ink/70">{{ $post->excerpt }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($post->tags as $tag)
                            <span class="rounded-full border border-ink/10 px-3 py-1 text-xs text-ink/70 bg-surface/50">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="glass-panel rounded-3xl p-10 text-center text-sm text-ink/70">
                    {{ __('No published posts yet.') }}
                </div>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $posts->links() }}
        </div>
    </section>
@endsection
