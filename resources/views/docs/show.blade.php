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
            <aside class="docs-sidebar">
                <p class="docs-sidebar-label">{{ __('Documentation') }}</p>

                <nav class="docs-sidebar-nav">
                    @foreach ($docs as $item)
                        @php $isCurrent = ($item['slug'] ?? null) === ($doc['slug'] ?? null); @endphp
                        <a href="{{ route('docs.show', ['page' => $item['slug']]) }}"
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
                </header>

                <div class="docs-prose">
                    {!! $content !!}
                </div>
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
