{{-- Shared Marketing Footer --}}
<footer class="border-t border-ink/10 bg-gradient-to-b from-surface to-surface-highlight/20 py-16">
    <div class="mx-auto max-w-6xl px-6">
        {{-- Newsletter / CTA Banner --}}
        <div class="mb-14 rounded-3xl border border-primary/15 bg-gradient-to-br from-primary/5 via-transparent to-secondary/5 p-8 sm:p-10">
            <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                <div>
                    <h3 class="font-display text-xl font-bold text-ink sm:text-2xl">{{ __('Ready to ship your SaaS?') }}</h3>
                    <p class="mt-2 text-sm text-ink/60">{{ __('Start building on a production-grade foundation today.') }}</p>
                </div>
                <div class="flex gap-3 shrink-0">
                    <a href="{{ route('pricing') }}" class="btn-primary !px-6 !py-2.5 text-sm">{{ __('Get Started') }}</a>
                    <a href="{{ route('docs.index') }}" class="btn-secondary !px-6 !py-2.5 text-sm">{{ __('Read Docs') }}</a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-10 mb-14">
            {{-- Brand --}}
            <div class="col-span-2 md:col-span-1">
                <a href="{{ route('home') }}" class="flex items-center gap-2 mb-5 group">
                    <x-application-logo class="h-9 w-9 text-primary transition-transform group-hover:scale-110" />
                    <span class="font-display text-lg font-bold text-ink">{{ $appBrandName ?? config('app.name', 'SaaS Kit') }}</span>
                </a>
                <p class="text-sm leading-6 text-ink/50 max-w-xs">{{ __('The boilerplate for high-ambition SaaS. Ship faster with senior-dev quality code.') }}</p>
            </div>

            {{-- Product --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-[0.15em] text-ink/70 mb-5">{{ __('Product') }}</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ route('features') }}" class="text-ink/55 hover:text-primary transition-colors">{{ __('Features') }}</a></li>
                    <li><a href="{{ route('solutions.index') }}" class="text-ink/55 hover:text-primary transition-colors">{{ __('Solutions') }}</a></li>
                    <li><a href="{{ route('pricing') }}" class="text-ink/55 hover:text-primary transition-colors">{{ __('Pricing') }}</a></li>
                    <li><a href="{{ route('roadmap') }}" class="text-ink/55 hover:text-primary transition-colors">{{ __('Roadmap') }}</a></li>
                </ul>
            </div>

            {{-- SEO Solutions --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-[0.15em] text-ink/70 mb-5">{{ __('SEO Pages') }}</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ route('solutions.show', ['slug' => 'laravel-stripe-paddle-billing-starter']) }}" class="text-ink/55 hover:text-primary transition-colors">{{ __('Billing Starter') }}</a></li>
                    <li><a href="{{ route('solutions.show', ['slug' => 'filament-admin-operations-for-saas']) }}" class="text-ink/55 hover:text-primary transition-colors">{{ __('Admin Operations') }}</a></li>
                    <li><a href="{{ route('solutions.show', ['slug' => 'laravel-saas-blog-and-seo-starter']) }}" class="text-ink/55 hover:text-primary transition-colors">{{ __('Blog + SEO') }}</a></li>
                    <li><a href="{{ route('solutions.show', ['slug' => 'laravel-saas-onboarding-and-localization']) }}" class="text-ink/55 hover:text-primary transition-colors">{{ __('Onboarding + i18n') }}</a></li>
                </ul>
            </div>

            {{-- Resources --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-[0.15em] text-ink/70 mb-5">{{ __('Resources') }}</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ route('blog.index') }}" class="text-ink/55 hover:text-primary transition-colors">{{ __('Blog') }}</a></li>
                    <li><a href="{{ route('docs.index') }}" class="text-ink/55 hover:text-primary transition-colors">{{ __('Documentation') }}</a></li>
                </ul>
            </div>

            {{-- Legal --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-[0.15em] text-ink/70 mb-5">{{ __('Legal') }}</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="#" class="text-ink/55 hover:text-primary transition-colors">{{ __('Privacy Policy') }}</a></li>
                    <li><a href="#" class="text-ink/55 hover:text-primary transition-colors">{{ __('Terms of Service') }}</a></li>
                </ul>
            </div>
        </div>

        {{-- Bottom Bar --}}
        <div class="pt-8 border-t border-ink/8 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-xs text-ink/40">
                &copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
            </p>
            <div class="flex items-center gap-5">
                <a href="#" class="text-ink/35 hover:text-ink transition-colors" aria-label="X (Twitter)">
                    <svg class="w-4.5 h-4.5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                <a href="#" class="text-ink/35 hover:text-ink transition-colors" aria-label="GitHub">
                    <svg class="w-4.5 h-4.5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/></svg>
                </a>
            </div>
        </div>
    </div>
</footer>
