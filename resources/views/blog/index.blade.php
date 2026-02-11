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

        {{-- Search & Filters --}}
        <div class="mt-8 glass-panel rounded-2xl p-4">
            <form action="{{ route('blog.index') }}" method="GET" class="flex flex-col gap-4 lg:flex-row lg:items-center">
                {{-- Search Input --}}
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-ink/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ $search ?? '' }}"
                        placeholder="{{ __('Search posts...') }}"
                        class="w-full pl-10 pr-4 py-2.5 bg-surface border border-ink/10 rounded-xl text-sm text-ink placeholder-ink/40 focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/20 transition"
                    />
                </div>

                {{-- Category Filter --}}
                <div class="flex-shrink-0 relative">
                    <select 
                        name="category" 
                        class="w-full lg:w-auto pl-4 pr-10 py-2.5 bg-surface border border-ink/10 rounded-xl text-sm text-ink focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/20 transition appearance-none cursor-pointer"
                        onchange="this.form.submit()"
                    >
                        <option value="">{{ __('All Categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->slug }}" {{ ($activeCategory?->id === $category->id) ? 'selected' : '' }}>
                                {{ $category->name }} ({{ $category->posts_count }})
                            </option>
                        @endforeach
                    </select>
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink/50 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>

                {{-- Tag Filter --}}
                <div class="flex-shrink-0 relative">
                    <select 
                        name="tag" 
                        class="w-full lg:w-auto pl-4 pr-10 py-2.5 bg-surface border border-ink/10 rounded-xl text-sm text-ink focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/20 transition appearance-none cursor-pointer"
                        onchange="this.form.submit()"
                    >
                        <option value="">{{ __('All Tags') }}</option>
                        @foreach ($tags as $tag)
                            <option value="{{ $tag->slug }}" {{ ($activeTag?->id === $tag->id) ? 'selected' : '' }}>
                                {{ $tag->name }} ({{ $tag->posts_count }})
                            </option>
                        @endforeach
                    </select>
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink/50 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>

                {{-- Search Button --}}
                <button type="submit" class="flex-shrink-0 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary/90 transition">
                    {{ __('Search') }}
                </button>
            </form>

            {{-- Active Filters Display --}}
            @if ($search || $activeCategory || $activeTag)
                <div class="mt-4 pt-4 border-t border-ink/10 flex flex-wrap items-center gap-2">
                    <span class="text-xs text-ink/50 uppercase tracking-wider">{{ __('Active filters:') }}</span>
                    
                    @if ($search)
                        <a href="{{ route('blog.index', array_filter(['category' => $activeCategory?->slug, 'tag' => $activeTag?->slug])) }}" 
                           class="inline-flex items-center gap-1.5 px-3 py-1 bg-primary/10 text-primary rounded-full text-xs font-medium hover:bg-primary/20 transition group">
                            <span>"{{ $search }}"</span>
                            <svg class="w-3.5 h-3.5 opacity-60 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </a>
                    @endif

                    @if ($activeCategory)
                        <a href="{{ route('blog.index', array_filter(['search' => $search, 'tag' => $activeTag?->slug])) }}" 
                           class="inline-flex items-center gap-1.5 px-3 py-1 bg-secondary/10 text-secondary rounded-full text-xs font-medium hover:bg-secondary/20 transition group">
                            <span>{{ $activeCategory->name }}</span>
                            <svg class="w-3.5 h-3.5 opacity-60 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </a>
                    @endif

                    @if ($activeTag)
                        <a href="{{ route('blog.index', array_filter(['search' => $search, 'category' => $activeCategory?->slug])) }}" 
                           class="inline-flex items-center gap-1.5 px-3 py-1 bg-ink/10 text-ink/70 rounded-full text-xs font-medium hover:bg-ink/20 transition group">
                            <span>#{{ $activeTag->name }}</span>
                            <svg class="w-3.5 h-3.5 opacity-60 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </a>
                    @endif

                    <a href="{{ route('blog.index') }}" class="text-xs text-ink/50 hover:text-primary transition ml-2">
                        {{ __('Clear all') }}
                    </a>
                </div>
            @endif
        </div>

        {{-- Posts Grid --}}
        <div class="mt-8 grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($posts as $post)
                <article class="glass-panel rounded-3xl p-6 relative group transition hover:border-primary/30 flex flex-col h-full">
                    
                    {{-- Featured Image (or placeholder) --}}
                    <div class="rounded-2xl overflow-hidden mb-6 aspect-video bg-surface relative">
                        @if($post->featured_image)
                            <img 
                                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($post->featured_image) }}" 
                                alt="{{ $post->title }}"
                                class="w-full h-full object-cover transition duration-500 group-hover:scale-105"
                            />
                        @else
                            {{-- Placeholder Pattern --}}
                            <div class="w-full h-full bg-gradient-to-br from-primary/5 to-secondary/5 flex items-center justify-center">
                                <svg class="w-12 h-12 text-primary/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                                </svg>
                            </div>
                        @endif
                    </div>

                    <div class="flex-1 flex flex-col">
                        <div class="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.2em] text-ink/50 mb-3">
                            <span>{{ optional($post->published_at)->format('M d, Y') }}</span>
                            @if ($post->reading_time)
                                <span>•</span>
                                <span>{{ $post->reading_time }} {{ __('min') }}</span>
                            @endif
                            @if ($post->category)
                                <a href="{{ route('blog.index', ['category' => $post->category->slug]) }}" 
                                   class="relative z-10 rounded-full bg-primary/10 px-2 py-1 text-primary hover:bg-primary/20 transition">
                                    {{ $post->category->name }}
                                </a>
                            @endif
                        </div>
                        <h2 class="text-xl font-semibold text-ink mb-2">
                            <a href="{{ route('blog.show', ['slug' => $post->slug]) }}" class="hover:text-primary transition before:absolute before:inset-0">{{ $post->title }}</a>
                        </h2>
                        <p class="text-sm text-ink/70 line-clamp-3 mb-4">{{ $post->excerpt }}</p>
                        
                        <div class="mt-auto flex flex-wrap gap-2 relative z-10">
                            @foreach ($post->tags->take(3) as $tag)
                                <a href="{{ route('blog.index', ['tag' => $tag->slug]) }}" 
                                   class="rounded-full border border-ink/10 px-2.5 py-1 text-[10px] uppercase tracking-wider text-ink/70 bg-surface/50 hover:border-primary/30 hover:text-primary transition">
                                    {{ $tag->name }}
                                </a>
                            @endforeach
                            @if($post->tags->count() > 3)
                                <span class="text-[10px] text-ink/50 self-center">+{{ $post->tags->count() - 3 }}</span>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="col-span-full glass-panel rounded-3xl p-10 text-center">
                    <svg class="w-12 h-12 mx-auto text-ink/30 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    @if ($search || $activeCategory || $activeTag)
                        <p class="text-sm text-ink/70">{{ __('No posts match your filters.') }}</p>
                        <a href="{{ route('blog.index') }}" class="mt-3 inline-block text-sm text-primary hover:underline relative z-10">
                            {{ __('Clear filters and view all posts') }}
                        </a>
                    @else
                        <p class="text-sm text-ink/70">{{ __('No published posts yet.') }}</p>
                    @endif
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        <div class="mt-8">
            {{ $posts->links() }}
        </div>
    </section>
@endsection
