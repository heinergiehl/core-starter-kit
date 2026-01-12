<div 
    x-data="{ submitting: false }"
    x-on:reset-failed.window="submitting = false"
>
    <form wire:submit="resetPassword" x-on:submit="submitting = true">
        <fieldset :disabled="submitting" x-bind:class="{ 'opacity-60': submitting }">
            {{-- Email Address --}}
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input 
                    wire:model="email" 
                    id="email" 
                    class="block mt-1 w-full" 
                    type="email" 
                    required 
                    autofocus 
                    autocomplete="username" 
                />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            {{-- Password --}}
            <div class="mt-4">
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input 
                    wire:model="password" 
                    id="password" 
                    class="block mt-1 w-full" 
                    type="password" 
                    required 
                    autocomplete="new-password" 
                />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            {{-- Confirm Password --}}
            <div class="mt-4">
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input 
                    wire:model="password_confirmation" 
                    id="password_confirmation" 
                    class="block mt-1 w-full"
                    type="password"
                    required 
                    autocomplete="new-password" 
                />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <button 
                    type="submit"
                    class="min-w-[140px] inline-flex items-center justify-center gap-2 px-4 py-2 bg-primary border border-transparent rounded-xl font-semibold text-sm text-white tracking-wide hover:bg-primary/90 focus:bg-primary/90 active:bg-primary focus:outline-none focus:ring-2 focus:ring-primary/50 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-100 disabled:cursor-not-allowed"
                >
                    <svg x-show="submitting" x-cloak class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="submitting ? '{{ __('Resetting...') }}' : '{{ __('Reset Password') }}'"></span>
                </button>
            </div>
        </fieldset>
    </form>
</div>
