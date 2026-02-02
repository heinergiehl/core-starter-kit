@extends('layouts.marketing')

@section('title', __('Pricing') . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('Choose a subscription or one-time plan with Stripe, Paddle, or Lemon Squeezy.'))

@section('content')
    @php
        $user = auth()->user();
        $canCheckout = (bool) $user;
        
        // Check if user already has any purchase (subscription OR one-time)
        $hasPurchased = $canCheckout && app(\App\Domain\Billing\Services\CheckoutService::class)->hasAnyPurchase($user);
        
        $catalog = strtolower((string) config('saas.billing.catalog', 'config'));

        // Determine available intervals for toggle
        // $plans is a Collection<Plan>
        $allIntervals = collect($plans)->pluck('prices')->collapse()->pluck('interval')->unique()->values();
        $hasMonthly = $allIntervals->contains('month');
        $hasYearly = $allIntervals->contains('year');
        $hasOneTime = $allIntervals->contains('once');
        $defaultInterval = $hasMonthly ? 'month' : ($hasYearly ? 'year' : 'once');
    @endphp

    <div x-data="{ interval: '{{ $defaultInterval }}' }">
    <section class="py-12">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 mb-4 text-xs font-semibold border rounded-full border-primary/20 bg-primary/10 text-primary backdrop-blur-md">
                    {{ __('Pricing Plans') }}
                </div>
                <h1 class="text-4xl font-bold font-display text-ink sm:text-5xl">{{ __('Plans that scale with your product') }}</h1>
                <p class="mt-4 text-lg text-ink/60">{{ __('Subscription and one-time options with unified billing data.') }}</p>
            </div>
            
            @if (($hasMonthly || $hasYearly) && $hasOneTime || ($hasMonthly && $hasYearly))
                <!-- Interval Toggle -->
                <div class="flex items-center gap-1 p-1 rounded-full bg-surface-highlight/10 border border-ink/5 backdrop-blur-md">
                    @if ($hasMonthly)
                    <button 
                        @click="interval = 'month'"
                        class="px-4 py-2 text-sm font-semibold rounded-full transition-all duration-300"
                        :class="interval === 'month' ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'text-ink/60 hover:text-ink hover:bg-surface/50'"
                    >
                        {{ __('Monthly') }}
                    </button>
                    @endif
                    @if ($hasYearly)
                    <button 
                        @click="interval = 'year'"
                        class="px-4 py-2 text-sm font-semibold rounded-full transition-all duration-300"
                        :class="interval === 'year' ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'text-ink/60 hover:text-ink hover:bg-surface/50'"
                    >
                        {{ __('Yearly') }}
                        <span class="ml-1 text-[10px] font-bold uppercase tracking-wider bg-white/20 px-1.5 py-0.5 rounded text-white">{{ __('Save') }}</span>
                    </button>
                    @endif
                    @if ($hasOneTime)
                    <button 
                        @click="interval = 'once'"
                        class="px-4 py-2 text-sm font-semibold rounded-full transition-all duration-300"
                        :class="interval === 'once' ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'text-ink/60 hover:text-ink hover:bg-surface/50'"
                    >
                        {{ __('Lifetime') }}
                    </button>
                    @endif
                </div>
            @endif
        </div>

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

    <section class="grid gap-12 mt-16 lg:grid-cols-3">
        @foreach ($plans as $plan)
            @php
                $isHighlighted = !empty($plan->highlight);
                $isOneTime = $plan->isOneTime();
                $planTypeLabel = $isOneTime ? __('One-time') : __('Subscription');
                
                // Calculate available intervals for this plan
                $planIntervals = collect($plan->prices)->pluck('interval')->unique()->values();
                $jsIntervals = $planIntervals->map(fn($i) => "'$i'")->implode(',');
            @endphp
            
            <!-- Plan Card -->
            <div 
                x-show="[{{ $jsIntervals }}].includes(interval)"
                class="glass-panel rounded-[32px] p-8 flex flex-col relative group transition-all duration-300 {{ $isHighlighted ? 'border-primary shadow-[0_0_50px_-12px_rgba(var(--primary-500-rgb),0.3)] ring-1 ring-primary/50 md:scale-110 z-10 bg-primary/5' : 'hover:border-primary/30 hover:shadow-lg' }}"
            >
                @if ($isHighlighted)
                    <div class="absolute -translate-x-1/2 -top-4 left-1/2">
                        <span class="px-4 py-1 text-xs font-bold text-white rounded-full shadow-lg bg-gradient-to-r from-primary to-secondary">{{ __('Most Popular') }}</span>
                    </div>
                @endif

                <div class="mb-6">
                    <p class="mb-2 text-xs font-bold tracking-widest uppercase text-primary">{{ $planTypeLabel }}</p>
                    <h2 class="text-3xl font-bold font-display text-ink">{{ $plan->name }}</h2>
                    @if (!empty($plan->summary))
                        <p class="mt-2 text-sm text-ink/60">{{ $plan->summary }}</p>
                    @endif
                </div>

                <div class="flex-grow space-y-4">
                    @if (empty($plan->prices))
                        <div class="px-6 py-6 text-sm text-center border border-dashed rounded-2xl border-ink/20 bg-surface/30 text-ink/60">
                            {{ __('Add prices in Admin > Prices to show checkout options for this plan.') }}
                        </div>
                    @else
                        @foreach ($plan->prices as $price)
                            @php
                                $amount = $price->amount ?? null;
                                $currency = strtoupper((string) ($price->currency ?? 'USD'));
                                $label = $price->label ?: ucfirst($price->key);
                                $interval = $price->interval ?? null;
                                $amountDisplay = __('Custom');
                                if (is_numeric($amount)) {
                                    $amountValue = (float) $amount;
                                    $amountDisplay = '$' . number_format(
                                        !empty($price->amountIsMinor) ? ($amountValue / 100) : $amountValue,
                                        !empty($price->amountIsMinor) ? 2 : 0
                                    );
                                }
                            @endphp
                            
                            <!-- Price Option -->
                            <div x-show="interval === '{{ $interval }}'" class="p-5 transition card-inner hover:border-primary/30">
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
                                    @if (empty($price->providerIds))
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
                                        <form method="GET" action="{{ route('checkout.start') }}">
                                            <input type="hidden" name="plan" value="{{ $plan->key }}">
                                            <input type="hidden" name="price" value="{{ $price->key }}">
                                            
                                            <button class="w-full rounded-xl bg-ink text-surface font-bold py-2.5 text-sm transition hover:scale-[1.02] active:scale-[0.98] hover:bg-primary hover:text-white shadow-lg shadow-black/5">
                                                {{ $isOneTime ? __('Buy Now') : __('Subscribe') }}
                                            </button>
                                        </form>
                                    @elseif (!$user)
                                        <div class="space-y-3">
                                            <a
                                                href="{{ route('checkout.start', ['plan' => $plan->key, 'price' => $price->key]) }}"
                                                class="flex items-center justify-center px-4 py-2 text-xs font-bold text-white transition rounded-xl bg-primary hover:bg-primary/90"
                                            >
                                                {{ $isOneTime ? __('Buy Now') : __('Subscribe') }}
                                            </a>
                                            <div class="text-center text-[11px] text-ink/50">
                                                <a href="{{ route('login') }}" class="underline underline-offset-2 hover:text-ink">{{ __('Log In') }}</a>
                                            </div>
                                        </div>
                                    @else
                                        <p class="text-xs italic text-center text-ink/50">{{ __('Upgrade required') }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                @if (!empty($plan->features))
                    <div class="pt-8 mt-8 border-t border-ink/5">
                        <ul class="space-y-3">
                            @foreach ($plan->features as $feature)
                                <li class="flex items-start gap-3 text-sm text-ink/70">
                                    <svg class="w-5 h-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
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
                    <p class="max-w-xl mt-3 text-lg text-ink/60">{{ __('For higher-volume customers, we offer custom pricing, SLA guarantees, and dedicated support channels.') }}</p>
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
    </div> <!-- End x-data -->
@endsection
