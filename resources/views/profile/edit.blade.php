<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-medium text-xl text-ink leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    {{-- Livewire Toast Notifications --}}


    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-600">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-600">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->has('social'))
                <div class="rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-600">
                    {{ $errors->first('social') }}
                </div>
            @endif

            @php
                $githubConnected = (bool) $githubAccount;
                $grantStatus = $repoAccessGrant?->status?->value;
                $statusClasses = [
                    'queued' => 'text-amber-700 bg-amber-500/10 border-amber-500/20',
                    'processing' => 'text-blue-700 bg-blue-500/10 border-blue-500/20',
                    'awaiting_github_link' => 'text-ink/70 bg-surface/30 border-ink/10',
                    'invited' => 'text-emerald-700 bg-emerald-500/10 border-emerald-500/20',
                    'granted' => 'text-emerald-700 bg-emerald-500/10 border-emerald-500/20',
                    'failed' => 'text-rose-700 bg-rose-500/10 border-rose-500/20',
                ];
                $statusClass = $statusClasses[$grantStatus] ?? 'text-ink/70 bg-surface/30 border-ink/10';
                $connectRedirect = route('social.redirect', [
                    'provider' => 'github',
                    'connect' => 1,
                    'intended' => route('profile.edit', absolute: false),
                ]);
                $githubUsername = trim((string) ($repoAccessGrant?->github_username ?? $githubAccount?->provider_name ?? ''));
            @endphp

            <div id="repo-access" class="glass-panel p-4 sm:p-8 rounded-[32px]">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-wider text-ink/40">{{ __('GitHub Access') }}</p>
                    <h3 class="mt-2 text-2xl font-display text-ink">{{ __('Private Repository Access') }}</h3>
                    <p class="mt-2 text-sm text-ink/60">
                        {{ __('Connect your GitHub account to receive private repository access automatically after successful purchase.') }}
                    </p>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-ink/10 bg-surface/20 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-ink/50">{{ __('Connected GitHub') }}</p>
                            @if ($githubConnected)
                                <p class="mt-2 text-sm font-semibold text-ink">
                                    {{ $githubUsername !== '' ? '@'.$githubUsername : __('Connected') }}
                                </p>
                                <p class="mt-1 text-xs text-ink/50">{{ __('GitHub ID: :id', ['id' => $githubAccount->provider_id]) }}</p>
                            @else
                                <p class="mt-2 text-sm font-semibold text-ink/70">{{ __('Not connected') }}</p>
                            @endif
                        </div>

                        <div class="rounded-2xl border border-ink/10 bg-surface/20 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-ink/50">{{ __('Repo Access Status') }}</p>
                            <div class="mt-2">
                                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ $repoAccessGrant?->status?->getLabel() ?? __('Not requested') }}
                                </span>
                            </div>
                            @if ($repoAccessGrant?->last_error)
                                <p class="mt-2 text-xs text-rose-600">{{ $repoAccessGrant->last_error }}</p>
                            @endif
                            <p class="mt-2 text-xs text-ink/50">{{ __('Repository: :repo', ['repo' => $repoAccessRepository]) }}</p>
                        </div>
                    </div>

                    @if (! $repoAccessEnabled)
                        <div class="mt-5 rounded-xl border border-ink/10 bg-surface/20 px-4 py-3 text-sm text-ink/70">
                            {{ __('This module is currently disabled by the site owner.') }}
                        </div>
                    @elseif (! $canRequestRepoAccess)
                        <div class="mt-5 rounded-xl border border-ink/10 bg-surface/20 px-4 py-3 text-sm text-ink/70">
                            {{ __('Repository access is available after a successful purchase.') }}
                        </div>
                    @endif

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        @if ($githubConnected)
                            <a href="{{ $connectRedirect }}" class="btn-secondary">
                                {{ __('Switch GitHub Account') }}
                            </a>

                            <form method="POST" action="{{ route('repo-access.github.disconnect') }}" data-submit-lock>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-secondary">
                                    {{ __('Disconnect GitHub') }}
                                </button>
                            </form>
                        @else
                            <a href="{{ $connectRedirect }}" class="btn-primary">
                                {{ __('Connect GitHub') }}
                            </a>
                        @endif

                        @if ($repoAccessEnabled && $canRequestRepoAccess)
                            <form method="POST" action="{{ route('repo-access.sync') }}" data-submit-lock>
                                @csrf
                                <button type="submit" class="btn-primary">
                                    {{ __('Sync Repo Access Now') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="glass-panel p-4 sm:p-8 rounded-[32px]">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information />
                </div>
            </div>

            <div class="glass-panel p-4 sm:p-8 rounded-[32px]">
                <div class="max-w-xl">
                    <livewire:profile.update-password />
                </div>
            </div>

            <div class="glass-panel p-4 sm:p-8 rounded-[32px]">
                <div class="max-w-xl">
                    <livewire:profile.two-factor-authentication />
                </div>
            </div>

            <div class="glass-panel p-4 sm:p-8 rounded-[32px]">
                <div class="max-w-xl">
                    <livewire:profile.delete-account />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
