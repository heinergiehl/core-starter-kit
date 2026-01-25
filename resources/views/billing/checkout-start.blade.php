@extends('layouts.marketing')

@section('title', __('Checkout') . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('Complete your subscription and start using the product.'))

@section('content')
    <section class="py-16">
        <div class="glass-panel rounded-3xl p-8">
            <div class="flex flex-col gap-3 text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-secondary">{{ __('Checkout') }}</p>
                <h1 class="font-display text-3xl">{{ __('Complete your subscription') }}</h1>
                <p class="text-sm text-ink/70">
                    @auth
                        {{ __('Confirm your details to continue to payment.') }}
                    @else
                        {{ __('Enter your email or sign in to continue to payment.') }}
                    @endauth
                </p>
            </div>

            @if ($errors->has('billing'))
                <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50/10 px-4 py-3 text-sm text-rose-600">
                    {{ $errors->first('billing') }}
                </div>
            @endif

            @if ($errors->has('coupon'))
                <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50/10 px-4 py-3 text-sm text-rose-600">
                    {{ $errors->first('coupon') }}
                </div>
            @endif

            @if ($errors->has('email'))
                <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50/10 px-4 py-3 text-sm text-rose-600">
                    {{ $errors->first('email') }}
                </div>
            @endif

            <div class="mt-10 grid gap-8 lg:grid-cols-2 lg:items-start">
                <div class="card-inner p-6">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Enter your details') }}</h2>

                    @if (!empty($social_providers) && !auth()->check())
                        <div class="mt-4">
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($social_providers as $providerName)
                                    <a
                                        href="{{ route('social.redirect', ['provider' => $providerName, 'intended' => route('checkout.start', ['plan' => $plan['key'] ?? '', 'price' => $price['key'] ?? '', 'provider' => $provider])]) }}"
                                        class="flex items-center justify-center gap-2 rounded-xl border border-ink/10 bg-surface/50 px-4 py-2.5 text-sm font-semibold text-ink/70 transition hover:bg-surface hover:text-ink"
                                    >
                                        @if ($providerName === 'google')
                                            <svg class="h-4 w-4" viewBox="0 0 24 24"><path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                        @elseif ($providerName === 'github')
                                            <svg class="h-4 w-4" viewBox="0 0 24 24"><path fill="currentColor" d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385c.6.105.825-.255.825-.57c0-.285-.015-1.23-.015-2.235c-3.015.555-3.795-.735-4.035-1.41c-.135-.345-.72-1.41-1.23-1.695c-.42-.225-1.02-.78-.015-.795c.945-.015 1.62.87 1.845 1.23c1.08 1.815 2.805 1.305 3.495.99c.105-.78.42-1.305.765-1.605c-2.67-.3-5.46-1.335-5.46-5.925c0-1.305.465-2.385 1.23-3.225c-.12-.3-.54-1.53.12-3.18c0 0 1.005-.315 3.3 1.23c.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23c.66 1.65.24 2.88.12 3.18c.765.84 1.23 1.905 1.23 3.225c0 4.605-2.805 5.625-5.475 5.925c.435.375.81 1.095.81 2.22c0 1.605-.015 2.895-.015 3.3c0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
                                        @endif
                                        {{ __('Continue with :provider', ['provider' => ucfirst($providerName)]) }}
                                    </a>
                                @endforeach
                            </div>
                            <div class="relative my-5">
                                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-ink/10"></div></div>
                                <div class="relative flex justify-center text-xs uppercase"><span class="bg-surface px-2 text-ink/40">{{ __('or') }}</span></div>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('billing.checkout', [], false) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="provider" value="{{ $provider }}">
                        <input type="hidden" name="plan" value="{{ $plan['key'] ?? '' }}">
                        <input type="hidden" name="price" value="{{ $price['key'] ?? '' }}">

                        <div>
                            <label class="text-xs uppercase tracking-[0.2em] text-ink/40">{{ __('Email') }}</label>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email', auth()->user()?->email) }}"
                                class="mt-2 w-full rounded-xl border border-ink/10 bg-surface/50 px-4 py-2.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary"
                                required
                            >
                        </div>

                        <div>
                            <label class="text-xs uppercase tracking-[0.2em] text-ink/40">{{ __('Name') }}</label>
                            <input
                                type="text"
                                name="name"
                                value="{{ old('name', auth()->user()?->name) }}"
                                class="mt-2 w-full rounded-xl border border-ink/10 bg-surface/50 px-4 py-2.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary"
                                placeholder="{{ __('Optional') }}"
                            >
                        </div>

                        @if ($coupon_enabled)
                            <div>
                                <label class="text-xs uppercase tracking-[0.2em] text-ink/40">{{ __('Promo Code') }}</label>
                                <input
                                    type="text"
                                    name="coupon"
                                    value="{{ old('coupon') }}"
                                    class="mt-2 w-full rounded-xl border border-ink/10 bg-surface/50 px-4 py-2.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary"
                                    placeholder="{{ __('Optional') }}"
                                >
                            </div>
                        @endif

                        <button class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white transition hover:bg-primary/90">
                            {{ __('Continue to payment') }}
                        </button>
                    </form>
                </div>

                <div class="card-inner p-6">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Plan details') }}</h2>
                    <div class="mt-4 space-y-3 text-sm text-ink/70">
                        <div class="flex items-center justify-between">
                            <span>{{ $plan['name'] ?? __('Plan') }}</span>
                            <span class="font-semibold text-ink">{{ $price['label'] ?? $price['key'] ?? '' }}</span>
                        </div>
                        @if (!empty($price['amount']))
                            @php
                                $amountValue = (float) $price['amount'];
                                $amountDisplay = !empty($price['amount_is_minor']) ? ($amountValue / 100) : $amountValue;
                            @endphp
                            <div class="flex items-center justify-between">
                                <span>{{ ucfirst($price['interval'] ?? __('one-time')) }}</span>
                                <span class="font-semibold text-ink">
                                    {{ number_format($amountDisplay, !empty($price['amount_is_minor']) ? 2 : 0) }}
                                    {{ strtoupper((string) ($price_currency ?? $price['currency'] ?? 'USD')) }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="mt-6 rounded-xl border border-ink/10 bg-surface/60 px-4 py-3 text-xs text-ink/60">
                        {{ __('Payment is securely handled by Paddle.') }}
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
