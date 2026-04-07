@extends('layouts.marketing')

@section('title', __('Pricing') . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('Choose a subscription or one-time plan with Stripe or Paddle.'))

@section('content')
    @php
        $user = auth()->user();
        $canCheckout = (bool) ($canCheckout ?? $user);
        $canChangeSubscription = (bool) ($canChangeSubscription ?? false);
        $catalog = strtolower((string) ($catalog ?? config('saas.billing.catalog', 'config')));
        $priceStates = is_array($priceStates ?? null) ? $priceStates : [];
        $currentSubscriptionContext = is_array($currentSubscriptionContext ?? null) ? $currentSubscriptionContext : [];
        $currentSubscriptionAmountMinor = isset($currentSubscriptionContext['amount_minor']) ? (int) $currentSubscriptionContext['amount_minor'] : null;
        $currentSubscriptionCurrency = (string) ($currentSubscriptionContext['currency'] ?? '');
        $currentSubscriptionPlanName = (string) ($currentSubscriptionContext['plan_name'] ?? __('Current plan'));
        $pendingPriceKeyLabel = (string) ($pendingPriceKey ?? '');
        $isPendingCancellation = (bool) ($isPendingCancellation ?? false);
        $pendingCancellationDate = $activeSubscription?->ends_at?->format('F j, Y');

        $allIntervals = collect($plans)
            ->pluck('prices')
            ->collapse()
            ->pluck('interval')
            ->filter()
            ->unique()
            ->values();

        $recurringIntervals = $allIntervals
            ->reject(fn ($interval) => $interval === 'once')
            ->values();
        $toggleIntervals = $recurringIntervals->isNotEmpty() ? $recurringIntervals : $allIntervals;
        $defaultInterval = (string) ($toggleIntervals->first() ?? 'month');
        $showAlwaysVisibleOneTimeNote = $recurringIntervals->isNotEmpty() && $allIntervals->contains('once');
        $intervalLabels = [
            'month' => __('Monthly'),
            'year' => __('Yearly'),
            'week' => __('Weekly'),
            'day' => __('Daily'),
            'once' => __('Lifetime'),
        ];
    @endphp

    <div x-data="{ interval: '{{ $defaultInterval }}' }">
    <section class="py-12">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 mb-4 text-xs font-semibold border rounded-full border-primary/20 bg-primary/10 text-primary backdrop-blur-md">
                    {{ __('Pricing Plans') }}
                </div>
                <h1 class="text-4xl font-bold font-display text-ink sm:text-5xl">{{ __('Plans that scale with your product') }}</h1>
                <p class="mt-4 text-lg text-ink/60">{{ __('Subscription and one-time options with unified billing data, SEO-ready content workflows, and international launch support.') }}</p>
            </div>
            
            @if ($toggleIntervals->count() > 1)
                <div class="flex flex-col items-start gap-2 sm:items-end">
                    <div class="flex items-center gap-1 rounded-full border border-ink/5 bg-surface-highlight/10 p-1 backdrop-blur-md">
                        @foreach ($toggleIntervals as $intervalOption)
                            @php
                                $intervalLabel = $intervalLabels[$intervalOption]
                                    ?? \Illuminate\Support\Str::of($intervalOption)->replace('_', ' ')->title()->value();
                            @endphp
                            <button
                                @click="interval = @js($intervalOption)"
                                class="rounded-full px-4 py-2 text-sm font-semibold transition-all duration-300"
                                :class="interval === @js($intervalOption) ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'text-ink/60 hover:bg-surface/50 hover:text-ink'"
                            >
                                {{ $intervalLabel }}
                                @if ($intervalOption === 'year' && $toggleIntervals->contains('month'))
                                    <span class="ml-1 rounded bg-white/20 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white">{{ __('Save') }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    @if ($showAlwaysVisibleOneTimeNote)
                        <p class="text-xs font-medium text-ink/55">
                            {{ __('One-time and supporter offers stay visible below.') }}
                        </p>
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

        @if ($canChangeSubscription && !empty($hasPendingPlanChange))
            <div class="px-4 py-3 mt-6 text-sm border rounded-2xl border-amber-400/30 bg-amber-500/10 text-amber-300 backdrop-blur">
                {{ __('A plan change is already pending provider confirmation.') }}
                @if (!empty($pendingPlanName))
                    {{ __('Pending target: :plan (:price).', ['plan' => $pendingPlanName, 'price' => $pendingPriceKeyLabel !== '' ? $pendingPriceKeyLabel : __('unknown interval')]) }}
                @endif
            </div>
        @endif

        @if ($isPendingCancellation)
            <div class="mt-6 flex flex-col gap-3 rounded-2xl border border-blue-500/25 bg-blue-500/10 px-4 py-3 text-sm text-blue-300 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                <p>
                    {{ __('Your subscription is scheduled to cancel on :date. Resume it before switching plans.', ['date' => $pendingCancellationDate ?: __('the current period end')]) }}
                </p>
                <form method="POST" action="{{ route('billing.resume') }}" data-submit-lock>
                    @csrf
                    <button type="submit" class="btn-secondary !py-2 !text-sm">
                        {{ __('Resume to change plan') }}
                    </button>
                </form>
            </div>
        @endif
    </section>

    <section class="grid gap-12 mt-16 lg:grid-cols-3">
        @foreach ($plans as $plan)
            @php
                $isHighlighted = !empty($plan->highlight);
                $isOneTime = $plan->isOneTime();
                $planTypeLabel = $isOneTime ? __('One-time') : __('Subscription');
                $isComingSoon = (bool) ($plan->entitlements['coming_soon'] ?? false);
                
                // Calculate available intervals for this plan
                $planIntervals = collect($plan->prices)->pluck('interval')->filter()->unique()->values()->all();
                $showPlanExpression = $isOneTime
                    ? 'true'
                    : \Illuminate\Support\Js::from($planIntervals).'.includes(interval)';
            @endphp
            
            <!-- Plan Card -->
            <div 
                x-show="{!! $showPlanExpression !!}"
                class="glass-panel rounded-[32px] p-8 flex flex-col relative group transition-all duration-300 {{ $isComingSoon ? 'opacity-75' : '' }} {{ $isHighlighted && !$isComingSoon ? 'border-primary shadow-[0_0_50px_-12px_rgba(var(--primary-500-rgb),0.3)] ring-1 ring-primary/50 md:scale-110 z-10 bg-primary/5' : 'hover:border-primary/30 hover:shadow-lg' }}"
            >
                @if ($isComingSoon)
                    <div class="absolute -translate-x-1/2 -top-4 left-1/2">
                        <span class="px-4 py-1 text-xs font-bold text-white rounded-full shadow-lg bg-gradient-to-r from-amber-500 to-orange-500">{{ __('Coming Soon') }}</span>
                    </div>
                @elseif ($isHighlighted)
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
                                $intervalLabelText = $interval
                                    ? ($intervalLabels[$interval]
                                        ?? \Illuminate\Support\Str::of($interval)->replace('_', ' ')->title()->value())
                                    : __('Interval');
                                $intervalBillingUnit = $interval
                                    ? \Illuminate\Support\Str::of($interval)->replace('_', ' ')->lower()->value()
                                    : __('interval');
                                $supportsCustomAmount = (bool) ($price->allowCustomAmount ?? false);
                                $isMetered = (bool) ($price->isMetered ?? false);
                                $usageLimitBehavior = $price->usageLimitBehavior instanceof \App\Enums\UsageLimitBehavior
                                    ? $price->usageLimitBehavior
                                    : \App\Enums\UsageLimitBehavior::tryFrom((string) ($price->usageLimitBehavior ?? '')) ?? \App\Enums\UsageLimitBehavior::BillOverage;
                                $usageBlocksAtLimit = $usageLimitBehavior->blocksUsage();
                                $usageMeterName = (string) ($price->usageMeterName ?? __('Usage'));
                                $usageUnitLabel = (string) ($price->usageUnitLabel ?? 'unit');
                                $usageIncludedUnits = is_numeric($price->usageIncludedUnits ?? null)
                                    ? (int) $price->usageIncludedUnits
                                    : null;
                                $usagePackageSize = max((int) ($price->usagePackageSize ?? 1), 1);
                                $usageOverageAmountMinor = is_numeric($price->usageOverageAmount ?? null)
                                    ? (int) $price->usageOverageAmount
                                    : null;
                                $defaultCustomAmountMinor = is_numeric($price->customAmountDefault ?? null)
                                    ? (int) $price->customAmountDefault
                                    : null;
                                $minimumCustomAmountMinor = is_numeric($price->customAmountMinimum ?? null)
                                    ? (int) $price->customAmountMinimum
                                    : null;
                                $maximumCustomAmountMinor = is_numeric($price->customAmountMaximum ?? null)
                                    ? (int) $price->customAmountMaximum
                                    : null;
                                $priceVisibilityExpression = $interval === 'once'
                                    ? 'true'
                                    : 'interval === '.\Illuminate\Support\Js::from($interval);
                                $amountDisplay = $supportsCustomAmount ? __('Pay what you want') : __('Custom');
                                $amountMeta = null;
                                $amountCaption = null;
                                $amountSummaryText = null;
                                $targetAmountMinor = null;
                                if ($supportsCustomAmount) {
                                    $targetAmountMinor = $defaultCustomAmountMinor
                                        ?? (is_numeric($amount) ? (int) round((float) $amount) : null);
                                    $startingAmountMinor = $minimumCustomAmountMinor ?? $targetAmountMinor;
                                    $startingAmountLabel = !is_null($startingAmountMinor)
                                        ? \App\Support\Money\CurrencyAmount::formatMinor($startingAmountMinor, $currency, true, true)
                                        : null;
                                    $amountSummaryText = $startingAmountLabel
                                        ? __('Pay what you want from :amount', ['amount' => $startingAmountLabel])
                                        : __('Pay what you want');

                                    if ($minimumCustomAmountMinor !== null && $maximumCustomAmountMinor !== null) {
                                        $amountMeta = __('Choose any amount between :min and :max.', [
                                            'min' => \App\Support\Money\CurrencyAmount::formatMinor($minimumCustomAmountMinor, $currency, true, true),
                                            'max' => \App\Support\Money\CurrencyAmount::formatMinor($maximumCustomAmountMinor, $currency, true, true),
                                        ]);
                                    } elseif ($startingAmountLabel) {
                                        $amountMeta = __('Starts at :amount.', ['amount' => $startingAmountLabel]);
                                    }
                                } elseif ($isMetered && is_numeric($amount)) {
                                    $amountValue = (float) $amount;
                                    $amountDisplay = !empty($price->amountIsMinor)
                                        ? \App\Support\Money\CurrencyAmount::formatMinor($amountValue, $currency)
                                        : \App\Support\Money\CurrencyAmount::formatMajor($amountValue, $currency);
                                    $targetAmountMinor = !empty($price->amountIsMinor)
                                        ? (int) round($amountValue)
                                        : \App\Support\Money\CurrencyAmount::parseMajorToMinor($amountValue, $currency);

                                    $baseAmountLabel = !empty($price->amountIsMinor)
                                        ? \App\Support\Money\CurrencyAmount::formatMinor($amountValue, $currency, true, true)
                                        : \App\Support\Money\CurrencyAmount::formatMajor($amountValue, $currency, true, true);
                                    $overageLabel = $usageOverageAmountMinor !== null
                                        ? \App\Support\Money\CurrencyAmount::formatMinor($usageOverageAmountMinor, $currency, true, true)
                                        : null;
                                    $packageUnitsLabel = number_format($usagePackageSize).' '.\Illuminate\Support\Str::plural($usageUnitLabel, $usagePackageSize);
                                    $amountCaption = __('Base / :interval', [
                                        'interval' => $intervalBillingUnit,
                                    ]);
                                    $amountSummaryText = $usageBlocksAtLimit
                                        ? __(':amount / :interval included usage', [
                                            'amount' => $baseAmountLabel,
                                            'interval' => $intervalBillingUnit,
                                        ])
                                        : __(':amount / :interval + usage', [
                                            'amount' => $baseAmountLabel,
                                            'interval' => $intervalBillingUnit,
                                        ]);

                                    if ($usageBlocksAtLimit && $usageIncludedUnits !== null && $usageIncludedUnits > 0) {
                                        $amountMeta = __('Includes :included :units per :interval. New usage is blocked until renewal or upgrade.', [
                                            'included' => number_format($usageIncludedUnits),
                                            'units' => \Illuminate\Support\Str::plural($usageUnitLabel, $usageIncludedUnits),
                                            'interval' => $intervalBillingUnit,
                                        ]);
                                    } elseif ($usageIncludedUnits !== null && $usageIncludedUnits > 0 && $overageLabel) {
                                        $amountMeta = __('Includes :included :units per :interval, then :overage per :package.', [
                                            'included' => number_format($usageIncludedUnits),
                                            'units' => \Illuminate\Support\Str::plural($usageUnitLabel, $usageIncludedUnits),
                                            'interval' => $intervalBillingUnit,
                                            'overage' => $overageLabel,
                                            'package' => $packageUnitsLabel,
                                        ]);
                                    } elseif ($overageLabel) {
                                        $amountMeta = __('No included usage. :meter is billed at :overage per :package.', [
                                            'meter' => $usageMeterName,
                                            'overage' => $overageLabel,
                                            'package' => $packageUnitsLabel,
                                        ]);
                                    }
                                } elseif (is_numeric($amount)) {
                                    $amountValue = (float) $amount;
                                    $amountDisplay = !empty($price->amountIsMinor)
                                        ? \App\Support\Money\CurrencyAmount::formatMinor($amountValue, $currency)
                                        : \App\Support\Money\CurrencyAmount::formatMajor($amountValue, $currency);
                                    $targetAmountMinor = !empty($price->amountIsMinor)
                                        ? (int) round($amountValue)
                                        : \App\Support\Money\CurrencyAmount::parseMajorToMinor($amountValue, $currency);
                                    $amountSummaryText = trim($currency.' '.$amountDisplay);
                                }
                            @endphp
                            
                            <!-- Price Option -->
                            <div x-show="{!! $priceVisibilityExpression !!}" class="p-5 transition card-inner hover:border-primary/30">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <p class="text-sm font-semibold text-ink">{{ $label }}</p>
                                        @if ($interval)
                                            <p class="text-xs capitalize text-ink/50">{{ $interval }}</p>
                                        @endif
                                        @if ($isMetered)
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <p class="inline-flex rounded-full border border-primary/15 bg-primary/5 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-primary">
                                                    {{ __('Usage-based') }}
                                                </p>
                                                <p class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide {{ $usageBlocksAtLimit ? 'border-amber-500/20 bg-amber-500/10 text-amber-600' : 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600' }}">
                                                    {{ $usageBlocksAtLimit ? __('Blocks at limit') : __('Bills overages') }}
                                                </p>
                                            </div>
                                        @endif
                                        @if ($amountMeta)
                                            <p class="mt-2 max-w-xs text-xs leading-5 text-ink/55">{{ $amountMeta }}</p>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <span class="{{ $supportsCustomAmount ? 'text-xl sm:text-2xl' : 'text-3xl' }} font-bold font-display text-ink">{{ $amountDisplay }}</span>
                                        @if ($amountCaption)
                                            <p class="text-xs font-medium text-ink/40">{{ $amountCaption }}</p>
                                        @elseif (!$supportsCustomAmount)
                                            <span class="text-xs font-medium text-ink/40">{{ $currency }}</span>
                                        @endif
                                    </div>
                                </div>

                                @if (!$isComingSoon)
                                <div>
                                    @php
                                        $priceState = data_get($priceStates, "{$plan->key}.{$price->key}", []);
                                        $priceIdForActiveProvider = $priceState['price_id_for_active_provider'] ?? null;
                                        $isCurrentSubscriptionPrice = (bool) ($priceState['is_current_subscription_price'] ?? false);
                                        $checkoutEligibility = $priceState['checkout_eligibility'] ?? null;
                                        $planChangePending = (bool) ($priceState['plan_change_pending'] ?? false);
                                        $formId = 'change-plan-'.$plan->key.'-'.$price->key;
                                        $planDirection = 'switch';
                                        if (!is_null($currentSubscriptionAmountMinor)
                                            && !is_null($targetAmountMinor)
                                            && $currentSubscriptionAmountMinor > 0
                                            && $targetAmountMinor > 0
                                            && $currentSubscriptionCurrency === $currency
                                        ) {
                                            if ($targetAmountMinor > $currentSubscriptionAmountMinor) {
                                                $planDirection = 'upgrade';
                                            } elseif ($targetAmountMinor < $currentSubscriptionAmountMinor) {
                                                $planDirection = 'downgrade';
                                            }
                                        }

                                        $intervalLabel = $interval
                                            ? ($intervalLabels[$interval]
                                                ?? \Illuminate\Support\Str::of($interval)->replace('_', ' ')->title()->value())
                                            : __('Interval');
                                    @endphp

                                    @if ($canChangeSubscription && !$isOneTime)
                                        @if ($isCurrentSubscriptionPrice)
                                            <p class="inline-block px-3 py-1.5 text-xs font-medium rounded-lg text-emerald-500 bg-emerald-500/10">
                                                {{ __('Current plan') }}
                                            </p>
                                        @elseif ($planChangePending)
                                            <p class="inline-block px-2 py-1 text-xs font-medium rounded-md text-amber-500 bg-amber-500/10">
                                                {{ __('Plan change pending') }}
                                            </p>
                                        @elseif (empty($priceIdForActiveProvider))
                                            <p class="inline-block px-2 py-1 text-xs font-medium rounded-md text-amber-500 bg-amber-500/10">
                                                {{ __('Not available for your billing provider') }}
                                            </p>
                                        @else
                                            <form id="{{ $formId }}" method="POST" action="{{ route('billing.change-plan') }}" data-submit-lock>
                                                @csrf
                                                <input type="hidden" name="plan" value="{{ $plan->key }}">
                                                <input type="hidden" name="price" value="{{ $price->key }}">
                                                <button
                                                    type="button"
                                                    onclick="openPlanChangeConfirmModal({
                                                        formId: @js($formId),
                                                        planName: @js((string) ($plan->name ?: ucfirst($plan->key))),
                                                        priceLabel: @js($label),
                                                        intervalLabel: @js($intervalLabel),
                                                        amountText: @js($amountSummaryText ?? trim($amountDisplay.' '.$currency)),
                                                        currentPlanName: @js($currentSubscriptionPlanName),
                                                        currentAmountText: @js(!is_null($currentSubscriptionAmountMinor) && $currentSubscriptionCurrency !== '' ? \App\Support\Money\CurrencyAmount::formatMinor($currentSubscriptionAmountMinor, $currentSubscriptionCurrency, true, true) : ''),
                                                        direction: @js($planDirection)
                                                    });"
                                                    class="w-full rounded-xl bg-ink text-surface font-bold py-2.5 text-sm transition hover:scale-[1.02] active:scale-[0.98] hover:bg-primary hover:text-white shadow-lg shadow-black/5"
                                                >
                                                    {{ __('Switch plan') }}
                                                </button>
                                            </form>
                                        @endif
                                    @elseif ($isPendingCancellation && !$isOneTime)
                                        <p class="inline-block px-2 py-1 text-xs font-medium rounded-md text-blue-300 bg-blue-500/10">
                                            {{ __('Scheduled to cancel') }}
                                        </p>
                                    @elseif (empty($price->providerIds) && !$supportsCustomAmount)
                                        @if ($catalog === 'database')
                                            <p class="inline-block px-2 py-1 text-xs font-medium rounded-md text-amber-500 bg-amber-500/10">{{ __('Missing Price ID') }}</p>
                                        @else
                                            <p class="inline-block px-2 py-1 text-xs font-medium rounded-md text-amber-500 bg-amber-500/10">{{ __('Missing Env ID') }}</p>
                                        @endif
                                    @elseif ($canCheckout)
                                        @if ($checkoutEligibility?->allowed)
                                            @php
                                                $checkoutCta = $isOneTime
                                                    ? ($supportsCustomAmount ? __('Contribute') : __('Buy Now'))
                                                    : ($isMetered ? __('Start Metered Plan') : __('Subscribe'));
                                                if ($checkoutEligibility->isUpgrade && $isOneTime) {
                                                    $checkoutCta = __('Upgrade One-time');
                                                } elseif ($checkoutEligibility->isUpgrade && ! $isOneTime) {
                                                    $checkoutCta = $isMetered ? __('Switch to Metered Plan') : __('Switch to Subscription');
                                                }
                                            @endphp

                                            <form method="GET" action="{{ route('checkout.start') }}">
                                                <input type="hidden" name="plan" value="{{ $plan->key }}">
                                                <input type="hidden" name="price" value="{{ $price->key }}">
                                                
                                                <button class="w-full rounded-xl bg-ink text-surface font-bold py-2.5 text-sm transition hover:scale-[1.02] active:scale-[0.98] hover:bg-primary hover:text-white shadow-lg shadow-black/5">
                                                    {{ $checkoutCta }}
                                                </button>
                                            </form>
                                        @else
                                            <div class="space-y-2 text-center">
                                                <p class="text-xs font-medium text-ink/60 bg-surface/40 px-3 py-1.5 rounded-lg">
                                                    {{ $checkoutEligibility?->message ?? __('Checkout unavailable for this option.') }}
                                                </p>
                                                @php
                                                    $isOneTimeDowngradeBlocked = ($checkoutEligibility?->errorCode ?? null) === 'BILLING_ONE_TIME_DOWNGRADE_UNSUPPORTED';
                                                    $supportEmail = (string) config('saas.support.email', '');
                                                    $supportDiscord = (string) config('saas.support.discord', '');
                                                @endphp
                                                @if ($isOneTimeDowngradeBlocked && $supportEmail !== '')
                                                    <a href="mailto:{{ $supportEmail }}" class="block text-xs font-medium text-primary hover:text-primary/80">{{ __('Contact support ->') }}</a>
                                                @elseif ($isOneTimeDowngradeBlocked && $supportDiscord !== '')
                                                    <a href="{{ $supportDiscord }}" target="_blank" rel="noopener noreferrer" class="block text-xs font-medium text-primary hover:text-primary/80">{{ __('Contact support ->') }}</a>
                                                @else
                                                    <a href="{{ route('billing.index') }}" class="block text-xs font-medium text-primary hover:text-primary/80">{{ __('View billing ->') }}</a>
                                                @endif
                                            </div>
                                        @endif
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
                                    @endif
                                </div>
                                @endif
                            </div>
                        @endforeach
                    @endif

                    @if ($isComingSoon)
                        <div class="px-6 py-5 text-center border border-dashed rounded-2xl border-amber-400/30 bg-amber-500/5">
                            <p class="text-sm font-semibold text-amber-500">{{ __('Coming Soon') }}</p>
                            <p class="mt-1 text-xs text-ink/50">{{ __('This plan is not yet available for purchase.') }}</p>
                        </div>
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

    <div
        id="plan-change-confirm-modal"
        class="hidden fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="plan-change-confirm-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex min-h-screen items-center justify-center px-4 py-12">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closePlanChangeConfirmModal()"></div>

            <div class="relative glass-panel rounded-[32px] p-8 max-w-lg w-full">
                <h3 id="plan-change-confirm-title" class="text-xl font-display font-bold text-ink">
                    {{ __('Confirm Plan Change') }}
                </h3>
                <p class="mt-2 text-sm text-ink/60">
                    {{ __('Please review this change before we ask your payment provider to apply it.') }}
                </p>

                <div class="mt-6 rounded-2xl border border-ink/10 bg-surface/40 p-4 space-y-2">
                    <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide">
                        <span
                            id="plan-change-confirm-badge"
                            class="inline-flex items-center rounded-full border px-2 py-1"
                        ></span>
                        <span id="plan-change-confirm-interval" class="text-ink/50"></span>
                    </div>
                    <p id="plan-change-confirm-plan" class="text-lg font-bold text-ink"></p>
                    <p id="plan-change-confirm-target" class="text-sm text-ink/60"></p>
                </div>

                <div class="mt-4 rounded-2xl border border-ink/10 bg-surface/30 px-4 py-3 text-sm text-ink/60">
                    <p>
                        {{ __('Current') }}:
                        <span id="plan-change-confirm-current-plan" class="font-semibold text-ink"></span>
                        <span id="plan-change-confirm-current-amount-wrap" class="hidden">
                            <span class="mx-1">&middot;</span>
                            <span id="plan-change-confirm-current-amount" class="font-semibold text-ink"></span>
                        </span>
                    </p>
                </div>

                <p id="plan-change-confirm-impact" class="mt-4 text-sm text-ink/70"></p>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <button
                        id="plan-change-confirm-submit"
                        type="button"
                        class="btn-primary"
                    >
                        <span id="plan-change-confirm-submit-label">{{ __('Confirm Plan Change') }}</span>
                        <span id="plan-change-confirm-submit-loading" class="hidden">{{ __('Submitting...') }}</span>
                    </button>
                    <button type="button" onclick="closePlanChangeConfirmModal()" class="btn-secondary">
                        {{ __('Keep Current Plan') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('plan-change-confirm-modal');
            const submitButton = document.getElementById('plan-change-confirm-submit');

            if (!modal || !submitButton) {
                return;
            }

            const badge = document.getElementById('plan-change-confirm-badge');
            const interval = document.getElementById('plan-change-confirm-interval');
            const planName = document.getElementById('plan-change-confirm-plan');
            const target = document.getElementById('plan-change-confirm-target');
            const currentPlan = document.getElementById('plan-change-confirm-current-plan');
            const currentAmountWrap = document.getElementById('plan-change-confirm-current-amount-wrap');
            const currentAmount = document.getElementById('plan-change-confirm-current-amount');
            const impact = document.getElementById('plan-change-confirm-impact');
            const submitLabel = document.getElementById('plan-change-confirm-submit-label');
            const submitLoading = document.getElementById('plan-change-confirm-submit-loading');

            const currentPlanFallback = @js($currentSubscriptionPlanName);
            const directionLabels = {
                upgrade: @js(__('Upgrade')),
                downgrade: @js(__('Downgrade')),
                switch: @js(__('Switch')),
            };
            const submitLabels = {
                upgrade: @js(__('Confirm Upgrade')),
                downgrade: @js(__('Confirm Downgrade')),
                switch: @js(__('Confirm Plan Change')),
            };
            const impactLabels = {
                upgrade: @js(__('Your provider will apply prorations automatically. Upgrades can trigger an immediate or next-invoice charge.')),
                downgrade: @js(__('Your provider will apply prorations automatically. Downgrades usually create invoice credit, not an automatic card refund.')),
                switch: @js(__('Your provider will apply prorations automatically based on your existing billing period.')),
            };
            const badgeStyles = {
                upgrade: ['border-emerald-500/30', 'bg-emerald-500/10', 'text-emerald-400'],
                downgrade: ['border-amber-500/30', 'bg-amber-500/10', 'text-amber-400'],
                switch: ['border-primary/30', 'bg-primary/10', 'text-primary'],
            };

            let targetFormId = null;

            const setSubmitting = (isSubmitting) => {
                submitButton.disabled = isSubmitting;
                submitButton.setAttribute('aria-disabled', isSubmitting ? 'true' : 'false');
                submitLabel.classList.toggle('hidden', isSubmitting);
                submitLoading.classList.toggle('hidden', !isSubmitting);
            };

            const setDirection = (direction) => {
                const normalizedDirection = (direction === 'upgrade' || direction === 'downgrade') ? direction : 'switch';
                const allStyleClasses = [
                    ...badgeStyles.upgrade,
                    ...badgeStyles.downgrade,
                    ...badgeStyles.switch,
                ];

                badge.classList.remove(...allStyleClasses);
                badge.classList.add(...badgeStyles[normalizedDirection]);
                badge.textContent = directionLabels[normalizedDirection];

                submitLabel.textContent = submitLabels[normalizedDirection];
                impact.textContent = impactLabels[normalizedDirection];
            };

            window.openPlanChangeConfirmModal = (payload = {}) => {
                targetFormId = payload.formId ?? null;
                setDirection(payload.direction ?? 'switch');

                interval.textContent = payload.intervalLabel ?? '';
                planName.textContent = payload.planName ?? '';

                const targetParts = [payload.priceLabel, payload.amountText]
                    .filter((value) => typeof value === 'string' && value.trim() !== '');
                target.textContent = targetParts.length > 0 ? targetParts.join(' - ') : '-';

                const resolvedCurrentPlan = (typeof payload.currentPlanName === 'string' && payload.currentPlanName.trim() !== '')
                    ? payload.currentPlanName
                    : currentPlanFallback;
                currentPlan.textContent = resolvedCurrentPlan ?? '';

                const currentAmountText = (typeof payload.currentAmountText === 'string')
                    ? payload.currentAmountText.trim()
                    : '';
                if (currentAmountText !== '') {
                    currentAmount.textContent = currentAmountText;
                    currentAmountWrap.classList.remove('hidden');
                } else {
                    currentAmount.textContent = '';
                    currentAmountWrap.classList.add('hidden');
                }

                setSubmitting(false);
                modal.classList.remove('hidden');
            };

            window.closePlanChangeConfirmModal = () => {
                modal.classList.add('hidden');
                setSubmitting(false);
                targetFormId = null;
            };

            submitButton.addEventListener('click', () => {
                if (!targetFormId || submitButton.disabled) {
                    return;
                }

                const form = document.getElementById(targetFormId);
                if (!form) {
                    return;
                }

                setSubmitting(true);

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                    return;
                }

                form.submit();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    window.closePlanChangeConfirmModal();
                }
            });
        })();
    </script>
    </div> <!-- End x-data -->
@endsection
