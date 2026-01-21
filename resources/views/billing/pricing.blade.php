@extends('layouts.marketing')

@section('title', __('Pricing') . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('Choose a subscription or one-time plan with Stripe, Paddle, or Lemon Squeezy.'))

@section('content')
    @php
        $user = auth()->user();
        $team = $user?->currentTeam;
        $canCheckout = $user && $team && $user->can('billing', $team);
        
        // Check if user already has any purchase (subscription OR one-time)
        $hasPurchased = $canCheckout && app(\App\Domain\Billing\Services\CheckoutService::class)->hasAnyPurchase($team);
        
        // Get customer-friendly provider labels from config
        $providerLabels = config('saas.billing.pricing.provider_labels', [
            'stripe' => 'Stripe',
            'paddle' => 'Paddle',
            'lemonsqueezy' => 'Lemon Squeezy',
        ]);
        
        // Check if provider choice is enabled for customers
        $providerChoiceEnabled = config('saas.billing.pricing.provider_choice_enabled', true);
        
        $catalog = strtolower((string) config('saas.billing.catalog', 'config'));
        $couponEnabledProviders = array_map('strtolower', config('saas.billing.discounts.providers', ['stripe']));
        $couponEnabled = in_array($provider ?? 'stripe', $couponEnabledProviders, true);
    @endphp

    <section class="py-12">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 mb-4 text-xs font-semibold border rounded-full border-primary/20 bg-primary/10 text-primary backdrop-blur-md">
                    {{ __('Pricing Plans') }}
                </div>
                <h1 class="text-4xl font-bold font-display text-ink sm:text-5xl">{{ __('Plans that scale with your product') }}</h1>
                <p class="mt-4 text-lg text-ink/60">{{ __('Subscription and one-time options with unified billing data. Switch providers instantly.') }}</p>
            </div>
            
            @if ($providerChoiceEnabled && count($providers) > 1)
                <!-- Provider Toggles (only shown when multiple providers and choice enabled) -->
                <div class="flex flex-wrap items-center gap-2 p-1.5 rounded-full bg-surface-highlight/10 border border-ink/5 backdrop-blur-md">
                    @foreach ($providers as $providerOption)
                        <a
                            href="{{ route('pricing', ['provider' => $providerOption]) }}"
                            class="{{ $providerOption === $provider 
                                ? 'bg-primary text-white shadow-lg shadow-primary/20' 
                                : 'text-ink/60 hover:text-ink hover:bg-surface/50' }} 
                                rounded-full px-5 py-2 text-sm font-semibold transition-all duration-300"
                        >
                            {{ $providerLabels[$providerOption] ?? ucfirst($providerOption) }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($providerChoiceEnabled && count($providers) > 1)
            <!-- Provider Info Banner (only shown in multi-provider mode) -->
            <div class="flex items-center gap-2 px-6 py-3 mt-8 text-sm border rounded-2xl border-ink/5 bg-surface-highlight/5 text-ink/60 backdrop-blur">
                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>
                    <span class="font-semibold text-ink">{{ __('Active Provider:') }}</span>
                    {{ $providerLabels[$provider] ?? ucfirst($provider) }}
                </span>
                <span class="mx-2 text-ink/20">|</span>
                <span>
                    {{ $catalog === 'database'
                        ? __('Manage prices in Admin > Prices to activate checkout buttons.')
                        : __('Use pricing IDs from `.env` to activate checkout buttons.') }}
                </span>
            </div>
        @endif

        @if ($errors->has('billing'))
            <div class="px-4 py-3 mt-6 text-sm border rounded-2xl border-rose-200 bg-rose-50/10 text-rose-600 backdrop-blur">
                {{ $errors->first('billing') }}
            </div>
        @endif

        @if ($errors->has('email'))
            <div class="px-4 py-3 mt-6 text-sm border rounded-2xl border-rose-200 bg-rose-50/10 text-rose-600 backdrop-blur">
                {{ $errors->first('email') }}
            </div>
        @endif

        @if ($errors->has('coupon'))
            <div class="px-4 py-3 mt-6 text-sm border rounded-2xl border-rose-200 bg-rose-50/10 text-rose-600 backdrop-blur">
                {{ $errors->first('coupon') }}
            </div>
        @endif
    </section>

    <section class="grid gap-8 lg:grid-cols-3">
        @foreach ($plans as $plan)
            @php
                $isHighlighted = !empty($plan['highlight']);
                $isOneTime = ($plan['type'] ?? 'subscription') === 'one_time';
                $planTypeLabel = $isOneTime ? __('One-time') : __('Subscription');
            @endphp
            
            <!-- Plan Card -->
            <div class="glass-panel rounded-[32px] p-8 flex flex-col relative group {{ $isHighlighted ? 'border-primary/50 shadow-primary/10 ring-1 ring-primary/20' : '' }}">
                @if ($isHighlighted)
                    <div class="absolute -translate-x-1/2 -top-4 left-1/2">
                        <span class="px-4 py-1 text-xs font-bold text-white rounded-full shadow-lg bg-gradient-to-r from-primary to-secondary">{{ __('Most Popular') }}</span>
                    </div>
                @endif

                <div class="mb-6">
                    <p class="mb-2 text-xs font-bold tracking-widest uppercase text-primary">{{ $planTypeLabel }}</p>
                    <h2 class="text-3xl font-bold font-display text-ink">{{ $plan['name'] }}</h2>
                    @if (!empty($plan['summary']))
                        <p class="mt-2 text-sm text-ink/60">{{ $plan['summary'] }}</p>
                    @endif
                </div>

                <div class="flex-grow space-y-4">
                    @if (empty($plan['prices']))
                        <div class="px-6 py-6 text-sm text-center border border-dashed rounded-2xl border-ink/20 bg-surface/30 text-ink/60">
                            {{ __('Add prices in Admin > Prices to show checkout options for this plan.') }}
                        </div>
                    @else
                        @foreach ($plan['prices'] as $price)
                            @php
                                $amount = $price['amount'] ?? null;
                                $currency = strtoupper((string) ($price['currency'] ?? 'USD'));
                                $label = $price['label'] ?? ucfirst($price['key']);
                                $interval = $price['interval'] ?? null;
                                $amountDisplay = __('Custom');
                                if (is_numeric($amount)) {
                                    $amountValue = (float) $amount;
                                    $amountDisplay = '$' . number_format(
                                        !empty($price['amount_is_minor']) ? ($amountValue / 100) : $amountValue,
                                        !empty($price['amount_is_minor']) ? 2 : 0
                                    );
                                }
                            @endphp
                            
                            <!-- Price Option -->
                            <div class="p-5 transition card-inner hover:border-primary/30">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <p class="text-sm font-semibold text-ink">{{ $label }}</p>
                                        @if ($interval)
                                            <p class="text-xs capitalize text-ink/50">{{ $interval }}</p>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <span class="text-3xl font-bold font-display text-ink">{{ $amountDisplay }}</span>
                                        <span class="text-xs font-medium text-ink/40">{{ $currency }}</span>
                                    </div>
                                </div>

                                <div>
                                    @if (empty($price['is_available']))
                                        @if ($catalog === 'database')
                                            <p class="inline-block px-2 py-1 text-xs font-medium rounded-md text-amber-500 bg-amber-500/10">{{ __('Missing Price ID') }}</p>
                                        @else
                                            <p class="inline-block px-2 py-1 text-xs font-medium rounded-md text-amber-500 bg-amber-500/10">{{ __('Missing Env ID') }}</p>
                                        @endif
                                    @elseif ($hasPurchased)
                                        {{-- User already has a purchase - show link to billing instead --}}
                                        <div class="space-y-2 text-center">
                                            <p class="text-xs font-medium text-emerald-500 bg-emerald-500/10 px-3 py-1.5 rounded-lg inline-flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                {{ __('Already purchased') }}
                                            </p>
                                            <a href="{{ route('billing.index') }}" class="block text-xs font-medium text-primary hover:text-primary/80">{{ __('View billing →') }}</a>
                                        </div>
                                    @elseif ($canCheckout)
                                        <form method="POST" action="{{ route('billing.checkout', [], false) }}">
                                            @csrf
                                            <input type="hidden" name="plan" value="{{ $plan['key'] }}">
                                            <input type="hidden" name="price" value="{{ $price['key'] }}">
                                            <input type="hidden" name="provider" value="{{ $provider }}">
                                            
                                            @if ($couponEnabled)
                                                <div class="relative mb-3 group/coupon">
                                                    <input
                                                        type="text"
                                                        name="coupon"
                                                        value="{{ old('coupon') }}"
                                                        class="w-full rounded-lg border border-ink/10 bg-surface/50 px-3 py-1.5 text-xs text-ink focus:border-primary focus:ring-1 focus:ring-primary transition placeholder:text-ink/30"
                                                        placeholder="{{ __('Promo Code') }}"
                                                    >
                                                </div>
                                            @endif
                                            
                                            <button class="w-full rounded-xl bg-ink text-surface font-bold py-2.5 text-sm transition hover:scale-[1.02] active:scale-[0.98] hover:bg-primary hover:text-white shadow-lg shadow-black/5">
                                                {{ $isOneTime ? __('Buy Now') : __('Subscribe') }}
                                            </button>
                                        </form>
                                    @elseif (!$user)
                                        <div class="space-y-3">
                                            <a
                                                href="{{ route('checkout.start', ['provider' => $provider, 'plan' => $plan['key'], 'price' => $price['key']]) }}"
                                                class="flex items-center justify-center px-4 py-2 text-xs font-bold text-white transition rounded-xl bg-primary hover:bg-primary/90"
                                            >
                                                {{ $isOneTime ? __('Buy Now') : __('Subscribe') }}
                                            </a>
                                            <div class="text-center text-[11px] text-ink/50">
                                                <a href="{{ route('login') }}" class="underline underline-offset-2 hover:text-ink">{{ __('Log In') }}</a>
                                            </div>
                                        </div>
                                    @elseif (!$team)
                                        <a href="{{ route('teams.select') }}" class="block w-full px-4 py-2 text-xs font-bold text-center transition rounded-xl bg-secondary/10 text-secondary hover:bg-secondary/20">{{ __('Select Team') }}</a>
                                    @else
                                        <p class="text-xs italic text-center text-ink/50">{{ __('Upgrade required') }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                @if (!empty($plan['features']))
                    <div class="pt-8 mt-8 border-t border-ink/5">
                        <ul class="space-y-3">
                            @foreach ($plan['features'] as $feature)
                                <li class="flex items-start gap-3 text-sm text-ink/70">
                                    <svg class="w-5 h-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                <div class="pt-6 mt-auto">
                    <p class="text-xs font-medium text-center text-ink/30">
                        {{ !empty($plan['seat_based'])
                            ? __('Auto-updates based on active members')
                            : __('Flat-rate billing') }}
                    </p>
                </div>
            </div>
        @endforeach
    </section>

    <!-- FAQ / Custom Section -->
    <section class="py-20">
        <div class="glass-panel rounded-[40px] px-8 py-10 md:p-12 relative overflow-hidden">
            <div class="absolute inset-0 opacity-50 bg-gradient-to-r from-secondary/10 to-transparent"></div>
            <div class="relative z-10 flex flex-col items-center justify-between gap-8 md:flex-row">
                <div>
                    <h2 class="text-3xl font-bold font-display text-ink">{{ __('Need something custom?') }}</h2>
                    <p class="max-w-xl mt-3 text-lg text-ink/60">{{ __('For large teams and enterprises, we offer custom pricing, SLA guarantees, and dedicated support channels.') }}</p>
                </div>
                <div class="flex flex-wrap gap-4">
                    @if (config('saas.support.email'))
                        <a href="mailto:{{ config('saas.support.email') }}" class="btn-primary">{{ __('Contact Sales') }}</a>
                    @endif
                    @if (config('saas.support.discord'))
                        <a href="{{ config('saas.support.discord') }}" class="btn-secondary">{{ __('Join Community') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
