@extends('layouts.marketing')

@section('title', __('Checkout') . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('Complete your subscription and start using the product.'))

@section('content')
    <section class="py-16">
        <div class="glass-panel rounded-3xl p-8">
            <div class="flex flex-col gap-3 text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-secondary">{{ __('Checkout') }}</p>
                <h1 class="font-display text-3xl">{{ __('Complete your subscription') }}</h1>
                @php
                    $isPaddle = $provider === 'paddle';
                @endphp
                <p class="text-sm text-ink/70">
                    {{ $isPaddle
                        ? __('Continue to Paddle checkout. We will email a confirmation link after payment.')
                        : __('Enter your details to continue to payment.')
                    }}
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

            @if ($errors->has('email') && !$isPaddle)
                <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50/10 px-4 py-3 text-sm text-rose-600">
                    {{ $errors->first('email') }}
                </div>
            @endif

            <div class="mt-10 grid gap-8 lg:grid-cols-2 lg:items-start">
                <div class="card-inner p-6">
                    <h2 class="text-lg font-semibold text-ink">
                        {{ $isPaddle ? __('Continue to payment') : __('Enter your details') }}
                    </h2>

                    <form method="POST" action="{{ route('billing.checkout') }}" class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="provider" value="{{ $provider }}">
                        <input type="hidden" name="plan" value="{{ $plan['key'] ?? '' }}">
                        <input type="hidden" name="price" value="{{ $price['key'] ?? '' }}">

                        @if (!$isPaddle)
                            <div>
                                <label class="text-xs uppercase tracking-[0.2em] text-ink/40">{{ __('Email') }}</label>
                                <input
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    class="mt-2 w-full rounded-xl border border-ink/10 bg-surface/50 px-4 py-2.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary"
                                    required
                                >
                            </div>

                            <div>
                                <label class="text-xs uppercase tracking-[0.2em] text-ink/40">{{ __('Name') }}</label>
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ old('name') }}"
                                    class="mt-2 w-full rounded-xl border border-ink/10 bg-surface/50 px-4 py-2.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary"
                                    placeholder="{{ __('Optional') }}"
                                >
                            </div>
                        @endif

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

                        @if (!empty($social_providers) && !$isPaddle)
                            <div class="mt-6">
                                <p class="text-xs uppercase tracking-[0.2em] text-ink/40">{{ __('Sign in faster') }}</p>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach ($social_providers as $providerName)
                                    <a
                                        href="{{ route('social.redirect', ['provider' => $providerName, 'intended' => request()->fullUrl()]) }}"
                                        class="flex items-center justify-center gap-2 rounded-xl border border-ink/10 bg-surface/50 px-4 py-2 text-xs font-semibold text-ink/70 transition hover:bg-surface hover:text-ink"
                                    >
                                        {{ ucfirst($providerName) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
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
