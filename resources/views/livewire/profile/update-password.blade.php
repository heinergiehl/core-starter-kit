<section 
    x-data="{ submitting: false }"
    x-on:password-updated.window="submitting = false"
    x-init="
        Livewire.hook('commit', ({ succeed, fail }) => {
            fail(() => { submitting = false; });
        });
    "
>
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ $hasPassword ? __('Update Password') : __('Set Password') }}
        </h2>

        <p class="mt-1 text-sm text-ink/60">
            @if($hasPassword)
                {{ __('Ensure your account is using a long, random password to stay secure.') }}
            @else
                {{ __('You signed up with a social account. Set a password to also log in with email.') }}
            @endif
        </p>
    </header>

    <form wire:submit="updatePassword" x-on:submit="submitting = true" class="mt-6 space-y-6">
        <fieldset :disabled="submitting" x-bind:class="{ 'opacity-60': submitting }">
            @if($hasPassword)
                <div>
                    <x-input-label for="update_password_current_password" :value="__('Current Password')" />
                    <x-text-input 
                        wire:model="current_password" 
                        id="update_password_current_password" 
                        name="current_password" 
                        type="password" 
                        class="mt-1 block w-full" 
                        autocomplete="current-password" 
                    />
                    <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                </div>
            @endif

            <div class="mt-4">
                <x-input-label for="update_password_password" :value="__('New Password')" />
                <x-text-input 
                    wire:model="password" 
                    id="update_password_password" 
                    name="password" 
                    type="password" 
                    class="mt-1 block w-full" 
                    autocomplete="new-password" 
                />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
                <x-text-input 
                    wire:model="password_confirmation" 
                    id="update_password_password_confirmation" 
                    name="password_confirmation" 
                    type="password" 
                    class="mt-1 block w-full" 
                    autocomplete="new-password" 
                />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="flex items-center gap-4 mt-4">
                <button 
                    type="submit"
                    class="min-w-[120px] inline-flex items-center justify-center gap-2 px-4 py-2 bg-primary border border-transparent rounded-xl font-semibold text-sm text-white tracking-wide hover:bg-primary/90 focus:bg-primary/90 active:bg-primary focus:outline-none focus:ring-2 focus:ring-primary/50 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-100 disabled:cursor-not-allowed"
                >
                    <svg x-show="submitting" x-cloak class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="submitting ? '{{ __('Saving...') }}' : '{{ $hasPassword ? __('Save') : __('Set Password') }}'"></span>
                </button>
            </div>
        </fieldset>
    </form>
</section>
