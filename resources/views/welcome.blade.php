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

        @php
            $pageTitle = $appBrandName ?? config('app.name', 'SaaS Kit');
            $pageDescription = __('Ship a polished SaaS with teams, billing, and a domain-first architecture.');
        @endphp

        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $pageDescription }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-surface text-ink selection:bg-primary/30 selection:text-white">
        <!-- Hero Background -->
        <div class="fixed inset-0 z-0 min-h-screen pointer-events-none bg-hero-glow"></div>
        <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-primary/20 blur-[120px] rounded-full pointer-events-none"></div>

        <div class="relative z-10 transition-colors duration-500">
            {{-- Unified Marketing Navigation --}}
            @include('partials.marketing-nav')

            {{-- Site-wide Announcements --}}
            <x-announcement-banner />

            <main>
                <!-- Hero Section -->
                <section class="relative px-6 pt-20 pb-32 text-center">
                    <div class="flex flex-col items-center animate-fade-up">
                        <div class="inline-flex items-center gap-2 px-3 py-1 mb-8 text-xs font-semibold border rounded-full border-primary/20 bg-primary/10 text-primary backdrop-blur-md">
                            <span class="relative flex w-2 h-2">
                              <span class="absolute inline-flex w-full h-full rounded-full opacity-75 animate-ping bg-primary"></span>
                              <span class="relative inline-flex w-2 h-2 rounded-full bg-primary"></span>
                            </span>
                            {{ __('v2.0 is now live') }}
                        </div>
                        
                        <h1 class="mx-auto max-w-4xl font-display text-5xl font-bold leading-[1.1] tracking-tight text-ink sm:text-7xl">
                            {{ __('app.hero_title') }} <br>
                            <span class="text-gradient hover:scale-[1.02] transition-transform duration-300 inline-block">{{ __('app.hero_title_highlight') }}</span>
                        </h1>
                        
                        <p class="max-w-2xl mx-auto mt-6 text-lg leading-relaxed text-ink/60">
                            {{ __('app.hero_description') }}
                            <span class="font-medium text-ink">{{ __('app.hero_tagline') }}</span>
                        </p>

                        <div class="flex flex-col items-center gap-4 mt-10 sm:flex-row">
                            @auth
                                 <a href="{{ url('/app') }}" class="w-full px-10 text-lg btn-primary sm:w-auto">{{ __('Launch Console') }}</a>
                            @else
                                <a href="{{ route('register') }}" class="w-full px-10 text-lg btn-primary sm:w-auto">{{ __('Start Building Free') }}</a>
                                <a href="#features" class="w-full px-10 text-lg btn-secondary sm:w-auto">{{ __('View Demo') }}</a>
                            @endauth
                        </div>

                        <!-- Social Proof / Trusted By -->
                        <div class="w-full max-w-5xl pt-8 mx-auto mt-16 border-t border-ink/5">
                            <p class="mb-6 text-xs font-medium tracking-widest uppercase text-ink/30">{{ __('app.built_with') }}</p>
                            <div class="flex flex-wrap justify-center transition-all duration-500 opacity-50 gap-x-12 gap-y-8 grayscale hover:grayscale-0 hover:opacity-100">
                                <span class="flex items-center gap-2 text-xl font-bold text-ink"><div class="w-6 h-6 rounded-md bg-ink/10"></div> Laravel 11</span>
                                <span class="flex items-center gap-2 text-xl font-bold text-ink"><div class="w-6 h-6 rounded-md bg-ink/10"></div> Filament 3</span>
                                <span class="flex items-center gap-2 text-xl font-bold text-ink"><div class="w-6 h-6 rounded-md bg-ink/10"></div> Livewire</span>
                                <span class="flex items-center gap-2 text-xl font-bold text-ink"><div class="w-6 h-6 rounded-md bg-ink/10"></div> Tailwind</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Bento Grid Features -->
                <section id="features" class="relative px-6 py-24">
                    <div class="mx-auto max-w-7xl">
                        <div class="max-w-3xl mx-auto mb-16 md:text-center">
                            <h2 class="text-4xl font-bold font-display text-ink">{{ __('Everything you need to ship.') }}</h2>
                            <p class="mt-4 text-ink/60">{{ __('We handled the boring stuff. You focus on the unique value.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 auto-rows-[300px]">
                            <!-- Feature 1: Large Span -->
                            <div class="glass-panel rounded-[32px] md:col-span-2 p-10 relative overflow-hidden group">
                                <div class="relative z-10">
                                    <h3 class="mb-2 text-2xl font-bold text-ink">{{ __('Multi-Tenant Architecture') }}</h3>
                                    <p class="max-w-md text-ink/60">{{ __('Built for scale. Teams, members, and roles are first-class citizens. Data is strictly scoped.') }}</p>
                                </div>
                                <div class="absolute right-0 bottom-0 w-2/3 h-2/3 bg-gradient-to-tl from-primary/20 to-transparent rounded-tl-[32px] border-t border-l border-ink/5 transition-transform group-hover:scale-105 duration-500">
                                    <!-- Pseudo UI Mockup -->
                                    <div class="absolute p-4 space-y-3 border inset-4 bg-surface/40 backdrop-blur rounded-2xl border-ink/10">
                                        <div class="w-1/3 h-4 rounded-full bg-ink/10"></div>
                                        <div class="w-3/4 h-4 rounded-full bg-ink/10"></div>
                                        <div class="w-1/2 h-4 rounded-full bg-ink/10"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Feature 2: Tall -->
                            <div class="glass-panel rounded-[32px] md:row-span-2 p-10 relative overflow-hidden group">
                                <div class="absolute inset-0 transition-opacity opacity-0 bg-gradient-to-b from-transparent via-transparent to-secondary/10 group-hover:opacity-100"></div>
                                <div class="relative z-10 flex flex-col h-full">
                                    <div class="flex items-center justify-center w-12 h-12 mb-6 border rounded-xl bg-secondary/20 text-secondary border-secondary/20">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </div>
                                    <h3 class="mb-2 text-2xl font-bold text-ink">{{ __('Universal Billing') }}</h3>
                                    <p class="mb-8 text-ink/60">{{ __('Stripe, Paddle, Lemon Squeezy. Switch providers with one config change. Webhooks handled.') }}</p>
                                    
                                    <!-- Visual -->
                                    <div class="relative flex items-center justify-center w-full h-40 mt-auto overflow-hidden border bg-ink/5 rounded-xl border-ink/10">
                                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-secondary/20 to-transparent opacity-50"></div>
                                        <div class="px-3 py-1 font-mono text-xs border rounded-full text-secondary bg-surface/50 border-secondary/30 backdrop-blur">
                                            $subscription->swap()
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Feature 3: Standard -->
                            <div class="glass-panel rounded-[32px] p-10 relative overflow-hidden group hover:border-accent/30 transition-colors">
                                <h3 class="mb-2 text-2xl font-bold text-ink">{{ __('Filament Panels') }}</h3>
                                <p class="text-ink/60">{{ __('Admin & App panels pre-configured. Use the TALL stack power.') }}</p>
                                <div class="absolute w-24 h-24 transition-colors rounded-full -right-4 -bottom-4 bg-accent/20 blur-2xl group-hover:bg-accent/30"></div>
                            </div>

                            <!-- Feature 4: Standard -->
                            <div class="glass-panel rounded-[32px] p-10 relative overflow-hidden group hover:border-primary/30 transition-colors">
                                <h3 class="mb-2 text-2xl font-bold text-ink">{{ __('Event Driven') }}</h3>
                                <p class="text-ink/60">{{ __('Decoupled architecture. Listen to `SubscriptionCreated` and react.') }}</p>
                                <div class="absolute w-24 h-24 transition-colors rounded-full -left-4 -top-4 bg-primary/20 blur-2xl group-hover:bg-primary/30"></div>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- CTA -->
                <section class="px-6 py-24 text-center">
                    <div class="relative mx-auto max-w-4xl p-12 rounded-[40px] border border-ink/10 bg-gradient-to-b from-ink/5 to-transparent overflow-hidden">
                        <div class="absolute inset-0 bg-primary/5"></div>
                        <div class="relative z-10">
                            <h2 class="mb-6 text-4xl font-bold font-display md:text-5xl text-ink">{{ __('Stop building the foundation.') }}</h2>
                            <p class="max-w-2xl mx-auto mb-10 text-xl text-ink/60">{{ __('You have a product to launch. Let us handle the SaaS plumbing.') }}</p>
                            <a href="{{ route('register') }}" class="px-12 py-4 text-lg shadow-xl btn-primary shadow-primary/20 hover:shadow-primary/40">{{ __('Get Started Now') }}</a>
                        </div>
                    </div>
                </section>
            </main>

            {{-- Unified Marketing Footer --}}
            @include('partials.marketing-footer')
        </div>
    </body>
</html>
