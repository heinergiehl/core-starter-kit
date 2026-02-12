@extends('layouts.marketing')

@section('template', 'docs')
@section('body_class', 'docs-page')
@section('title', ($doc['title'] ?? __('Documentation')) . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('Technical documentation for this starter kit.'))

@section('content')
    <section class="docs-shell py-8 sm:py-12">
        <div class="mb-6">
            <a href="{{ route('docs.index') }}" class="docs-back-link">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                {{ __('Back to documentation') }}
            </a>
        </div>

        <div class="docs-layout">
            <aside class="docs-sidebar" x-data="{ query: '' }">
                <p class="docs-sidebar-label">{{ __('Documentation') }}</p>

                <div class="docs-sidebar-search">
                    <label class="sr-only" for="docs-sidebar-search">{{ __('Search docs') }}</label>
                    <input id="docs-sidebar-search" type="search" class="docs-search-input docs-search-input-sm"
                        placeholder="{{ __('Filter docs…') }}" x-model.debounce.150ms="query" />
                </div>

                <nav class="docs-sidebar-nav">
                    @foreach ($docs as $item)
                        @php $isCurrent = ($item['slug'] ?? null) === ($doc['slug'] ?? null); @endphp
                        @php($search = str(($item['title'] ?? '').' '.($item['slug'] ?? ''))->lower()->toString())
                        <a href="{{ route('docs.show', ['page' => $item['slug']]) }}"
                            data-search="{{ $search }}" data-current="{{ $isCurrent ? '1' : '0' }}"
                            x-show="query.trim() === '' || $el.dataset.search.includes(query.trim().toLowerCase()) || $el.dataset.current === '1'"
                            class="{{ $isCurrent ? 'docs-sidebar-link docs-sidebar-link-active' : 'docs-sidebar-link' }}">
                            {{ $item['title'] }}
                        </a>
                    @endforeach
                </nav>
            </aside>

            <article class="docs-article">
                <header class="docs-article-header">
                    <h1 class="docs-article-title">{{ $doc['title'] }}</h1>
                    <p class="docs-article-summary">{{ $doc['summary'] }}</p>
                    <p class="docs-article-meta">
                        {{ $doc['filename'] }} · {{ __('Updated') }} {{ $doc['updated_at'] }}
                    </p>
                </header>

                <div class="docs-prose">
                    {!! $content !!}
                </div>

                @if (! empty($prevDoc) || ! empty($nextDoc))
                    <nav class="docs-pagination" aria-label="{{ __('Documentation navigation') }}">
                        @if (! empty($prevDoc))
                            <a href="{{ route('docs.show', ['page' => $prevDoc['slug']]) }}"
                                class="docs-pagination-link docs-pagination-link-prev">
                                <span class="docs-pagination-label">{{ __('Previous') }}</span>
                                <span class="docs-pagination-title">{{ $prevDoc['title'] }}</span>
                            </a>
                        @endif

                        @if (! empty($nextDoc))
                            <a href="{{ route('docs.show', ['page' => $nextDoc['slug']]) }}"
                                class="docs-pagination-link docs-pagination-link-next">
                                <span class="docs-pagination-label">{{ __('Next') }}</span>
                                <span class="docs-pagination-title">{{ $nextDoc['title'] }}</span>
                            </a>
                        @endif
                    </nav>
                @endif
            </article>

            @if (! empty($toc))
                <aside class="docs-toc">
                    <p class="docs-toc-label">{{ __('On this page') }}</p>
                    <nav class="docs-toc-nav">
                        @foreach ($toc as $entry)
                            <a href="#{{ $entry['id'] }}"
                                class="{{ $entry['level'] === 3 ? 'docs-toc-link docs-toc-link-nested' : 'docs-toc-link' }}">
                                {{ $entry['title'] }}
                            </a>
                        @endforeach
                    </nav>
                </aside>
            @endif
        </div>
    </section>
@endsection
