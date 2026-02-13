<!DOCTYPE html>
@php
    use App\Domain\Settings\Services\BrandingService;
    
    $branding = app(BrandingService::class);
    $forcedTemplate = trim($__env->yieldContent('template'));
    $previewTemplate = (string) request()->query('template_preview', '');
    $availableTemplates = array_keys(config('template.templates', []));
    $activeTemplate = $forcedTemplate !== '' ? $forcedTemplate : $branding->templateForGuest();

    if ($previewTemplate !== '' && in_array($previewTemplate, $availableTemplates, true)) {
        $activeTemplate = $previewTemplate;
    }

    $themeCssOverrides = $branding->themeCssVariableOverrides();
    $bodyClass = trim($__env->yieldContent('body_class'));
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-template="{{ $activeTemplate }}" @if($themeCssOverrides !== '') style="{{ $themeCssOverrides }}" @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-theme-init />

        @php
            $defaultTitle = $appBrandName ?? config('app.name', 'SaaS Kit');
            $pageTitle = trim($__env->yieldContent('title')) ?: $defaultTitle;
            $pageDescription = trim($__env->yieldContent('meta_description')) ?: __('Launch a polished SaaS with billing, auth, and clean architecture.');
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
        <link rel="canonical" href="{{ url()->current() }}">
        <meta name="robots" content="@yield('meta_robots', 'index,follow,max-image-preview:large')">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:type" content="@yield('og_type', 'website')">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:site_name" content="{{ $defaultTitle }}">
        <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta name="twitter:card" content="summary_large_image">
        @php
            $twitterTitle = trim($__env->yieldContent('twitter_title')) ?: $pageTitle;
            $twitterDescription = trim($__env->yieldContent('twitter_description')) ?: $pageDescription;
        @endphp
        <meta name="twitter:title" content="{{ $twitterTitle }}">
        <meta name="twitter:description" content="{{ $twitterDescription }}">
        <meta name="twitter:image" content="{{ $ogImage }}">
        @php
            $alternateLocaleUrls = [];
            $currentRoute = request()->route();
            $routeName = $currentRoute?->getName();
            $routeParameters = $currentRoute?->parameters() ?? [];

            if ($routeName && array_key_exists('locale', $routeParameters)) {
                foreach (array_keys(config('saas.locales.supported', ['en' => 'English'])) as $localeCode) {
                    $alternateLocaleUrls[$localeCode] = route(
                        $routeName,
                        array_merge($routeParameters, ['locale' => $localeCode]),
                        true
                    );
                }
            }

            $xDefaultLocale = (string) config('saas.locales.default', config('app.locale', 'en'));
        @endphp
        @if ($alternateLocaleUrls !== [])
            @foreach ($alternateLocaleUrls as $localeCode => $alternateUrl)
                <link rel="alternate" hreflang="{{ $localeCode }}" href="{{ $alternateUrl }}">
            @endforeach
            <link
                rel="alternate"
                hreflang="x-default"
                href="{{ $alternateLocaleUrls[$xDefaultLocale] ?? reset($alternateLocaleUrls) }}"
            >
        @endif
        @php
            $localizedHomeUrl = route('home', ['locale' => app()->getLocale()]);
            $organizationId = $localizedHomeUrl.'#organization';
            $websiteId = $localizedHomeUrl.'#website';
            $logoPath = filled($appLogoPath ?? null)
                ? (string) $appLogoPath
                : (string) config('saas.branding.logo_path', 'branding/shipsolid-s-mark.svg');
            $logoUrl = asset($logoPath);
        @endphp
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => $organizationId,
                        'name' => $defaultTitle,
                        'url' => $localizedHomeUrl,
                        'logo' => [
                            '@type' => 'ImageObject',
                            'url' => $logoUrl,
                        ],
                    ],
                    [
                        '@type' => 'WebSite',
                        '@id' => $websiteId,
                        'url' => $localizedHomeUrl,
                        'name' => $defaultTitle,
                        'inLanguage' => app()->getLocale(),
                        'publisher' => [
                            '@id' => $organizationId,
                        ],
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
        </script>
        @stack('meta')
        @stack('preloads')
        <link rel="alternate" type="application/rss+xml" title="{{ __('RSS') }}" href="{{ route('rss') }}">
        <link rel="sitemap" type="application/xml" title="{{ __('Sitemap') }}" href="{{ route('sitemap') }}">

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

        
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-surface text-ink {{ $bodyClass }}">
        <div class="min-h-screen bg-hero-glow">
            {{-- Unified Marketing Navigation --}}
            @include('partials.marketing-nav')
            
            {{-- Site-wide Announcements --}}
            @if (config('saas.features.announcements', true))
                <x-announcement-banner />
            @endif

            <main class="mx-auto max-w-6xl px-6 pb-16">
                @yield('content')
            </main>

            {{-- Unified Marketing Footer --}}
            @include('partials.marketing-footer')
        </div>
        @livewireScripts
    </body>
</html>
