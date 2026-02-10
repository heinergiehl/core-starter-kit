<!DOCTYPE html>
@php
    use App\Domain\Settings\Services\BrandingService;
    
    $branding = app(BrandingService::class);
    $activeTemplate = $branding->templateForGuest();
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-template="{{ $activeTemplate }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-theme-init />

        <title>{{ $appBrandName ?? config('app.name', 'SaaS Kit') }}</title>

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
        {{-- Impersonation Banner (shows when admin is impersonating a user) --}}
        <x-impersonation-banner />
        
        <div class="min-h-screen bg-surface">
            @include('layouts.navigation')
            
            {{-- Site-wide Announcements --}}
            @if (config('saas.features.announcements', true))
                <x-announcement-banner />
            @endif

            @isset($header)
                <header class="sticky top-[4rem] z-40 border-b border-ink/5 bg-surface/80 backdrop-blur-md">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
        @livewireScripts
        <x-livewire-toasts />
    </body>
</html>
