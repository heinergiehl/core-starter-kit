@extends('layouts.marketing')

@section('template', 'docs')
@section('body_class', 'docs-page')
@section('title', __('Documentation') . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('Browse setup guides and technical reference for this starter kit.'))

@section('content')
    <section class="docs-shell py-10 sm:py-14">
        <header class="docs-hero">
            <p class="docs-eyebrow">{{ __('Developer Guides') }}</p>
            <h1 class="docs-title">{{ __('Documentation') }}</h1>
            <p class="docs-subtitle">
                {{ __('Clear setup guides for billing, architecture, security, testing, and extension workflows.') }}
            </p>
        </header>

        @if (empty($docs))
            <div class="docs-empty-state">
                {{ __('No documentation files were found in the docs directory.') }}
            </div>
        @else
            <div class="docs-index-grid">
                @foreach ($docs as $doc)
                    <a href="{{ route('docs.show', ['page' => $doc['slug']]) }}" class="docs-index-card group">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="docs-index-card-title">{{ $doc['title'] }}</h2>
                            <span class="docs-index-card-arrow" aria-hidden="true">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </span>
                        </div>
                        <p class="docs-index-card-summary">{{ $doc['summary'] }}</p>
                        <p class="docs-index-card-meta">{{ $doc['slug'] }}.md</p>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
