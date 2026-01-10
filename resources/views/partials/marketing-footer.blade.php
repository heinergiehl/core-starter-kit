{{-- Shared Marketing Footer --}}
<footer class="border-t border-ink/10 bg-surface-highlight/10 backdrop-blur-md py-12">
    <div class="mx-auto max-w-6xl px-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-12">
            {{-- Brand --}}
            <div class="col-span-2 md:col-span-1">
                <a href="/" class="flex items-center gap-2 mb-4">
                    <x-application-logo class="h-8 w-8 text-primary" />
                    <span class="font-display text-lg font-bold text-ink">{{ $appBrandName ?? config('app.name', 'SaaS Kit') }}</span>
                </a>
                <p class="text-sm text-ink/50 max-w-xs">{{ __('The boilerplate for high-ambition SaaS. Ship faster with senior-dev quality code.') }}</p>
            </div>

            {{-- Product --}}
            <div>
                <h4 class="font-semibold text-ink mb-4">{{ __('Product') }}</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="/#features" class="text-ink/60 hover:text-ink transition">{{ __('Features') }}</a></li>
                    <li><a href="{{ route('pricing') }}" class="text-ink/60 hover:text-ink transition">{{ __('Pricing') }}</a></li>
                    <li><a href="{{ route('roadmap') }}" class="text-ink/60 hover:text-ink transition">{{ __('Roadmap') }}</a></li>
                </ul>
            </div>

            {{-- Resources --}}
            <div>
                <h4 class="font-semibold text-ink mb-4">{{ __('Resources') }}</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('blog.index') }}" class="text-ink/60 hover:text-ink transition">{{ __('Blog') }}</a></li>
                    <li><a href="/#architecture" class="text-ink/60 hover:text-ink transition">{{ __('Documentation') }}</a></li>
                </ul>
            </div>

            {{-- Legal --}}
            <div>
                <h4 class="font-semibold text-ink mb-4">{{ __('Legal') }}</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="#" class="text-ink/60 hover:text-ink transition">{{ __('Privacy Policy') }}</a></li>
                    <li><a href="#" class="text-ink/60 hover:text-ink transition">{{ __('Terms of Service') }}</a></li>
                </ul>
            </div>
        </div>

        {{-- Bottom Bar --}}
        <div class="pt-8 border-t border-ink/5 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-sm text-ink/40">
                &copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
            </p>
            <div class="flex items-center gap-4">
                {{-- Social Icons Placeholder --}}
                <a href="#" class="text-ink/40 hover:text-ink transition">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                <a href="#" class="text-ink/40 hover:text-ink transition">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/></svg>
                </a>
            </div>
        </div>
    </div>
</footer>
