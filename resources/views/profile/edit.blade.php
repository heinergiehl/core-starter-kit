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
                        {{ __('Manage the GitHub account that should receive private repository access.') }}
                    </p>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-ink/10 bg-surface/20 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-ink/50">{{ __('Selected GitHub Username') }}</p>
                            <p id="profile-repo-access-selected" class="mt-2 text-sm font-semibold text-ink/70">
                                {{ $githubUsername !== '' ? '@'.$githubUsername : __('Not selected') }}
                            </p>
                            <p id="profile-repo-access-helper" class="mt-1 text-xs text-ink/50">{{ __('Search and pick your account from GitHub in real time.') }}</p>
                        </div>

                        <div class="rounded-2xl border border-ink/10 bg-surface/20 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-ink/50">{{ __('Repo Access Status') }}</p>
                            <div class="mt-2">
                                <span id="profile-repo-access-status-badge" class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ $repoAccessGrant?->status?->getLabel() ?? __('Not requested') }}
                                </span>
                            </div>
                            <p id="profile-repo-access-state-copy" class="mt-2 text-xs text-ink/60">
                                {{ __('Select your account and confirm to grant access.') }}
                            </p>
                            <p id="profile-repo-access-error" role="alert" class="mt-2 text-xs text-rose-600 {{ $repoAccessGrant?->last_error ? '' : 'hidden' }}">
                                {{ $repoAccessGrant?->last_error }}
                            </p>
                            <p class="mt-2 text-xs text-ink/50">{{ __('Repository: :repo', ['repo' => $repoAccessRepository]) }}</p>
                        </div>
                    </div>

                    @if ($repoAccessEnabled && $canRequestRepoAccess)
                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <button id="profile-repo-access-primary-action" type="button" class="btn-primary">
                                {{ __('Refresh Status') }}
                            </button>
                            <button id="profile-repo-access-toggle-search" type="button" class="btn-secondary">
                                {{ $githubUsername !== '' ? __('Change GitHub Username') : __('Choose GitHub Username') }}
                            </button>
                            <a
                                id="profile-repo-access-open-invitations"
                                href="https://github.com/notifications?query=reason%3Ainvitation"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="hidden text-sm font-medium text-primary hover:text-primary/80"
                            >
                                {{ __('Open GitHub Invitations') }}
                            </a>
                        </div>

                        <div id="profile-repo-access-search-panel" class="mt-6 {{ $githubUsername !== '' ? 'hidden' : '' }}">
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
                </div>
            </div>

            @if ($repoAccessEnabled && $canRequestRepoAccess)
                <script>
                    (() => {
                        const statusUrl = @json(route('repo-access.status'));
                        const searchUrl = @json(route('repo-access.github.search'));
                        const selectUrl = @json(route('repo-access.github.select', [], false));
                        const syncUrl = @json(route('repo-access.sync', [], false));
                        const csrfToken = @json(csrf_token());

                        const searchInput = document.getElementById('profile-repo-access-search-input');
                        const searchResults = document.getElementById('profile-repo-access-search-results');
                        const selectedLabel = document.getElementById('profile-repo-access-selected');
                        const helper = document.getElementById('profile-repo-access-helper');
                        const feedback = document.getElementById('profile-repo-access-feedback');
                        const statusBadge = document.getElementById('profile-repo-access-status-badge');
                        const stateCopy = document.getElementById('profile-repo-access-state-copy');
                        const errorLabel = document.getElementById('profile-repo-access-error');
                        const primaryButton = document.getElementById('profile-repo-access-primary-action');
                        const toggleSearchButton = document.getElementById('profile-repo-access-toggle-search');
                        const invitationLink = document.getElementById('profile-repo-access-open-invitations');
                        const searchPanel = document.getElementById('profile-repo-access-search-panel');

                        if (!searchInput || !searchResults || !selectedLabel || !helper || !feedback || !statusBadge || !stateCopy || !errorLabel || !primaryButton || !toggleSearchButton || !invitationLink || !searchPanel) {
                            return;
                        }

                        const statusClasses = {
                            queued: ['text-amber-700', 'bg-amber-500/10', 'border-amber-500/20'],
                            processing: ['text-blue-700', 'bg-blue-500/10', 'border-blue-500/20'],
                            awaiting_github_link: ['text-ink/70', 'bg-surface/30', 'border-ink/10'],
                            ready: ['text-blue-700', 'bg-blue-500/10', 'border-blue-500/20'],
                            invited: ['text-emerald-700', 'bg-emerald-500/10', 'border-emerald-500/20'],
                            granted: ['text-emerald-700', 'bg-emerald-500/10', 'border-emerald-500/20'],
                            failed: ['text-rose-700', 'bg-rose-500/10', 'border-rose-500/20'],
                            disabled: ['text-ink/70', 'bg-surface/30', 'border-ink/10'],
                        };
                        const allStatusClasses = [...new Set(Object.values(statusClasses).flat())];

                        let currentStatus = null;
                        let currentPrimaryAction = 'none';
                        let isSyncing = false;
                        let searchPanelOpen = !selectedLabel.textContent.includes('@');
                        let pollTimer = null;
                        let searchDebounce = null;
                        let searchRequestController = null;

                        const notify = (message, type = 'info') => {
                            if (!message) {
                                return;
                            }
                            window.dispatchEvent(new CustomEvent('notify', { detail: { message, type } }));
                        };

                        const setSearchPanelOpen = (open) => {
                            searchPanelOpen = open;
                            searchPanel.classList.toggle('hidden', !open);
                            toggleSearchButton.textContent = open
                                ? @json(__('Close Username Picker'))
                                : (currentStatus?.github_username ? @json(__('Change GitHub Username')) : @json(__('Choose GitHub Username')));
                            if (open) {
                                searchInput.focus();
                            }
                        };

                        const setPrimaryState = (label, disabled, loading = false) => {
                            primaryButton.textContent = loading ? @json(__('Working...')) : label;
                            primaryButton.disabled = disabled || loading;
                            primaryButton.classList.toggle('opacity-60', disabled || loading);
                            primaryButton.classList.toggle('cursor-not-allowed', disabled || loading);
                        };

                        const setStatusBadge = (key, label) => {
                            statusBadge.classList.remove(...allStatusClasses);
                            statusBadge.classList.add(...(statusClasses[key] || statusClasses.awaiting_github_link));
                            statusBadge.textContent = label;
                        };

                        const setFeedback = (message, tone = 'info') => {
                            feedback.textContent = message || '';
                            feedback.classList.toggle('hidden', !message);
                            feedback.classList.remove('text-blue-600', 'text-emerald-600', 'text-rose-600', 'text-amber-600');
                            feedback.classList.add(
                                tone === 'success'
                                    ? 'text-emerald-600'
                                    : tone === 'error'
                                        ? 'text-rose-600'
                                        : tone === 'warning'
                                            ? 'text-amber-600'
                                            : 'text-blue-600'
                            );
                        };

                        const startPolling = () => {
                            if (pollTimer) {
                                return;
                            }
                            pollTimer = window.setInterval(() => fetchStatus(false).catch(() => {}), 3500);
                        };

                        const stopPolling = () => {
                            if (!pollTimer) {
                                return;
                            }
                            window.clearInterval(pollTimer);
                            pollTimer = null;
                        };

                        const renderResults = (items, query = '') => {
                            searchResults.innerHTML = '';
                            if (!Array.isArray(items) || items.length === 0) {
                                if (query.trim().length >= 2) {
                                    const empty = document.createElement('p');
                                    empty.className = 'rounded-xl border border-ink/10 bg-surface/20 px-3 py-2 text-xs text-ink/60';
                                    empty.textContent = @json(__('No matching GitHub users found. Keep typing to refine.'));
                                    searchResults.appendChild(empty);
                                }
                                return;
                            }

                            items.forEach((item) => {
                                const button = document.createElement('button');
                                button.type = 'button';
                                button.className = 'w-full text-left rounded-xl border border-ink/10 bg-surface/20 px-3 py-2 hover:bg-surface/30 transition flex items-center gap-3';

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
                                            throw new Error(data.message || @json(__('Could not select GitHub username.')));
                                        }

                                        if (data?.user?.login) {
                                            searchInput.value = data.user.login;
                                        }
                                        searchResults.innerHTML = '';
                                        setSearchPanelOpen(false);
                                        const message = data.message || @json(__('GitHub username selected.'));
                                        setFeedback(message, 'success');
                                        notify(message, 'success');
                                        await fetchStatus(false);
                                    } catch (error) {
                                        setFeedback(error.message || @json(__('Could not select GitHub username.')), 'error');
                                    }
                                });

                                const avatar = document.createElement('img');
                                avatar.src = item.avatar_url || '';
                                avatar.alt = '';
                                avatar.className = 'h-8 w-8 rounded-full bg-surface/40';
                                button.appendChild(avatar);

                                const copy = document.createElement('div');
                                copy.className = 'min-w-0';

                                const login = document.createElement('p');
                                login.className = 'text-sm font-semibold text-ink';
                                login.textContent = `@${item.login}`;
                                copy.appendChild(login);

                                const url = document.createElement('p');
                                url.className = 'text-xs text-ink/50';
                                url.textContent = item.html_url || '';
                                copy.appendChild(url);

                                button.appendChild(copy);
                                searchResults.appendChild(button);
                            });
                        };

                        const renderState = (state) => {
                            currentStatus = state;
                            const hasUsername = !!state.github_username;
                            selectedLabel.textContent = hasUsername ? `@${state.github_username}` : @json(__('Not selected'));

                            if (state.grant_error) {
                                errorLabel.textContent = state.grant_error;
                                errorLabel.classList.remove('hidden');
                            } else {
                                errorLabel.textContent = '';
                                errorLabel.classList.add('hidden');
                            }

                            let statusKey = String(state.grant_status || 'awaiting_github_link');
                            let statusLabel = state.grant_label || @json(__('Not requested'));
                            let message = @json(__('Choose your GitHub username to continue.'));
                            let action = 'none';
                            let primaryLabel = @json(__('Choose GitHub Username'));
                            let disablePrimary = true;

                            invitationLink.classList.add('hidden');
                            toggleSearchButton.disabled = false;

                            if (!state.enabled) {
                                statusKey = 'disabled';
                                statusLabel = @json(__('Disabled'));
                                message = @json(__('Repository access module is disabled.'));
                                primaryLabel = @json(__('Unavailable'));
                                toggleSearchButton.disabled = true;
                                setSearchPanelOpen(false);
                            } else if (!state.eligible) {
                                statusKey = 'disabled';
                                statusLabel = @json(__('Locked'));
                                message = @json(__('Repository access becomes available after a successful purchase.'));
                                primaryLabel = @json(__('Purchase Required'));
                                toggleSearchButton.disabled = true;
                                setSearchPanelOpen(false);
                            } else if (statusKey === 'queued' || statusKey === 'processing') {
                                action = 'refresh';
                                disablePrimary = false;
                                primaryLabel = @json(__('Refresh Status'));
                                message = state.is_stale
                                    ? @json(__('Still processing longer than expected. If this persists, your queue worker may be offline.'))
                                    : @json(__('Repository access is processing. This usually finishes within about a minute.'));
                            } else if (statusKey === 'invited') {
                                action = 'refresh';
                                disablePrimary = false;
                                primaryLabel = @json(__('Refresh Status'));
                                message = @json(__('Invitation sent. Ask the customer to accept it via GitHub notifications or email.'));
                                invitationLink.classList.remove('hidden');
                            } else if (statusKey === 'granted') {
                                action = 'refresh';
                                disablePrimary = false;
                                primaryLabel = @json(__('Refresh Status'));
                                message = @json(__('Access granted. This GitHub account can clone the private repository.'));
                            } else if (statusKey === 'failed') {
                                action = hasUsername ? 'sync' : 'none';
                                disablePrimary = !hasUsername;
                                primaryLabel = hasUsername ? @json(__('Retry Access Grant')) : @json(__('Choose GitHub Username'));
                                message = hasUsername
                                    ? @json(__('Last attempt failed. Verify account selection and retry.'))
                                    : @json(__('Select a GitHub username before retrying.'));
                            } else if (hasUsername) {
                                statusKey = statusKey === 'awaiting_github_link' ? 'ready' : statusKey;
                                statusLabel = statusKey === 'ready' ? @json(__('Ready')) : statusLabel;
                                action = 'sync';
                                disablePrimary = false;
                                primaryLabel = @json(__('Grant Repository Access'));
                                message = @json(__('Ready to grant access. Confirm to send invitation/add collaborator.'));
                            }

                            if (!hasUsername && !toggleSearchButton.disabled) {
                                setSearchPanelOpen(true);
                            }

                            if ((statusKey === 'granted' || statusKey === 'invited') && searchPanelOpen) {
                                setSearchPanelOpen(false);
                            }

                            setStatusBadge(statusKey, statusLabel);
                            helper.textContent = hasUsername
                                ? @json(__('Change the account only if access should be granted to another GitHub username.'))
                                : @json(__('Search and pick your account from GitHub in real time.'));
                            stateCopy.textContent = message;

                            currentPrimaryAction = action;
                            setPrimaryState(primaryLabel, disablePrimary || action === 'none', false);
                            state.is_in_progress ? startPolling() : stopPolling();
                        };

                        const fetchStatus = async (showMessage = false) => {
                            const response = await fetch(statusUrl, {
                                headers: { Accept: 'application/json' },
                                credentials: 'same-origin',
                            });
                            if (!response.ok) {
                                throw new Error('status_fetch_failed');
                            }
                            const data = await response.json();
                            renderState(data);
                            if (showMessage) {
                                setFeedback(@json(__('Status refreshed.')), 'info');
                            }
                            return data;
                        };

                        const queueSync = async () => {
                            if (isSyncing || currentPrimaryAction !== 'sync') {
                                return;
                            }
                            isSyncing = true;
                            setPrimaryState(primaryButton.textContent, true, true);
                            try {
                                const response = await fetch(syncUrl, {
                                    method: 'POST',
                                    headers: {
                                        Accept: 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken,
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ source: 'profile_card' }),
                                });
                                const data = await response.json().catch(() => ({}));
                                if (!response.ok) {
                                    throw new Error(data.message || @json(__('Could not queue repository access sync.')));
                                }
                                const message = data.message || @json(__('Repository access sync has been queued.'));
                                const tone = ['queued', 'processing'].includes(String(data.status || '')) ? 'info' : 'success';
                                setFeedback(message, tone);
                                notify(message, tone);
                                await fetchStatus(false);
                            } catch (error) {
                                const message = error.message || @json(__('Could not queue repository access sync.'));
                                setFeedback(message, 'error');
                                notify(message, 'error');
                            } finally {
                                isSyncing = false;
                                if (currentStatus) {
                                    renderState(currentStatus);
                                }
                            }
                        };

                        const runSearch = async (term) => {
                            const query = (term || '').trim();
                            if (query.length < 2) {
                                searchResults.innerHTML = '';
                                return;
                            }

                            if (searchRequestController) {
                                searchRequestController.abort();
                            }
                            searchRequestController = new AbortController();

                            try {
                                const url = new URL(searchUrl, window.location.origin);
                                url.searchParams.set('q', query);

                                const response = await fetch(url.toString(), {
                                    headers: { 'Accept': 'application/json' },
                                    credentials: 'same-origin',
                                    signal: searchRequestController.signal,
                                });
                                const data = await response.json().catch(() => ({}));

                                if (!response.ok) {
                                    renderResults([], query);
                                    setFeedback(data.message || @json(__('GitHub search failed.')), 'error');
                                    return;
                                }

                                renderResults(data.items || [], query);
                            } catch (error) {
                                if (error?.name === 'AbortError') {
                                    return;
                                }
                                renderResults([], query);
                                setFeedback(@json(__('GitHub search failed.')), 'error');
                            }
                        };

                        toggleSearchButton.addEventListener('click', () => {
                            if (!toggleSearchButton.disabled) {
                                setSearchPanelOpen(!searchPanelOpen);
                            }
                        });

                        primaryButton.addEventListener('click', async () => {
                            if (currentPrimaryAction === 'sync') {
                                await queueSync();
                                return;
                            }
                            if (currentPrimaryAction === 'refresh') {
                                try {
                                    await fetchStatus(true);
                                } catch (_error) {
                                    setFeedback(@json(__('Could not refresh repo access status right now.')), 'error');
                                }
                            }
                        });

                        searchInput.addEventListener('input', (event) => {
                            if (searchDebounce) {
                                window.clearTimeout(searchDebounce);
                            }
                            searchDebounce = window.setTimeout(() => runSearch(event.target.value), 280);
                        });

                        fetchStatus(false).catch(() => {
                            setFeedback(@json(__('Could not load repository access status yet.')), 'error');
                        });

                        window.addEventListener('beforeunload', () => {
                            stopPolling();
                            if (searchRequestController) {
                                searchRequestController.abort();
                            }
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
