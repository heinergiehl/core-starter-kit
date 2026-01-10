<nav x-data="{ open: false }" class="glass-panel border-b border-ink/5 sticky top-0 z-50">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 group">
                        <x-application-logo class="block h-8 w-auto fill-current text-primary group-hover:scale-110 transition-transform" />
                        <span class="hidden text-lg font-display font-medium text-ink sm:block">{{ $appBrandName ?? config('app.name', 'SaaS Kit') }}</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                    <x-nav-link :href="route('home')" :active="request()->routeIs('home')" class="text-ink/60 hover:text-ink">
                        {{ __('Home') }}
                    </x-nav-link>
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" class="text-ink/60 hover:text-ink">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    
                    <x-nav-link :href="url('/app')" :active="request()->is('app*')" class="text-ink/60 hover:text-ink font-medium text-primary">
                        {{ __('Open App') }}
                    </x-nav-link>
                    
                    @if(auth()->user()?->currentTeam?->hasActiveSubscription())
                         <x-nav-link :href="route('billing.index')" :active="request()->routeIs('billing*')" class="text-ink/60 hover:text-ink">
                            {{ __('Billing') }}
                        </x-nav-link>
                    @endif

                    <x-nav-link :href="route('roadmap')" :active="request()->routeIs('roadmap')" class="text-ink/60 hover:text-ink">
                        {{ __('Roadmap') }}
                    </x-nav-link>
                    
                     <x-nav-link :href="route('blog.index')" :active="request()->routeIs('blog.*')" class="text-ink/60 hover:text-ink">
                        {{ __('Blog') }}
                    </x-nav-link>

                    @if(auth()->user()?->is_admin)
                        <x-nav-link :href="url('/admin')" :active="request()->is('admin*')" class="text-purple-500 hover:text-purple-400 font-medium">
                            {{ __('Admin') }}
                        </x-nav-link>
                    @endif
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-3">
                <x-theme-switcher />
                <x-locale-switcher class="hidden lg:block text-ink/60 hover:text-ink" />
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-ink/10 text-sm leading-4 font-medium rounded-full text-ink/80 bg-surface/5 hover:text-ink hover:bg-surface/10 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <!-- Wrapper for dropdown content (assuming x-dropdown includes one, if not we style the links directly) -->
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        @if(auth()->user()?->currentTeam?->hasActiveSubscription())
                            <x-dropdown-link :href="route('billing.index')">
                                {{ __('Billing') }}
                            </x-dropdown-link>
                        @endif

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-ink/60 hover:text-ink hover:bg-surface/10 focus:outline-none focus:bg-surface/10 focus:text-ink transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-surface/95 backdrop-blur-xl border-b border-ink/10">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('home')" :active="request()->routeIs('home')" class="text-ink/70 hover:text-ink hover:bg-surface/5">
                {{ __('Home') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" class="text-ink/70 hover:text-ink hover:bg-surface/5">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="url('/app')" :active="request()->is('app*')" class="text-ink/70 hover:text-ink hover:bg-surface/5 text-primary">
                {{ __('Open App') }}
            </x-responsive-nav-link>

            @if(auth()->user()?->currentTeam?->hasActiveSubscription())
                <x-responsive-nav-link :href="route('billing.index')" :active="request()->routeIs('billing*')" class="text-ink/70 hover:text-ink hover:bg-surface/5">
                    {{ __('Billing') }}
                </x-responsive-nav-link>
            @endif

            <x-responsive-nav-link :href="route('roadmap')" :active="request()->routeIs('roadmap')" class="text-ink/70 hover:text-ink hover:bg-surface/5">
                {{ __('Roadmap') }}
            </x-responsive-nav-link>

             <x-responsive-nav-link :href="route('blog.index')" :active="request()->routeIs('blog.*')" class="text-ink/70 hover:text-ink hover:bg-surface/5">
                {{ __('Blog') }}
            </x-responsive-nav-link>
            @if(auth()->user()?->is_admin)
                <x-responsive-nav-link :href="url('/admin')" :active="request()->is('admin*')" class="text-purple-500 hover:text-purple-400 hover:bg-purple-500/10">
                    {{ __('Admin') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-ink/10">
            <div class="px-4">
                <div class="font-medium text-base text-ink">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-ink/60">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <div class="px-4">
                    <x-locale-switcher class="text-ink/60" />
                </div>
                <x-responsive-nav-link :href="route('profile.edit')" class="text-ink/70 hover:text-ink hover:bg-surface/5">
                    {{ __('Profile') }}
                </x-responsive-nav-link>



                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();"
                            class="text-ink/70 hover:text-ink hover:bg-surface/5">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
