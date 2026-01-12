<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-medium text-xl text-ink leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    {{-- Livewire Toast Notifications --}}
    <x-livewire-toasts />

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="glass-panel p-4 sm:p-8 rounded-[32px]">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information />
                </div>
            </div>

            <div class="glass-panel p-4 sm:p-8 rounded-[32px]">
                <div class="max-w-xl">
                    <livewire:profile.update-password />
                </div>
            </div>

            <div class="glass-panel p-4 sm:p-8 rounded-[32px]">
                <div class="max-w-xl">
                    <livewire:profile.two-factor-authentication />
                </div>
            </div>

            <div class="glass-panel p-4 sm:p-8 rounded-[32px]">
                <div class="max-w-xl">
                    <livewire:profile.delete-account />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
