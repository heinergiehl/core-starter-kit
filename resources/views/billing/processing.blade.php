@extends('layouts.marketing')

@section('title', 'Processing Billing - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', 'Confirming your billing status and activating your plan.')
@section('meta_robots', 'noindex,follow')

@section('content')
    @php
        $portalUrl = route('billing.portal');
        $isLoggedIn = auth()->check();
        $successRedirect = $isLoggedIn ? route('billing.index') : route('login');
    @endphp

    <section class="py-16">
        <div class="glass-panel rounded-3xl p-8 text-center relative">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-secondary">Billing</p>
                    <h1 class="mt-3 font-display text-3xl">Activating your plan</h1>
                    <p class="mt-3 text-sm text-ink/70">
                        We are waiting for the webhook confirmation from your billing provider.
                        This usually takes a few seconds.
                    </p>
                </div>
            </div>

            <div class="mt-8 grid gap-6 md:grid-cols-2">
                <div class="card-inner px-5 py-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-ink/50">Status</p>
                    <p id="billing-status" class="mt-2 text-lg font-semibold text-ink">
                        {{ __('Checking...') }}
                    </p>
                    <p id="billing-details" class="mt-2 text-sm text-ink/60">
                        {{ __('We will keep polling for updates.') }}
                    </p>
                </div>
                <div class="card-inner px-5 py-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-ink/50">Next steps</p>
                    <div class="mt-3 flex flex-wrap gap-3 text-sm font-semibold">
                        @if ($isLoggedIn)
                            <a href="{{ route('dashboard') }}" class="rounded-full border border-ink/15 px-4 py-2 text-ink/70 hover:text-ink">Go to dashboard</a>
                            <a href="{{ $portalUrl }}" class="rounded-full bg-primary px-4 py-2 text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90">Manage billing</a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-full border border-ink/15 px-4 py-2 text-ink/70 hover:text-ink">Log in</a>
                            <a href="{{ route('pricing') }}" class="rounded-full border border-ink/15 px-4 py-2 text-ink/70 hover:text-ink">View pricing</a>
                        @endif
                    </div>
                </div>
            </div>

            <div id="billing-retry" class="mt-8 hidden rounded-2xl border border-amber-500/20 bg-amber-500/10 px-5 py-4 text-sm text-amber-200">
                We are still waiting for confirmation. If this takes longer than a few minutes, check the webhook logs in the Admin Panel.
            </div>
        </div>
    </section>

    <script>
        (function () {
            const statusEl = document.getElementById('billing-status');
            const detailsEl = document.getElementById('billing-details');
            const retryEl = document.getElementById('billing-retry');
            const sessionUuid = @json($session_uuid ?? null);
            const successRedirect = @json($successRedirect);
            const isLoggedIn = @json($isLoggedIn);
            const statusUrl = new URL(@json(route('billing.status')));
            if (sessionUuid) {
                statusUrl.searchParams.set('session', sessionUuid);
            }
            // Add timestamp to prevent caching
            statusUrl.searchParams.set('_t', Date.now());

            const maxAttempts = 60; // Increased to 3 minutes
            const intervalMs = 2000; // Faster polling (2s)
            let attempts = 0;

            const update = (status, detail, isDone) => {
                statusEl.textContent = status;
                detailsEl.textContent = detail;
                if (isDone) {
                    retryEl.classList.add('hidden');
                }
            };

            const checkStatus = async () => {
                attempts += 1;
                try {
                    const response = await fetch(statusUrl.toString(), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });

                    // Handle redirects (likely to login or verify-email)
                    if (response.redirected) {
                        console.log('Redirect detected, reloading page to restore session...');
                        window.location.reload();
                        return;
                    }

                    // Check content type
                    const contentType = response.headers.get("content-type");
                    if (!contentType || !contentType.includes("application/json")) {
                        console.log('Received non-JSON response:', contentType);
                         // If we get HTML (like login page), reload to fix session
                         if (attempts > 1) {
                            console.log('Unexpected response type, reloading...');
                            window.location.reload();
                            return;
                         }
                        throw new Error("Invalid response type");
                    }

                    if (response.ok) {
                        const data = await response.json();
                        const type = data.type || 'subscription';
                        const status = data.status || 'pending';
                        const isSuccess = ['active', 'trialing', 'paid', 'complete', 'completed'].includes(status);
                        const isFailure = ['canceled', 'cancelled', 'refunded', 'failed'].includes(status);

                        if (isSuccess) {
                            update('Active', `Plan ${data.plan_key || ''} is now active.`, true);
                            if (successRedirect) {
                                setTimeout(() => {
                                    window.location.href = successRedirect;
                                }, 1000);
                            }
                            return;
                        }

                        if (isFailure) {
                            update('Needs attention', 'Payment did not complete. Please retry or contact support.', true);
                            return;
                        }

                        update('Processing', `Waiting for ${type} confirmation...`, false);
                    } else {
                        if (response.status === 401 || response.status === 419) {
                             console.log('Session expired, reloading...');
                             window.location.reload();
                             return;
                        }
                        update('Processing', 'Waiting for webhook confirmation...', false);
                    }
                } catch (error) {
                    console.error('Polling error:', error);
                    update('Processing', 'We will retry shortly.', false);
                }

                if (attempts >= maxAttempts) {
                    retryEl.classList.remove('hidden');
                    return;
                }

                setTimeout(checkStatus, intervalMs);
            };

            checkStatus();
        })();
    </script>
@endsection
