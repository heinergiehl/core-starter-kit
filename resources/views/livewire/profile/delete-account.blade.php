<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-ink/60">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <x-danger-button wire:click="openModal">
        {{ __('Delete Account') }}
    </x-danger-button>

    {{-- Confirmation Modal --}}
    @if($showModal)
        <div 
            class="fixed inset-0 z-50 overflow-y-auto" 
            aria-labelledby="modal-title" 
            role="dialog" 
            aria-modal="true"
        >
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                {{-- Background overlay --}}
                <div 
                    class="fixed inset-0 bg-ink/50 backdrop-blur-sm transition-opacity" 
                    wire:click="closeModal"
                ></div>

                {{-- Modal panel --}}
                <div class="inline-block transform overflow-hidden rounded-2xl bg-surface text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <form wire:submit="deleteAccount" class="p-6">
                        <h2 class="text-lg font-medium text-ink">
                            {{ __('Are you sure you want to delete your account?') }}
                        </h2>

                        <p class="mt-1 text-sm text-ink/60">
                            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                        </p>

                        <div class="mt-6">
                            <x-input-label for="delete_password" value="{{ __('Password') }}" class="sr-only" />
                            <x-text-input
                                wire:model="password"
                                id="delete_password"
                                type="password"
                                class="mt-1 block w-3/4"
                                placeholder="{{ __('Password') }}"
                                autofocus
                            />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button type="button" wire:click="closeModal">
                                {{ __('Cancel') }}
                            </x-secondary-button>

                            <x-danger-button type="submit" wire:loading.attr="disabled" wire:target="deleteAccount" class="min-w-[120px]">
                                <svg wire:loading wire:target="deleteAccount" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="deleteAccount">{{ __('Delete Account') }}</span>
                                <span wire:loading wire:target="deleteAccount">{{ __('Deleting...') }}</span>
                            </x-danger-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</section>
