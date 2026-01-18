<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-medium text-2xl text-ink leading-tight">
            {{ __('Billing & Subscription') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-600">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-600">
                    {{ session('error') }}
                </div>
            @endif

            @if (session('info'))
                <div class="mb-6 rounded-2xl border border-blue-500/20 bg-blue-500/10 px-4 py-3 text-sm text-blue-600">
                    {{ session('info') }}
                </div>
            @endif

            @if ($subscription)
                @php
                    $statusColors = [
                        'active' => 'text-emerald-600 bg-emerald-500/10 border-emerald-500/20',
                        'trialing' => 'text-blue-600 bg-blue-500/10 border-blue-500/20',
                        'past_due' => 'text-amber-600 bg-amber-500/10 border-amber-500/20',
                        'canceled' => 'text-rose-600 bg-rose-500/10 border-rose-500/20',
                        'paused' => 'text-gray-600 bg-gray-500/10 border-gray-500/20',
                    ];
                    $statusColor = $statusColors[$subscription->status] ?? 'text-ink/60 bg-surface/10 border-ink/10';
                    // Pending cancellation = has canceled_at but still active status and ends_at in future
                    $isPendingCancellation = $subscription->canceled_at && $subscription->ends_at && $subscription->ends_at->isFuture();
                    $supportsLocalCancel = in_array($subscription->provider, ['stripe', 'lemonsqueezy', 'paddle'], true);
                    $canResume = $supportsLocalCancel && $isPendingCancellation;
                    $canCancel = $supportsLocalCancel && !$isPendingCancellation && in_array($subscription->status, ['active', 'trialing'], true);
                @endphp

                <!-- Current Plan Card -->
                <div class="glass-panel rounded-[32px] p-8 mb-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-ink/40">{{ __('Current Plan') }}</p>
                            <h2 class="mt-2 text-2xl font-display font-bold text-ink">
                                {{ $plan['name'] ?? ucfirst($subscription->plan_key) }}
                            </h2>
                            <div class="mt-3 inline-flex items-center gap-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border {{ $statusColor }}">
                                    {{ ucfirst($subscription->status) }}
                                </span>
                                @if ($subscription->quantity > 1)
                                    <span class="text-sm text-ink/50">{{ $subscription->quantity }} {{ __('seats') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-medium text-ink/40 uppercase tracking-wide">{{ __('Provider') }}</p>
                            <p class="mt-1 text-sm font-semibold text-ink">{{ ucfirst($subscription->provider) }}</p>
                        </div>
                    </div>

                    <!-- Billing Details -->
                    <div class="mt-8 grid gap-4 sm:grid-cols-3">
                        @if ($subscription->renews_at)
                            <div class="rounded-2xl border border-ink/5 bg-surface/30 p-4">
                                <p class="text-xs font-medium text-ink/40 uppercase">
                                    {{ $isPendingCancellation ? __('Access Until') : __('Next Billing') }}
                                </p>
                                <p class="mt-1 text-lg font-semibold text-ink">
                                    {{ ($subscription->ends_at ?? $subscription->renews_at)->format('M j, Y') }}
                                </p>
                            </div>
                        @endif

                        @if ($subscription->trial_ends_at && $subscription->trial_ends_at->isFuture())
                            <div class="rounded-2xl border border-ink/5 bg-surface/30 p-4">
                                <p class="text-xs font-medium text-ink/40 uppercase">{{ __('Trial Ends') }}</p>
                                <p class="mt-1 text-lg font-semibold text-ink">
                                    {{ $subscription->trial_ends_at->format('M j, Y') }}
                                </p>
                            </div>
                        @endif

                        <div class="rounded-2xl border border-ink/5 bg-surface/30 p-4">
                            <p class="text-xs font-medium text-ink/40 uppercase">{{ __('Member Since') }}</p>
                            <p class="mt-1 text-lg font-semibold text-ink">
                                {{ $subscription->created_at->format('M j, Y') }}
                            </p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-8 flex flex-wrap items-center gap-4 pt-6 border-t border-ink/5">
                        <a href="{{ route('billing.portal') }}" class="btn-primary">
                            {{ __('Manage Payment Method') }}
                        </a>

                        @if (!$isPendingCancellation && in_array($subscription->status, ['active', 'trialing'], true))
                            <a href="{{ route('pricing') }}?current_plan={{ $subscription->plan_key }}" class="btn-secondary">
                                {{ __('Change Plan') }}
                            </a>
                        @endif

                        @if ($canResume)
                            <form method="POST" action="{{ route('billing.resume') }}" class="inline">
                                @csrf
                                <button type="submit" class="btn-secondary">
                                    {{ __('Resume Subscription') }}
                                </button>
                            </form>
                        @endif

                        @if ($canCancel && !$isPendingCancellation)
                            <button 
                                type="button" 
                                onclick="document.getElementById('cancel-modal').classList.remove('hidden')"
                                class="text-sm font-medium text-rose-500 hover:text-rose-600 transition-colors"
                            >
                                {{ __('Cancel Subscription') }}
                            </button>
                        @endif
                    </div>

                    @if ($isPendingCancellation && $subscription->ends_at)
                        <div class="mt-6 rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-700">
                            {{ __('Your subscription has been canceled. You will retain access until :date.', ['date' => $subscription->ends_at->format('F j, Y')]) }}
                        </div>
                    @endif
                </div>

                <!-- Recent Invoices -->
                @if ($invoices->isNotEmpty())
                    <div class="glass-panel rounded-[32px] p-8">
                        <h3 class="text-lg font-bold text-ink mb-4">{{ __('Recent Invoices') }}</h3>
                        <div class="space-y-3">
                            @foreach ($invoices as $invoice)
                                <div class="flex items-center justify-between p-3 rounded-xl border border-ink/5 bg-surface/30 hover:bg-surface/50 transition-colors">
                                    <div class="flex items-center gap-4">
                                        <div class="h-10 w-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-medium text-ink">{{ $invoice->issued_at?->format('M j, Y') ?? __('Invoice') }}</p>
                                            <p class="text-xs text-ink/50">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount_paid / 100, 2) }}</p>
                                        </div>
                                    </div>
                                    @if ($invoice->hosted_invoice_url)
                                        <a href="{{ $invoice->hosted_invoice_url }}" target="_blank" class="text-sm font-medium text-primary hover:text-primary/80">
                                            {{ __('View') }} &rarr;
                                        </a>
                                    @endif
                                    @if ($invoice->id)
                                        <a href="{{ route('invoices.download_invoice', $invoice) }}" class="ml-4 text-sm font-medium text-ink/60 hover:text-ink">
                                            {{ __('Download PDF') }}
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            @elseif($pendingOrder)
                <!-- Provisioning State (Race Condition Handling) -->
                <div class="glass-panel rounded-[32px] p-8 text-center animate-pulse">
                    <div class="mx-auto w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center text-primary mb-4">
                        <svg class="w-8 h-8 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-display font-bold text-ink">{{ __('Setting up your subscription') }}</h2>
                    <p class="mt-2 text-ink/60 max-w-md mx-auto">{{ __('We received your payment and are activating your plan. This usually takes just a few seconds.') }}</p>
                    <div class="mt-6">
                        <span class="text-xs font-mono text-ink/40">{{ __('Order ID: :id', ['id' => $pendingOrder->id]) }}</span>
                    </div>
                </div>
                <script>
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                </script>

            @else
                <!-- No Subscription -->
                <div class="glass-panel rounded-[32px] p-8 text-center">
                    <div class="mx-auto w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center text-primary mb-4">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-display font-bold text-ink">{{ __('No Active Subscription') }}</h2>
                    <p class="mt-2 text-ink/60 max-w-md mx-auto">{{ __('You are currently on the free plan. Upgrade to unlock premium features.') }}</p>
                    <a href="{{ route('pricing') }}" class="btn-primary mt-6 inline-block">
                        {{ __('View Plans') }}
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Cancel Modal -->
    @if ($subscription && $canCancel)
    <div id="cancel-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-center justify-center px-4 py-12">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="document.getElementById('cancel-modal').classList.add('hidden')"></div>
            
            <div class="relative glass-panel rounded-[32px] p-8 max-w-md w-full">
                <h3 class="text-xl font-display font-bold text-ink" id="modal-title">{{ __('Cancel Subscription') }}</h3>
                <p class="mt-3 text-ink/60">
                    {{ __('Are you sure you want to cancel? You will retain access until the end of your current billing period.') }}
                </p>

                <form method="POST" action="{{ route('billing.cancel') }}" class="mt-6">
                    @csrf
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="confirm" value="1" required class="mt-0.5 rounded border-ink/20 text-primary focus:ring-primary">
                        <span class="text-sm text-ink/70">{{ __('I understand that my subscription will be canceled and I will lose access to premium features at the end of my billing period.') }}</span>
                    </label>

                    <div class="mt-6 flex items-center gap-4">
                        <button type="submit" class="btn-primary !bg-rose-500 hover:!bg-rose-600">
                            {{ __('Cancel Subscription') }}
                        </button>
                        <button type="button" onclick="document.getElementById('cancel-modal').classList.add('hidden')" class="text-sm font-medium text-ink/60 hover:text-ink">
                            {{ __('Keep Subscription') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</x-app-layout>
