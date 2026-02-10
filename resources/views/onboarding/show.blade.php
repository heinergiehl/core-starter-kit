<x-guest-layout>
    <div class="w-full max-w-xl mx-auto">
        {{-- Progress Bar --}}
        <div class="mb-8">
            <div class="flex justify-between mb-2">
                @for($i = 1; $i <= $totalSteps; $i++)
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-all {{ $i <= $step ? 'bg-primary text-white' : 'bg-ink/10 text-ink/40' }}">
                            @if($i < $step)
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            @else
                                {{ $i }}
                            @endif
                        </div>
                        @if($i < $totalSteps)
                            <div class="w-16 h-1 mx-2 rounded {{ $i < $step ? 'bg-primary' : 'bg-ink/10' }}"></div>
                        @endif
                    </div>
                @endfor
            </div>
        </div>

        <div class="glass-panel rounded-[32px] p-8">
            @if($step === 1)
                {{-- Step 1: Welcome --}}
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-display font-bold text-ink">{{ __('Welcome aboard!') }}</h2>
                    <p class="text-ink/60 mt-2">{{ __("Let's get you set up in just a few steps.") }}</p>
                </div>

                <form method="POST" action="{{ route('onboarding.update') }}" data-submit-lock>
                    @csrf
                    <input type="hidden" name="step" value="1">

                    <div class="mb-6">
                        <x-input-label for="name" :value="__('Your Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <x-primary-button class="w-full justify-center">
                        {{ __('Continue') }}
                    </x-primary-button>
                </form>

            @else
                {{-- Step 2: Preferences & Complete --}}
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-green-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-display font-bold text-ink">{{ __("You're all set!") }}</h2>
                    <p class="text-ink/60 mt-2">{{ __('Just a few preferences and you can start using the app.') }}</p>
                </div>

                <form method="POST" action="{{ route('onboarding.update') }}" data-submit-lock>
                    @csrf
                    <input type="hidden" name="step" value="2">

                    <div class="mb-6">
                        <x-input-label for="locale" :value="__('Preferred Language')" />
                        <select id="locale" name="locale" class="mt-1 block w-full border-ink/20 rounded-lg shadow-sm focus:border-primary focus:ring-primary bg-surface text-ink">
                            <option value="en" {{ ($user->locale ?? 'en') === 'en' ? 'selected' : '' }}>English</option>
                            <option value="de" {{ ($user->locale ?? 'en') === 'de' ? 'selected' : '' }}>Deutsch</option>
                            <option value="es" {{ ($user->locale ?? 'en') === 'es' ? 'selected' : '' }}>Español</option>
                            <option value="fr" {{ ($user->locale ?? 'en') === 'fr' ? 'selected' : '' }}>Français</option>
                        </select>
                    </div>

                    <x-primary-button class="w-full justify-center">
                        {{ __('Complete Setup') }}
                    </x-primary-button>
                </form>
            @endif

            {{-- Skip Link --}}
            <div class="mt-6 text-center">
                <form method="POST" action="{{ route('onboarding.skip') }}" data-submit-lock>
                    @csrf
                    <button type="submit" class="text-sm text-ink/40 hover:text-ink/60 transition">
                        {{ __('Skip for now') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
