{{-- Shared Marketing Navigation --}}
<header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
    <a href="/" class="flex items-center gap-3 group">
        <div class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-surface-highlight/50 border border-ink/10 shadow-inner overflow-hidden transition-all group-hover:scale-110 group-hover:border-primary/50">
            <x-application-logo class="relative z-10 h-6 w-6 text-primary" />
            <div class="absolute inset-0 bg-gradient-to-tr from-primary/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
        </div>
        <span class="font-display text-xl font-bold tracking-tight text-ink">{{ $appBrandName ?? config('app.name', 'SaaS Kit') }}</span>
    </a>

    {{-- Desktop Navigation --}}
    <nav class="hidden md:flex items-center gap-1 rounded-full border border-ink/5 bg-surface-highlight/30 p-1 backdrop-blur-lg">
        <a href="/" class="px-4 py-2 text-sm font-medium text-ink/70 transition hover:text-ink rounded-full hover:bg-surface/50 {{ request()->is('/') ? 'bg-surface/50 text-ink' : '' }}">{{ __('Home') }}</a>
        <a href="/#features" class="px-4 py-2 text-sm font-medium text-ink/70 transition hover:text-ink rounded-full hover:bg-surface/50">{{ __('Features') }}</a>
        <a href="{{ route('pricing') }}" class="px-4 py-2 text-sm font-medium text-ink/70 transition hover:text-ink rounded-full hover:bg-surface/50 {{ request()->routeIs('pricing') ? 'bg-surface/50 text-ink' : '' }}">{{ __('Pricing') }}</a>
        <a href="{{ route('roadmap') }}" class="px-4 py-2 text-sm font-medium text-ink/70 transition hover:text-ink rounded-full hover:bg-surface/50 {{ request()->routeIs('roadmap') ? 'bg-surface/50 text-ink' : '' }}">{{ __('Roadmap') }}</a>
        <a href="{{ route('blog.index') }}" class="px-4 py-2 text-sm font-medium text-ink/70 transition hover:text-ink rounded-full hover:bg-surface/50 {{ request()->routeIs('blog.*') ? 'bg-surface/50 text-ink' : '' }}">{{ __('Blog') }}</a>
    </nav>

    {{-- Right Side Actions --}}
    <div class="flex items-center gap-3">
        <x-theme-switcher />
        <x-locale-switcher class="hidden md:block" />
        
        @auth
            <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex rounded-full border border-ink/15 px-4 py-2 text-sm font-semibold text-ink/80 transition hover:border-ink/30 hover:text-ink">{{ __('Dashboard') }}</a>
            <a href="{{ url('/app') }}" class="rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90">{{ __('Open app') }}</a>
        @else
            <a href="{{ route('login') }}" class="hidden sm:inline-flex text-sm font-medium text-ink/70 hover:text-ink transition">{{ __('Sign In') }}</a>
            <a href="{{ route('register') }}" class="rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90">{{ __('Get Started') }}</a>
        @endauth

        {{-- Mobile Menu Button --}}
        <button 
            type="button" 
            class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-ink/60 hover:text-ink hover:bg-surface/50 transition"
            onclick="document.getElementById('mobile-menu').classList.toggle('hidden')"
        >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>
</header>

{{-- Mobile Navigation Menu --}}
<div id="mobile-menu" class="hidden md:hidden bg-surface/95 backdrop-blur-xl border-b border-ink/10">
    <div class="px-4 py-4 space-y-2">
        <a href="/" class="block px-4 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface/50 {{ request()->is('/') ? 'bg-surface/50 text-ink' : '' }}">{{ __('Home') }}</a>
        <a href="/#features" class="block px-4 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface/50">{{ __('Features') }}</a>
        <a href="{{ route('pricing') }}" class="block px-4 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface/50 {{ request()->routeIs('pricing') ? 'bg-surface/50 text-ink' : '' }}">{{ __('Pricing') }}</a>
        <a href="{{ route('roadmap') }}" class="block px-4 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface/50 {{ request()->routeIs('roadmap') ? 'bg-surface/50 text-ink' : '' }}">{{ __('Roadmap') }}</a>
        <a href="{{ route('blog.index') }}" class="block px-4 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface/50 {{ request()->routeIs('blog.*') ? 'bg-surface/50 text-ink' : '' }}">{{ __('Blog') }}</a>
        
        <div class="pt-2 border-t border-ink/10">
            @auth
                <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface/50">{{ __('Dashboard') }}</a>
                <a href="{{ url('/app') }}" class="block px-4 py-2 text-sm font-medium text-primary hover:text-primary/80 rounded-lg hover:bg-primary/5">{{ __('Open app') }}</a>
            @else
                <a href="{{ route('login') }}" class="block px-4 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface/50">{{ __('Sign In') }}</a>
                <a href="{{ route('register') }}" class="block px-4 py-2 text-sm font-medium text-primary hover:text-primary/80 rounded-lg hover:bg-primary/5">{{ __('Get Started') }}</a>
            @endauth
        </div>
    </div>
</div>
