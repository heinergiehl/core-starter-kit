<x-guest-layout>
    <div class="mb-4 text-sm text-ink/60">
        {{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application.') }}
    </div>

    <form method="POST" action="{{ route('two-factor.verify') }}" x-data="{ useRecovery: false }">
        @csrf

        <div x-show="!useRecovery">
            <x-input-label for="code" :value="__('Code')" />
            <x-text-input 
                id="code" 
                class="block mt-1 w-full text-center tracking-[0.5em] font-mono text-lg" 
                type="text" 
                name="code" 
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                autofocus 
                autocomplete="one-time-code"
                placeholder="000000"
            />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div x-show="useRecovery" x-cloak>
            <x-input-label for="recovery_code" :value="__('Recovery Code')" />
            <x-text-input 
                id="recovery_code" 
                class="block mt-1 w-full font-mono" 
                type="text" 
                name="recovery_code"
                placeholder="XXXXXXXX"
            />
            <x-input-error :messages="$errors->get('recovery_code')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between mt-4">
            <button 
                type="button" 
                class="text-sm text-ink/60 underline hover:text-ink"
                @click="useRecovery = !useRecovery"
                x-text="useRecovery ? '{{ __('Use authentication code') }}' : '{{ __('Use recovery code') }}'"
            ></button>

            <x-primary-button>
                {{ __('Verify') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
