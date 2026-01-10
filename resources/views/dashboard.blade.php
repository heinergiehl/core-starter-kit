<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-medium text-2xl text-ink leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <!-- Background Glow -->
            <div class="fixed top-20 right-0 w-[500px] h-[500px] bg-primary/20 blur-[100px] rounded-full pointer-events-none -z-10"></div>

            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="glass-panel p-8 rounded-[32px] lg:col-span-2 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-surface/5 to-transparent pointer-events-none"></div>

                    <div class="flex items-center justify-between relative z-10">
                        <div>
                            <p class="text-sm font-medium text-ink/50 uppercase tracking-widest">{{ __('Current Workspace') }}</p>
                            <h3 class="mt-2 text-3xl font-display font-bold text-ink">{{ $team?->name ?? __('No team selected') }}</h3>
                            @if ($team)
                                <div class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary border border-primary/20">
                                    {{ $team->slug }}
                                </div>
                            @endif
                        </div>
                        <a href="{{ route('teams.select') }}" class="btn-secondary text-sm !py-2 !px-4 hover:bg-surface/10">{{ __('Switch') }}</a>
                    </div>

                    @if ($team)
                        @php
                            $maxSeats = $entitlements?->get('max_seats');
                            $subscriptionStatus = $team->subscription?->status;
                            $statusColor = match($subscriptionStatus) {
                                'active' => 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20',
                                'trialing' => 'text-blue-500 bg-blue-500/10 border-blue-500/20',
                                'past_due' => 'text-amber-500 bg-amber-500/10 border-amber-500/20',
                                'canceled' => 'text-red-500 bg-red-500/10 border-red-500/20',
                                default => 'text-ink/50 bg-surface/5 border-ink/10'
                            };
                        @endphp

                        <div class="mt-10 grid gap-6 sm:grid-cols-2 relative z-10">
                            <!-- Helper Card: Seats -->
                            <div class="rounded-3xl border border-ink/5 bg-surface/30 p-6 backdrop-blur-sm group hover:border-ink/10 transition-colors">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-ink/40">{{ __('Usage') }}</p>
                                        <p class="mt-3 text-4xl font-display text-ink">
                                            {{ $entitlements?->get('seats_in_use', 0) }}
                                            <span class="text-lg text-ink/30 font-sans font-normal">/ {{ is_null($maxSeats) ? '∞' : $maxSeats }}</span>
                                        </p>
                                    </div>
                                    <div class="h-10 w-10 rounded-full bg-surface/50 flex items-center justify-center text-ink/50 group-hover:text-ink group-hover:scale-110 transition-all shadow-sm">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                    </div>
                                </div>
                                <div class="mt-4 h-1.5 w-full bg-surface/10 rounded-full overflow-hidden">
                                    <div class="h-full bg-primary rounded-full" style="width: {{ $maxSeats ? min(($entitlements?->get('seats_in_use', 0) / $maxSeats) * 100, 100) : 0 }}%"></div>
                                </div>
                            </div>

                            <!-- Helper Card: Plan -->
                            <div class="rounded-3xl border border-ink/5 bg-surface/30 p-6 backdrop-blur-sm group hover:border-ink/10 transition-colors">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-ink/40">{{ __('Plan Status') }}</p>
                                        <div class="mt-3 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border {{ $statusColor }}">
                                            {{ ucfirst($subscriptionStatus ?? 'Free') }}
                                        </div>
                                    </div>
                                    <div class="h-10 w-10 rounded-full bg-surface/50 flex items-center justify-center text-ink/50 group-hover:text-ink group-hover:scale-110 transition-all shadow-sm">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                    </div>
                                </div>
                                <div class="mt-4 flex items-center gap-2 text-sm text-ink/50">
                                    @if($team->subscription?->onTrial())
                                        {{ __('Trial ends in :days days', ['days' => $team->subscription->trial_ends_at->diffInDays(now())]) }}
                                    @else
                                        <a href="{{ route('billing.index') }}" class="hover:text-primary transition-colors">{{ __('Manage Billing') }} &rarr;</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-8 rounded-2xl border border-dashed border-ink/20 bg-surface/5 p-8 text-center">
                            <h3 class="text-lg font-medium text-ink">{{ __('Welcome to your dashboard') }}</h3>
                            <p class="mt-2 text-ink/50">{{ __('Get started by creating a team to unlock all features.') }}</p>
                            <a href="{{ route('teams.create') }}" class="btn-primary mt-6 inline-block">{{ __('Create Team') }}</a>
                        </div>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <div class="glass-panel p-6 rounded-[32px]">
                        <h3 class="text-lg font-bold text-ink mb-4">{{ __('Quick Access') }}</h3>
                        <div class="space-y-2">
                            <a href="{{ url('/app') }}" class="group flex items-center gap-3 p-3 rounded-2xl hover:bg-surface/5 transition-colors border border-transparent hover:border-ink/5">
                                <div class="h-10 w-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                                </div>
                                <div>
                                    <p class="font-medium text-ink">{{ __('App Panel') }}</p>
                                    <p class="text-xs text-ink/50">{{ __('Launch your app') }}</p>
                                </div>
                            </a>
                            
                            @if(auth()->user()?->is_admin)
                            <a href="{{ url('/admin') }}" class="group flex items-center gap-3 p-3 rounded-2xl hover:bg-surface/5 transition-colors border border-transparent hover:border-ink/5">
                                <div class="h-10 w-10 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-500 group-hover:scale-110 transition-transform">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </div>
                                <div>
                                    <p class="font-medium text-ink">{{ __('Admin Console') }}</p>
                                    <p class="text-xs text-ink/50">{{ __('Manage the platform') }}</p>
                                </div>
                            </a>
                            @endif

                            <a href="{{ route('blog.index') }}" class="group flex items-center gap-3 p-3 rounded-2xl hover:bg-surface/5 transition-colors border border-transparent hover:border-ink/5">
                                <div class="h-10 w-10 rounded-xl bg-pink-500/10 flex items-center justify-center text-pink-500 group-hover:scale-110 transition-transform">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                                </div>
                                <div>
                                    <p class="font-medium text-ink">{{ __('Documentation') }}</p>
                                    <p class="text-xs text-ink/50">{{ __('Guides & Updates') }}</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
