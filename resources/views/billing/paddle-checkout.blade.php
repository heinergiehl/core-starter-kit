@extends('layouts.marketing')

@section('title', 'Checkout - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', 'Complete your checkout securely.')

@section('content')
    <section class="py-6 md:py-12">
        <div class="max-w-7xl mx-auto px-4">
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-primary/10 border border-primary/20 mb-3">
                    <svg class="h-3.5 w-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <span class="text-xs font-semibold text-primary">{{ __('Secure Checkout') }}</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-display font-bold text-ink mb-2">{{ __('Complete Your Purchase') }}</h1>
            </div>

            <div class="grid lg:grid-cols-[1fr,380px] gap-6">
                <!-- Main Checkout Area (Left) -->
                <div>
                    @if (!$transaction_id && empty($inline_items))
                        <div class="glass-panel rounded-2xl p-8 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-amber-500/20 text-amber-600 mb-4">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-ink mb-2">{{ __('Missing Checkout Data') }}</h3>
                            <p class="text-sm text-ink/60 mb-6">{{ __('Please restart checkout from the pricing page.') }}</p>
                            <a href="{{ route('pricing') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-primary text-white font-semibold hover:bg-primary/90 transition">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                {{ __('Back to Pricing') }}
                            </a>
                        </div>
                    @else
                        <!-- Paddle Checkout Container -->
                        <div class="glass-panel rounded-2xl overflow-hidden">
                            <div id="paddle-checkout" class="paddle-checkout min-h-[720px]"></div>
                        </div>

                        <!-- Error State -->
                        <div id="paddle-error" class="hidden mt-4 glass-panel rounded-2xl p-4 border-2 border-rose-500/20">
                            <div class="flex items-start gap-3">
                                <svg class="h-5 w-5 text-rose-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div class="flex-1">
                                    <h3 class="text-sm font-semibold text-rose-500 mb-1">{{ __('Checkout Error') }}</h3>
                                    <p class="text-xs text-ink/70">{{ __('Checkout could not load. Please refresh the page or try again.') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Sticky Sidebar (Right) -->
                <div class="lg:sticky lg:top-6 lg:self-start space-y-4">
                    <!-- Order Summary -->
                    <div class="glass-panel rounded-2xl p-5">
                        <h2 class="text-base font-semibold text-ink mb-4 flex items-center gap-2">
                            <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            {{ __('Order Summary') }}
                        </h2>
                        
                        <div class="space-y-3">
                            <div>
                                <p class="font-medium text-ink text-sm">{{ $checkout['plan_name'] ?? __('Plan') }}</p>
                                <p class="text-xs text-ink/60 mt-0.5">{{ $checkout['price_label'] ?? '' }}</p>
                            </div>
                            
                            @if (!empty($checkout['amount']))
                                @php
                                    $amountValue = (float) $checkout['amount'];
                                    $amountDisplay = $checkout['amount_is_minor'] ?? true
                                        ? $amountValue / 100
                                        : $amountValue;
                                @endphp
                                
                                <div class="pt-3 border-t border-ink/10">
                                    <div class="flex items-baseline justify-between">
                                        <span class="text-xs text-ink/60">{{ ucfirst($checkout['interval'] ?? __('one-time')) }}</span>
                                        <div class="text-right">
                                            <span class="text-2xl font-bold text-primary">
                                                {{ number_format($amountDisplay, ($checkout['amount_is_minor'] ?? true) ? 2 : 0) }}
                                            </span>
                                            <span class="text-sm font-semibold text-ink/70 ml-1">
                                                {{ strtoupper((string) ($checkout['currency'] ?? 'USD')) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Trust Indicators -->
                    <div class="glass-panel rounded-2xl p-5">
                        <h3 class="text-sm font-semibold text-ink mb-3">{{ __('Secure Payment') }}</h3>
                        <div class="space-y-2.5 text-xs">
                            <div class="flex items-start gap-2.5">
                                <svg class="h-4 w-4 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <div>
                                    <p class="font-medium text-ink">{{ __('SSL Encrypted') }}</p>
                                    <p class="text-ink/60 mt-0.5">{{ __('Your payment info is secure') }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <svg class="h-4 w-4 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                                <div>
                                    <p class="font-medium text-ink">{{ __('Powered by Paddle') }}</p>
                                    <p class="text-ink/60 mt-0.5">{{ __('Trusted payment processor') }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <svg class="h-4 w-4 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p class="font-medium text-ink">{{ __('Instant Access') }}</p>
                                    <p class="text-ink/60 mt-0.5">{{ __('Start immediately after payment') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Help -->
                    <div class="glass-panel rounded-2xl p-4 bg-primary/5 border-primary/20">
                        <div class="flex items-start gap-2.5">
                            <svg class="h-4 w-4 text-primary flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="text-xs">
                                <p class="font-medium text-ink mb-0.5">{{ __('Need help?') }}</p>
                                <p class="text-ink/70">{{ __('Contact support if you have questions.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($transaction_id || !empty($inline_items))
        <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
        <script>
            (function () {
                const transactionId = @json($transaction_id);
                const env = @json($environment);
                const inlineItems = @json($inline_items);
                const customerEmail = @json($checkout['email'] ?? null);
                const discountId = @json($checkout['discount_id'] ?? null);
                const discountCode = @json($checkout['discount_code'] ?? null);
                const customData = @json($checkout['custom_data'] ?? null);
                const debug = @json(app()->environment('local'));
               
                const vendorId = Number(@json($vendor_id));
                const errorEl = document.getElementById('paddle-error');
                const frameTargetId = 'paddle-checkout';
                const frameTarget = document.getElementById(frameTargetId);
                const successUrl = @json($checkout['success_url'] ?? null) || @json(config('saas.billing.success_url') ?: route('billing.processing', ['provider' => 'paddle'], true));
                const cancelUrl = @json(config('saas.billing.cancel_url')) || @json(route('pricing'));

                if (!window.Paddle) {
                    errorEl.classList.remove('hidden');
                    return;
                }

                if (window.Paddle.Environment && typeof window.Paddle.Environment.set === 'function' && env === 'sandbox') {
                    window.Paddle.Environment.set('sandbox');
                }

                if (!frameTarget) {
                    errorEl.classList.remove('hidden');
                    return;
                }

                const inlineSettings = {
                    displayMode: 'inline',
                    frameTarget: frameTargetId,
                    frameInitialHeight: 720,
                    frameStyle: 'width:100%;min-height:720px;background:transparent;border:none;',
                    variant: 'one-page',
                    successUrl: successUrl,
                };

                const stripPaddleTxnParam = () => {
                    const url = new URL(window.location.href);
                    if (url.searchParams.has('_ptxn')) {
                        url.searchParams.delete('_ptxn');
                        url.searchParams.delete('ptxn');
                        window.history.replaceState({}, document.title, url.toString());
                    }
                };

                stripPaddleTxnParam();

                let completed = false;

                const redirectTo = (url, txId) => {
                    if (!url) {
                        return;
                    }
                    const redirectUrl = new URL(url, window.location.origin);
                    if (txId) {
                        redirectUrl.searchParams.set('_ptxn', txId);
                    }
                    window.location.href = redirectUrl.toString();
                };

                const showSuccessState = () => {
                    frameTarget.innerHTML = `
                        <div class="px-6 py-8 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-ink">Payment complete</h3>
                            <p class="mt-2 text-sm text-ink/60">Redirecting you now...</p>
                        </div>
                    `;
                };

                const handleEvent = (event) => {
                    if (debug) {
                        console.log('[Paddle] event', event);
                    }

                    const rawName = event?.event_name
                        || event?.eventName
                        || event?.name
                        || event?.event
                        || event?.type
                        || '';
                    const eventName = String(rawName).toLowerCase();
                    const status = String(
                        event?.status
                        || event?.data?.status
                        || event?.event_data?.status
                        || ''
                    ).toLowerCase();

                    const isComplete = eventName.includes('checkout.complete')
                        || eventName.includes('checkout.completed')
                        || eventName.includes('checkout_complete')
                        || eventName.includes('checkout.success')
                        || eventName.includes('checkout.purchased')
                        || eventName.includes('transaction.completed')
                        || eventName.includes('transaction.paid')
                        || status === 'completed'
                        || status === 'paid';
                    const isClosed = eventName.includes('checkout.closed')
                        || eventName.includes('checkout.canceled')
                        || eventName.includes('checkout.cancelled');

                    if (isComplete) {
                        if (completed) {
                            return;
                        }
                        completed = true;
                        showSuccessState();
                        const txId = event?.data?.transaction_id
                            || event?.event_data?.transaction_id
                            || event?.transaction_id
                            || event?.data?.transactionId
                            || event?.transactionId
                            || transactionId;
                        setTimeout(() => redirectTo(successUrl, txId), 800);
                    }

                    if (isClosed) {
                        redirectTo(cancelUrl);
                    }
                };

                if (window.Paddle.Initialize && typeof window.Paddle.Initialize === 'function' && Number.isFinite(vendorId)) {
                    window.Paddle.Initialize({
                        seller: vendorId,
                        eventCallback: handleEvent,
                        checkout: {
                            settings: inlineSettings,
                        },
                    });
                }

                if (window.Paddle.Checkout && typeof window.Paddle.Checkout.open === 'function') {
                    const existingFrame = frameTarget.querySelector('iframe');
                    const state = frameTarget.dataset.paddleState;

                    if (state === 'loading' || state === 'ready' || existingFrame) {
                        return;
                    }

                    frameTarget.innerHTML = '';
                    frameTarget.dataset.paddleState = 'loading';

                    const payload = {};

                    const useTransactionId = !!transactionId;
                    if (useTransactionId) {
                        payload.transactionId = transactionId;
                    }

                    if (!useTransactionId && inlineItems.length > 0) {
                        payload.items = inlineItems;
                    }

                    if (customerEmail) {
                        payload.customer = { email: customerEmail };
                    }

                    if (discountId) {
                        payload.discountId = discountId;
                    } else if (discountCode) {
                        payload.discountCode = discountCode;
                    }

                    if (customData) {
                        payload.customData = customData;
                    }

                    payload.settings = inlineSettings;

                    window.Paddle.Checkout.open(payload);

                    setTimeout(() => {
                        if (!frameTarget.querySelector('iframe')) {
                            errorEl.classList.remove('hidden');
                            frameTarget.dataset.paddleState = 'error';
                            return;
                        }
                        frameTarget.dataset.paddleState = 'ready';
                    }, 2000);
                    return;
                }

                errorEl.classList.remove('hidden');
            })();
        </script>
    @endif
@endsection
