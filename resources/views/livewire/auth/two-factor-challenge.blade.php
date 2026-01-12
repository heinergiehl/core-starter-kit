<div 
    x-data="{ submitting: false }"
    x-on:verify-failed.window="submitting = false"
>
    <div class="mb-4 text-sm text-ink/60">
        @if($useRecoveryCode)
            {{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}
        @else
            {{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application.') }}
        @endif
    </div>

    <form wire:submit="verify" x-on:submit="submitting = true">
        <fieldset :disabled="submitting" x-bind:class="{ 'opacity-60': submitting }">
            @if($useRecoveryCode)
                {{-- Recovery Code --}}
                <div>
                    <x-input-label for="recovery_code" :value="__('Recovery Code')" />
                    <x-text-input 
                        wire:model="recovery_code" 
                        id="recovery_code" 
                        class="block mt-1 w-full font-mono" 
                        type="text" 
                        required 
                        autofocus
                        autocomplete="one-time-code"
                    />
                    <x-input-error :messages="$errors->get('recovery_code')" class="mt-2" />
                </div>
            @else
                {{-- Authentication Code --}}
                <div>
                    <x-input-label for="code" :value="__('Code')" />
                    <x-text-input 
                        wire:model="code" 
                        id="code" 
                        class="block mt-1 w-full text-center tracking-[0.5em] font-mono text-lg" 
                        type="text" 
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        placeholder="000000"
                        required 
                        autofocus
                        autocomplete="one-time-code"
                    />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
            @endif

            <div class="flex items-center justify-between mt-4">
                <button 
                    type="button" 
                    wire:click="toggleRecoveryCode" 
                    class="text-sm text-ink/60 hover:text-ink underline disabled:opacity-50 disabled:pointer-events-none"
                >
                    @if($useRecoveryCode)
                        {{ __('Use authentication code') }}
                    @else
                        {{ __('Use a recovery code') }}
                    @endif
                </button>

                <button 
                    type="submit"
                    class="min-w-[100px] inline-flex items-center justify-center gap-2 px-4 py-2 bg-primary border border-transparent rounded-xl font-semibold text-sm text-white tracking-wide hover:bg-primary/90 focus:bg-primary/90 active:bg-primary focus:outline-none focus:ring-2 focus:ring-primary/50 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-100 disabled:cursor-not-allowed"
                >
                    <svg x-show="submitting" x-cloak class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="submitting ? '{{ __('Verifying...') }}' : '{{ __('Verify') }}'"></span>
                </button>
            </div>
        </fieldset>
    </form>
</div>
