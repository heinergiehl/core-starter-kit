@extends('layouts.marketing')

@section('title', 'Checkout - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', 'Complete your checkout securely.')

@section('content')
    <section class="py-16">
        <div class="p-8 glass-panel rounded-3xl">
            <div class="flex flex-col gap-3 text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-secondary">Checkout</p>
                <h1 class="text-3xl font-display">Complete your purchase</h1>
                <p class="text-sm text-ink/70">
                    You will be redirected after payment completes.
                </p>
            </div>

            <div class="mt-10 grid gap-8 lg:grid-cols-2 lg:items-start">
                <div class="card-inner p-6">
                    <h2 class="text-lg font-semibold text-ink">Plan details</h2>
                    <div class="mt-4 space-y-3 text-sm text-ink/70">
                        <div class="flex items-center justify-between">
                            <span>{{ $checkout['plan_name'] ?? 'Plan' }}</span>
                            <span class="font-semibold text-ink">{{ $checkout['price_label'] ?? '' }}</span>
                        </div>
                        @if (!empty($checkout['amount']))
                            @php
                                $amountValue = (float) $checkout['amount'];
                                $amountDisplay = $checkout['amount_is_minor'] ?? true
                                    ? $amountValue / 100
                                    : $amountValue;
                            @endphp
                            <div class="flex items-center justify-between">
                                <span>{{ ucfirst($checkout['interval'] ?? 'one-time') }}</span>
                                <span class="font-semibold text-ink">
                                    {{ number_format($amountDisplay, ($checkout['amount_is_minor'] ?? true) ? 2 : 0) }}
                                    {{ strtoupper((string) ($checkout['currency'] ?? 'USD')) }}
                                </span>
                            </div>
                        @endif
                    </div>
                    <div class="mt-6 rounded-xl border border-ink/10 bg-surface/60 px-4 py-3 text-xs text-ink/60">
                        Secure payment handled by Paddle. You will be redirected after payment completes.
                    </div>
                </div>

                <div>
                    @if (!$transaction_id && empty($inline_items))
                        <div class="px-5 py-4 text-sm card-inner text-ink/70">
                            Missing checkout data. Please restart checkout from the pricing page.
                        </div>
                    @else
                        <div id="paddle-checkout" class="paddle-checkout card-inner px-4 py-6 min-h-[720px]"></div>
                    @endif

                    <div id="paddle-error" class="hidden px-5 py-4 mt-6 text-sm border rounded-2xl border-rose-500/20 bg-rose-500/10 text-rose-200">
                        Checkout could not load. Please refresh or try again.
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
                const successUrl = @json(config('saas.billing.success_url') ?: route('billing.processing', ['provider' => 'paddle'], true));
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
