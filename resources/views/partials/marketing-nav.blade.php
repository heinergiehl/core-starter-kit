{{-- Shared Marketing Navigation --}}
@php
    $user = auth()->user();
    $hasSubscription = $user?->hasActiveSubscription() ?? false;
    $hasPurchase = $user
        ? app(\App\Domain\Billing\Services\CheckoutService::class)->hasAnyPurchase($user)
        : false;
    $isAdmin = $user?->is_admin ?? false;
@endphp
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
        @if (config('saas.features.roadmap', true))
            <a href="{{ route('roadmap') }}" class="px-4 py-2 text-sm font-medium text-ink/70 transition hover:text-ink rounded-full hover:bg-surface/50 {{ request()->routeIs('roadmap') ? 'bg-surface/50 text-ink' : '' }}">{{ __('Roadmap') }}</a>
        @endif
        @if (config('saas.features.blog', true))
            <a href="{{ route('blog.index') }}" class="px-4 py-2 text-sm font-medium text-ink/70 transition hover:text-ink rounded-full hover:bg-surface/50 {{ request()->routeIs('blog.*') ? 'bg-surface/50 text-ink' : '' }}">{{ __('Blog') }}</a>
        @endif
    </nav>

    {{-- Right Side Actions --}}
    <div class="flex items-center gap-3">
        <x-theme-switcher />
        <x-locale-switcher class="hidden md:block" />
        
        @auth
            <div class="relative hidden sm:block">
                <button 
                    onclick="toggleUserDropdown(event)" 
                    class="inline-flex items-center gap-2 rounded-full border border-ink/15 px-3 py-1.5 text-sm font-medium text-ink/80 transition hover:border-ink/30 hover:text-ink">
                    <span class="h-6 w-6 rounded-full {{ $isAdmin ? 'bg-amber-500/20 text-amber-600' : 'bg-primary/20 text-primary' }} flex items-center justify-center text-xs font-bold">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </span>
                    <span class="max-w-[100px] truncate">{{ $user->name }}</span>
                    <svg class="h-4 w-4 transition-transform" id="user-dropdown-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                
                <div 
                    id="user-dropdown" 
                    style="display: none;"
                    class="absolute right-0 mt-2 w-48 rounded-xl border border-ink/10 bg-surface shadow-xl shadow-ink/5 py-2 z-50">
                    <div class="px-4 py-2 border-b border-ink/10">
                        <p class="text-sm font-medium text-ink truncate">{{ $user->name }}</p>
                        <p class="text-xs text-ink/60 truncate">{{ $user->email }}</p>
                        @if($isAdmin)
                            <span class="inline-block mt-1 px-2 py-0.5 text-xs font-medium text-amber-600 bg-amber-500/10 rounded-full">{{ __('Admin') }}</span>
                        @endif
                    </div>
                    @if($isAdmin)
                        <a href="{{ url('/admin') }}" class="block px-4 py-2 text-sm text-amber-600 hover:text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-500/10 transition">
                            {{ __('Admin Panel') }}
                        </a>
                    @endif
                    @if($hasSubscription)
                        <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-ink/70 hover:text-ink hover:bg-surface-highlight/50 transition">
                            {{ __('Dashboard') }}
                        </a>
                        <a href="{{ url('/app') }}" class="block px-4 py-2 text-sm text-ink/70 hover:text-ink hover:bg-surface-highlight/50 transition">
                            {{ __('Open App') }}
                        </a>
                    @elseif($hasPurchase)
                        <a href="{{ route('billing.index') }}" class="block px-4 py-2 text-sm text-ink/70 hover:text-ink hover:bg-surface-highlight/50 transition">
                            {{ __('Billing') }}
                        </a>
                    @elseif(!$isAdmin)
                        <a href="{{ route('pricing') }}" class="block px-4 py-2 text-sm text-ink/70 hover:text-ink hover:bg-surface-highlight/50 transition">
                            {{ __('Choose a Plan') }}
                        </a>
                    @endif
                    <hr class="my-2 border-ink/10">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 transition">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>
            </div>
            
            <script>
                function toggleUserDropdown(event) {
                    event.stopPropagation();
                    const dropdown = document.getElementById('user-dropdown');
                    const arrow = document.getElementById('user-dropdown-arrow');
                    const isHidden = dropdown.style.display === 'none';
                    
                    dropdown.style.display = isHidden ? 'block' : 'none';
                    arrow.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    const dropdown = document.getElementById('user-dropdown');
                    if (dropdown && dropdown.style.display === 'block') {
                        dropdown.style.display = 'none';
                        document.getElementById('user-dropdown-arrow').style.transform = 'rotate(0deg)';
                    }
                });
            </script>
            @if($isAdmin)
                <a href="{{ url('/admin') }}" class="rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-amber-500/20 transition hover:bg-amber-600">{{ __('Admin Panel') }}</a>
            @elseif($hasSubscription)
                <a href="{{ url('/app') }}" class="rounded-full bg-primary px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90 whitespace-nowrap">{{ __('Open App') }}</a>
            @elseif($hasPurchase)
                <a href="{{ route('billing.index') }}" class="rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90">{{ __('Billing') }}</a>
            @else
                <a href="{{ route('pricing') }}" class="rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90">{{ __('Choose a Plan') }}</a>
            @endif
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
        @if (config('saas.features.roadmap', true))
            <a href="{{ route('roadmap') }}" class="block px-4 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface/50 {{ request()->routeIs('roadmap') ? 'bg-surface/50 text-ink' : '' }}">{{ __('Roadmap') }}</a>
        @endif
        @if (config('saas.features.blog', true))
            <a href="{{ route('blog.index') }}" class="block px-4 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface/50 {{ request()->routeIs('blog.*') ? 'bg-surface/50 text-ink' : '' }}">{{ __('Blog') }}</a>
        @endif
        
        <div class="pt-2 border-t border-ink/10">
            @auth
                <div class="px-4 py-2 flex items-center gap-2 mb-2">
                    <span class="h-8 w-8 rounded-full {{ $isAdmin ? 'bg-amber-500/20 text-amber-600' : 'bg-primary/20 text-primary' }} flex items-center justify-center text-sm font-bold">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-ink truncate">{{ $user->name }}</p>
                        <p class="text-xs text-ink/60 truncate">{{ $user->email }}</p>
                        @if($isAdmin)
                            <span class="inline-block mt-0.5 px-2 py-0.5 text-xs font-medium text-amber-600 bg-amber-500/10 rounded-full">{{ __('Admin') }}</span>
                        @endif
                    </div>
                </div>
                @if($isAdmin)
                    <a href="{{ url('/admin') }}" class="block px-4 py-2 text-sm font-medium text-amber-600 hover:text-amber-700 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-500/10">{{ __('Admin Panel') }}</a>
                @endif
                @if($hasSubscription)
                    <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface/50">{{ __('Dashboard') }}</a>
                    <a href="{{ url('/app') }}" class="block px-4 py-2 text-sm font-medium text-primary hover:text-primary/80 rounded-lg hover:bg-primary/5">{{ __('Open app') }}</a>
                @elseif($hasPurchase)
                    <a href="{{ route('billing.index') }}" class="block px-4 py-2 text-sm font-medium text-primary hover:text-primary/80 rounded-lg hover:bg-primary/5">{{ __('Billing') }}</a>
                @elseif(!$isAdmin)
                    <a href="{{ route('pricing') }}" class="block px-4 py-2 text-sm font-medium text-primary hover:text-primary/80 rounded-lg hover:bg-primary/5">{{ __('Choose a Plan') }}</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="mt-2 pt-2 border-t border-ink/10">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-sm font-medium text-red-500 hover:text-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10">
                        {{ __('Log Out') }}
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="block px-4 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface/50">{{ __('Sign In') }}</a>
                <a href="{{ route('register') }}" class="block px-4 py-2 text-sm font-medium text-primary hover:text-primary/80 rounded-lg hover:bg-primary/5">{{ __('Get Started') }}</a>
            @endauth
        </div>
    </div>
</div>
