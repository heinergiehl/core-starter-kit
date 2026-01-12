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

        <title>{{ $appBrandName ?? config('app.name', 'SaaS Kit') }}</title>

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
        {{-- Impersonation Banner (shows when admin is impersonating a user) --}}
        <x-impersonation-banner />
        
        <div class="min-h-screen bg-surface">
            @include('layouts.navigation')
            
            {{-- Site-wide Announcements --}}
            <x-announcement-banner />

            @php
                $maxSeats = $entitlements?->get('max_seats');
            @endphp

            @if(isset($entitlements) && !is_null($maxSeats) && $maxSeats > 0 && !$entitlements?->get('has_available_seats', true))
                <div class="border-b border-amber-200 bg-amber-50">
                    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 text-sm text-amber-900 sm:px-6 lg:px-8">
                        <span>{{ __('Seat limit reached. Upgrade your plan to invite more members.') }}</span>
                        <a href="{{ url('/app') }}" class="font-semibold text-amber-900 underline underline-offset-4 hover:text-amber-700">{{ __('Manage billing') }}</a>
                    </div>
                </div>
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
    </body>
</html>
