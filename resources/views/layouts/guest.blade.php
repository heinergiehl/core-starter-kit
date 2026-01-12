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
            $pageDescription = trim($__env->yieldContent('meta_description')) ?: 'Launch a polished SaaS with teams, billing, and clean architecture.';
        @endphp

        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $pageDescription }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:type" content="@yield('og_type', 'website')">
        <meta property="og:url" content="{{ url()->current() }}">
        @stack('meta')

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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-surface text-ink">
        <div class="min-h-screen bg-hero-glow flex flex-col justify-center items-center px-6 py-10">
            <div class="flex items-center gap-3 text-lg font-semibold">
                <a href="/" class="flex items-center gap-3">
                    <x-application-logo class="h-10 w-10 text-primary" />
                    <span class="font-display text-2xl tracking-tight">{{ $appBrandName ?? config('app.name', 'SaaS Kit') }}</span>
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-8 px-8 py-8 glass-panel overflow-hidden rounded-[24px]">
                {{ $slot }}
            </div>
        </div>
        @livewireScripts
    </body>
</html>
