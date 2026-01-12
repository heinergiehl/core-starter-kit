<!DOCTYPE html>
@php
    use App\Domain\Settings\Models\BrandSetting;
    
    $activeTemplate = config('template.active', 'default');
    
    // Try to get the authenticated user's current team's template
    if (auth()->check() && auth()->user()->current_team_id) {
        $brandSetting = BrandSetting::where('team_id', auth()->user()->current_team_id)->first();
        if ($brandSetting && $brandSetting->template) {
            $activeTemplate = $brandSetting->template;
        }
    }
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-template="{{ $activeTemplate }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <script>
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            } else {
                document.documentElement.classList.remove('dark')
            }
        </script>

        @php
            $defaultTitle = $appBrandName ?? config('app.name', 'SaaS Kit');
            $pageTitle = trim($__env->yieldContent('title')) ?: $defaultTitle;
            $pageDescription = trim($__env->yieldContent('meta_description')) ?: __('Launch a polished SaaS with teams, billing, and clean architecture.');
            $ogImage = trim($__env->yieldContent('og_image'));

            if (!$ogImage) {
                $ogImage = route('og', [
                    'title' => $pageTitle,
                    'subtitle' => $pageDescription,
                ], true);
            }
        @endphp

        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $pageDescription }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:type" content="@yield('og_type', 'website')">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $ogImage }}">
        @stack('meta')
        <link rel="alternate" type="application/rss+xml" title="RSS" href="{{ route('rss') }}">
        <link rel="sitemap" type="application/xml" title="Sitemap" href="{{ route('sitemap') }}">

        @php
            $activeTemplate = config('template.active', 'default');
            $templateConfig = config("template.templates.{$activeTemplate}", []);
            $templateFonts = $templateConfig['fonts'] ?? [];
            $fontSans = $templateFonts['sans'] ?? config('saas.branding.fonts.sans', 'Plus Jakarta Sans');
            $fontDisplay = $templateFonts['display'] ?? config('saas.branding.fonts.display', 'Outfit');
            
            // Build Google Fonts URL based on template
            $fontFamilies = collect([$fontSans, $fontDisplay])
                ->unique()
                ->map(fn($f) => str_replace(' ', '+', $f) . ':wght@300;400;500;600;700')
                ->implode('&family=');
        @endphp

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family={{ $fontFamilies }}&display=swap" rel="stylesheet">

        <style>
            :root {
                --font-sans: '{{ $fontSans }}';
                --font-display: '{{ $fontDisplay }}';
            }
        </style>

        @if(isset($themeTokens))
            <style>
                :root {
                    --color-primary: {{ $themeTokens['primary'] ?? '14 116 144' }};
                    --color-secondary: {{ $themeTokens['secondary'] ?? '245 158 11' }};
                    --color-accent: {{ $themeTokens['accent'] ?? '239 68 68' }};
                    --color-bg: {{ $themeTokens['bg'] ?? '250 250 249' }};
                    --color-fg: {{ $themeTokens['fg'] ?? '15 23 42' }};
                }
            </style>
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-surface text-ink">
        <div class="min-h-screen bg-hero-glow">
            {{-- Unified Marketing Navigation --}}
            @include('partials.marketing-nav')
            
            {{-- Site-wide Announcements --}}
            <x-announcement-banner />

            <main class="mx-auto max-w-6xl px-6 pb-16">
                @yield('content')
            </main>

            {{-- Unified Marketing Footer --}}
            @include('partials.marketing-footer')
        </div>
    </body>
</html>
