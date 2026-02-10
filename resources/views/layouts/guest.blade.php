<!DOCTYPE html>
@php
    use App\Domain\Settings\Services\BrandingService;
    
    $branding = app(BrandingService::class);
    $activeTemplate = $branding->templateForGuest();

    $themeStyle = '';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-template="{{ $activeTemplate }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-theme-init />

        @php
            $defaultTitle = $appBrandName ?? config('app.name', 'SaaS Kit');
            $pageTitle = trim($__env->yieldContent('title')) ?: $defaultTitle;
            $pageDescription = trim($__env->yieldContent('meta_description')) ?: 'Launch a polished SaaS with billing, auth, and clean architecture.';
        @endphp

        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $pageDescription }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:type" content="@yield('og_type', 'website')">
        <meta property="og:url" content="{{ url()->current() }}">
        @stack('meta')

        @php
            $faviconPath = $appFaviconPath ?? null;
            $defaultFaviconPath = (string) config('saas.branding.favicon_path', 'branding/shipsolid-s-favicon.svg');
            $faviconUrl = asset(filled($faviconPath) ? $faviconPath : $defaultFaviconPath);
            $faviconVersion = rawurlencode((string) ($appBrandingVersion ?? config('app.asset_version', '1')));
        @endphp

        <link rel="icon" href="{{ $faviconUrl }}?v={{ $faviconVersion }}" sizes="any">
        <link rel="shortcut icon" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">

        @php
            $brandFonts = config('saas.branding.fonts', []);
            $fontSans = $brandFonts['sans'] ?? 'Instrument Sans';
            $fontDisplay = $brandFonts['display'] ?? 'Instrument Serif';
        @endphp

        <style>
            :root {
                --font-sans: '{{ $fontSans }}';
                --font-display: '{{ $fontDisplay }}';
            }
        </style>

        
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-surface text-ink">
        <div class="min-h-screen bg-hero-glow flex flex-col justify-center items-center px-6 py-10">
            <div class="flex items-center gap-3 text-lg font-semibold">
                <a href="/" class="flex items-center gap-3">
                    <x-application-logo class="h-14 w-14 text-primary" />
                    <span class="font-display text-2xl tracking-tight">{{ $appBrandName ?? config('app.name', 'SaaS Kit') }}</span>
                </a>
                <div class="ml-auto">
                    <x-theme-switcher />
                </div>
            </div>

            <div class="w-full sm:max-w-md mt-8 px-8 py-8 glass-panel overflow-hidden rounded-[24px]">
                {{ $slot }}
            </div>
        </div>
        @livewireScripts
        <x-livewire-toasts />
    </body>
</html>
