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
        <div class="fixed inset-0 min-h-screen bg-hero-glow pointer-events-none z-0"></div>
        <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-primary/20 blur-[120px] rounded-full pointer-events-none"></div>

        <div class="relative z-10 transition-colors duration-500">
            {{-- Unified Marketing Navigation --}}
            @include('partials.marketing-nav')

            {{-- Site-wide Announcements --}}
            <x-announcement-banner />

            <main>
                <!-- Hero Section -->
                <section class="relative pt-20 pb-32 text-center px-6">
                    <div class="animate-fade-up flex flex-col items-center">
                        <div class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary mb-8 backdrop-blur-md">
                            <span class="relative flex h-2 w-2">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                            </span>
                            {{ __('v2.0 is now live') }}
                        </div>
                        
                        <h1 class="mx-auto max-w-4xl font-display text-5xl font-bold leading-[1.1] tracking-tight text-ink sm:text-7xl">
                            {{ __('app.hero_title') }} <br>
                            <span class="text-gradient hover:scale-[1.02] transition-transform duration-300 inline-block">{{ __('app.hero_title_highlight') }}</span>
                        </h1>
                        
                        <p class="mx-auto mt-6 max-w-2xl text-lg text-ink/60 leading-relaxed">
                            {{ __('app.hero_description') }}
                            <span class="text-ink font-medium">{{ __('app.hero_tagline') }}</span>
                        </p>

                        <div class="mt-10 flex flex-col sm:flex-row items-center gap-4">
                            @auth
                                 <a href="{{ url('/app') }}" class="btn-primary w-full sm:w-auto text-lg px-10">{{ __('Launch Console') }}</a>
                            @else
                                <a href="{{ route('register') }}" class="btn-primary w-full sm:w-auto text-lg px-10">{{ __('Start Building Free') }}</a>
                                <a href="#features" class="btn-secondary w-full sm:w-auto text-lg px-10">{{ __('View Demo') }}</a>
                            @endauth
                        </div>

                        <!-- Social Proof / Trusted By -->
                        <div class="mt-16 pt-8 border-t border-ink/5 w-full max-w-5xl mx-auto">
                            <p class="text-xs font-medium uppercase tracking-widest text-ink/30 mb-6">{{ __('app.built_with') }}</p>
                            <div class="flex flex-wrap justify-center gap-x-12 gap-y-8 opacity-50 grayscale transition-all duration-500 hover:grayscale-0 hover:opacity-100">
                                <span class="text-xl font-bold flex items-center gap-2 text-ink"><div class="w-6 h-6 bg-ink/10 rounded-md"></div> Laravel 11</span>
                                <span class="text-xl font-bold flex items-center gap-2 text-ink"><div class="w-6 h-6 bg-ink/10 rounded-md"></div> Filament 3</span>
                                <span class="text-xl font-bold flex items-center gap-2 text-ink"><div class="w-6 h-6 bg-ink/10 rounded-md"></div> Livewire</span>
                                <span class="text-xl font-bold flex items-center gap-2 text-ink"><div class="w-6 h-6 bg-ink/10 rounded-md"></div> Tailwind</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Bento Grid Features -->
                <section id="features" class="py-24 px-6 relative">
                    <div class="mx-auto max-w-7xl">
                        <div class="mb-16 md:text-center max-w-3xl mx-auto">
                            <h2 class="font-display text-4xl font-bold text-ink">{{ __('Everything you need to ship.') }}</h2>
                            <p class="mt-4 text-ink/60">{{ __('We handled the boring stuff. You focus on the unique value.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 auto-rows-[300px]">
                            <!-- Feature 1: Large Span -->
                            <div class="glass-panel rounded-[32px] md:col-span-2 p-10 relative overflow-hidden group">
                                <div class="relative z-10">
                                    <h3 class="text-2xl font-bold text-ink mb-2">{{ __('Multi-Tenant Architecture') }}</h3>
                                    <p class="text-ink/60 max-w-md">{{ __('Built for scale. Teams, members, and roles are first-class citizens. Data is strictly scoped.') }}</p>
                                </div>
                                <div class="absolute right-0 bottom-0 w-2/3 h-2/3 bg-gradient-to-tl from-primary/20 to-transparent rounded-tl-[32px] border-t border-l border-ink/5 transition-transform group-hover:scale-105 duration-500">
                                    <!-- Pseudo UI Mockup -->
                                    <div class="absolute inset-4 bg-surface/40 backdrop-blur rounded-2xl border border-ink/10 p-4 space-y-3">
                                        <div class="h-4 w-1/3 bg-ink/10 rounded-full"></div>
                                        <div class="h-4 w-3/4 bg-ink/10 rounded-full"></div>
                                        <div class="h-4 w-1/2 bg-ink/10 rounded-full"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Feature 2: Tall -->
                            <div class="glass-panel rounded-[32px] md:row-span-2 p-10 relative overflow-hidden group">
                                <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-secondary/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                <div class="relative z-10 h-full flex flex-col">
                                    <div class="w-12 h-12 rounded-xl bg-secondary/20 flex items-center justify-center mb-6 text-secondary border border-secondary/20">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </div>
                                    <h3 class="text-2xl font-bold text-ink mb-2">{{ __('Universal Billing') }}</h3>
                                    <p class="text-ink/60 mb-8">{{ __('Stripe, Paddle, Lemon Squeezy. Switch providers with one config change. Webhooks handled.') }}</p>
                                    
                                    <!-- Visual -->
                                    <div class="mt-auto relative w-full h-40 bg-ink/5 rounded-xl border border-ink/10 flex items-center justify-center overflow-hidden">
                                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-secondary/20 to-transparent opacity-50"></div>
                                        <div class="font-mono text-xs text-secondary bg-surface/50 px-3 py-1 rounded-full border border-secondary/30 backdrop-blur">
                                            $subscription->swap()
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Feature 3: Standard -->
                            <div class="glass-panel rounded-[32px] p-10 relative overflow-hidden group hover:border-accent/30 transition-colors">
                                <h3 class="text-2xl font-bold text-ink mb-2">{{ __('Filament Panels') }}</h3>
                                <p class="text-ink/60">{{ __('Admin & App panels pre-configured. Use the TALL stack power.') }}</p>
                                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-accent/20 blur-2xl rounded-full group-hover:bg-accent/30 transition-colors"></div>
                            </div>

                            <!-- Feature 4: Standard -->
                            <div class="glass-panel rounded-[32px] p-10 relative overflow-hidden group hover:border-primary/30 transition-colors">
                                <h3 class="text-2xl font-bold text-ink mb-2">{{ __('Event Driven') }}</h3>
                                <p class="text-ink/60">{{ __('Decoupled architecture. Listen to `SubscriptionCreated` and react.') }}</p>
                                <div class="absolute -left-4 -top-4 w-24 h-24 bg-primary/20 blur-2xl rounded-full group-hover:bg-primary/30 transition-colors"></div>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- CTA -->
                <section class="py-24 px-6 text-center">
                    <div class="relative mx-auto max-w-4xl p-12 rounded-[40px] border border-ink/10 bg-gradient-to-b from-ink/5 to-transparent overflow-hidden">
                        <div class="absolute inset-0 bg-primary/5"></div>
                        <div class="relative z-10">
                            <h2 class="font-display text-4xl md:text-5xl font-bold text-ink mb-6">{{ __('Stop building the foundation.') }}</h2>
                            <p class="text-xl text-ink/60 mb-10 max-w-2xl mx-auto">{{ __('You have a product to launch. Let us handle the SaaS plumbing.') }}</p>
                            <a href="{{ route('register') }}" class="btn-primary text-lg px-12 py-4 shadow-xl shadow-primary/20 hover:shadow-primary/40">{{ __('Get Started Now') }}</a>
                        </div>
                    </div>
                </section>
            </main>

            {{-- Unified Marketing Footer --}}
            @include('partials.marketing-footer')
        </div>
    </body>
</html>
