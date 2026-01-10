@php
    $providers = config('saas.auth.social_providers', []);
    $labels = [
        'google' => 'Google',
        'github' => 'GitHub',
        'linkedin' => 'LinkedIn',
    ];
    $enabledProviders = collect($providers)
        ->map(fn (string $provider) => strtolower($provider))
        ->filter(fn (string $provider) => !empty(config("services.{$provider}.client_id")))
        ->values();
@endphp

@if($enabledProviders->isNotEmpty())
    <div class="mt-6">
        <div class="flex items-center gap-3 text-xs uppercase tracking-[0.2em] text-ink/40">
            <span class="h-px flex-1 bg-ink/10"></span>
            <span>{{ __('Or continue with') }}</span>
            <span class="h-px flex-1 bg-ink/10"></span>
        </div>
        <div class="mt-4 grid gap-2">
            @foreach($enabledProviders as $provider)
                <a
                    href="{{ route('social.redirect', $provider) }}"
                    class="flex items-center justify-center rounded-xl border border-ink/10 bg-white px-4 py-2 text-sm font-semibold text-ink/80 transition hover:border-primary/30 hover:text-ink"
                >
                    {{ __('Continue with :provider', ['provider' => $labels[$provider] ?? ucfirst($provider)]) }}
                </a>
            @endforeach
        </div>
    </div>
@endif
