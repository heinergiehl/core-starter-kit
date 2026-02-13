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
                $githubUsername = trim((string) ($repoAccessGrant?->github_username ?? $githubAccount?->provider_name ?? ''));
            @endphp

            <div id="repo-access" class="glass-panel p-4 sm:p-8 rounded-[32px]">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-wider text-ink/40">{{ __('GitHub Access') }}</p>
                    <h3 class="mt-2 text-2xl font-display text-ink">{{ __('Private Repository Access') }}</h3>
                    <p class="mt-2 text-sm text-ink/60">
                        {{ __('Select your GitHub username and we will grant repository access after successful purchase.') }}
                    </p>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-ink/10 bg-surface/20 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-ink/50">{{ __('Selected GitHub Username') }}</p>
                            <p id="profile-repo-access-selected" class="mt-2 text-sm font-semibold text-ink/70">
                                {{ $githubUsername !== '' ? '@'.$githubUsername : __('Not selected') }}
                            </p>
                            <p class="mt-1 text-xs text-ink/50">{{ __('Search and pick your account from GitHub in real time.') }}</p>
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

                    @if ($repoAccessEnabled && $canRequestRepoAccess)
                        <div class="mt-6">
                            <label for="profile-repo-access-search-input" class="text-xs font-medium uppercase tracking-wide text-ink/50">{{ __('Find GitHub account') }}</label>
                            <input
                                id="profile-repo-access-search-input"
                                type="text"
                                autocomplete="off"
                                placeholder="{{ __('Type GitHub username...') }}"
                                class="mt-2 w-full rounded-xl border border-ink/15 bg-surface/20 px-4 py-3 text-sm text-ink focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                            >
                            <div id="profile-repo-access-search-results" class="mt-3 max-h-56 overflow-auto space-y-2"></div>
                            <p id="profile-repo-access-feedback" class="mt-2 text-xs text-blue-600 hidden"></p>
                        </div>
                    @endif

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

            @if ($repoAccessEnabled && $canRequestRepoAccess)
                <script>
                    (() => {
                        const searchInput = document.getElementById('profile-repo-access-search-input');
                        const searchResults = document.getElementById('profile-repo-access-search-results');
                        const selectedLabel = document.getElementById('profile-repo-access-selected');
                        const feedback = document.getElementById('profile-repo-access-feedback');
                        if (!searchInput || !searchResults || !selectedLabel || !feedback) {
                            return;
                        }

                        const searchUrl = @json(route('repo-access.github.search'));
                        const selectUrl = @json(route('repo-access.github.select', [], false));
                        const csrfToken = @json(csrf_token());
                        let searchDebounce = null;

                        const setFeedback = (message, tone = 'info') => {
                            feedback.textContent = message || '';
                            feedback.classList.toggle('hidden', !message);
                            feedback.classList.remove('text-blue-600', 'text-emerald-600', 'text-rose-600');
                            feedback.classList.add(
                                tone === 'success' ? 'text-emerald-600' : (tone === 'error' ? 'text-rose-600' : 'text-blue-600')
                            );
                        };

                        const renderResults = (items) => {
                            searchResults.innerHTML = '';
                            if (!Array.isArray(items) || items.length === 0) {
                                return;
                            }

                            items.forEach((item) => {
                                const button = document.createElement('button');
                                button.type = 'button';
                                button.className = 'w-full text-left rounded-xl border border-ink/10 bg-surface/20 px-3 py-2 hover:bg-surface/30 transition flex items-center gap-3';
                                button.innerHTML = `
                                    <img src="${item.avatar_url || ''}" alt="" class="h-8 w-8 rounded-full bg-surface/40">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-ink">@${item.login}</p>
                                        <p class="text-xs text-ink/50">${item.html_url || ''}</p>
                                    </div>
                                `;

                                button.addEventListener('click', async () => {
                                    try {
                                        const response = await fetch(selectUrl, {
                                            method: 'POST',
                                            headers: {
                                                'Accept': 'application/json',
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': csrfToken,
                                            },
                                            credentials: 'same-origin',
                                            body: JSON.stringify({
                                                login: item.login,
                                                id: item.id,
                                            }),
                                        });

                                        const data = await response.json().catch(() => ({}));
                                        if (!response.ok) {
                                            throw new Error(data.message || 'select_failed');
                                        }

                                        selectedLabel.textContent = `@${item.login}`;
                                        searchInput.value = item.login;
                                        searchResults.innerHTML = '';
                                        setFeedback(data.message || @json(__('GitHub username selected.')), 'success');
                                    } catch (error) {
                                        setFeedback(error.message || @json(__('Could not select GitHub username.')), 'error');
                                    }
                                });

                                searchResults.appendChild(button);
                            });
                        };

                        const runSearch = async (term) => {
                            const query = (term || '').trim();
                            if (query.length < 2) {
                                searchResults.innerHTML = '';
                                return;
                            }

                            try {
                                const url = new URL(searchUrl, window.location.origin);
                                url.searchParams.set('q', query);

                                const response = await fetch(url.toString(), {
                                    headers: { 'Accept': 'application/json' },
                                    credentials: 'same-origin',
                                });
                                const data = await response.json().catch(() => ({}));

                                if (!response.ok) {
                                    renderResults([]);
                                    setFeedback(data.message || @json(__('GitHub search failed.')), 'error');
                                    return;
                                }

                                setFeedback('', 'info');
                                renderResults(data.items || []);
                            } catch (_error) {
                                renderResults([]);
                                setFeedback(@json(__('GitHub search failed.')), 'error');
                            }
                        };

                        searchInput.addEventListener('input', (event) => {
                            if (searchDebounce) {
                                window.clearTimeout(searchDebounce);
                            }
                            searchDebounce = window.setTimeout(() => runSearch(event.target.value), 280);
                        });
                    })();
                </script>
            @endif

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
