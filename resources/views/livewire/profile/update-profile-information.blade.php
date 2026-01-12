<section 
    x-data="{ submitting: false }"
    x-on:profile-updated.window="submitting = false"
    x-init="
        $wire.on('validation-errors', () => { submitting = false; });
        Livewire.hook('commit', ({ succeed, fail }) => {
            fail(() => { submitting = false; });
        });
    "
>
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-ink/60">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" x-on:submit="submitting = true" class="mt-6 space-y-6">
        <fieldset :disabled="submitting" x-bind:class="{ 'opacity-60': submitting }">
            <div>
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input 
                    wire:model="name" 
                    id="name" 
                    name="name" 
                    type="text" 
                    class="mt-1 block w-full" 
                    required 
                    autofocus 
                    autocomplete="name" 
                />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div class="mt-4">
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input 
                    wire:model="email" 
                    id="email" 
                    name="email" 
                    type="email" 
                    class="mt-1 block w-full" 
                    required 
                    autocomplete="username" 
                />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div>
                        <p class="text-sm mt-2 text-ink">
                            {{ __('Your email address is unverified.') }}

                            <button 
                                wire:click.prevent="sendVerification" 
                                class="underline text-sm text-ink/60 hover:text-ink rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                            >
                                {{ __('Click here to re-send the verification email.') }}
                            </button>
                        </p>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4 mt-4">
                <button 
                    type="submit"
                    class="min-w-[100px] inline-flex items-center justify-center gap-2 px-4 py-2 bg-primary border border-transparent rounded-xl font-semibold text-sm text-white tracking-wide hover:bg-primary/90 focus:bg-primary/90 active:bg-primary focus:outline-none focus:ring-2 focus:ring-primary/50 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-100 disabled:cursor-not-allowed"
                >
                    <svg x-show="submitting" x-cloak class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="submitting ? '{{ __('Saving...') }}' : '{{ __('Save') }}'"></span>
                </button>
            </div>
        </fieldset>
    </form>
</section>
