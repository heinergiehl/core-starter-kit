@props([
    'message' => 'Changes save after validation passes.',
    'submitLabel' => 'Save changes',
    'wireTarget' => 'save',
])

<div class="mt-6 flex flex-col gap-3 border-t border-gray-200/80 pt-4 dark:border-white/10 lg:flex-row lg:items-center lg:justify-between">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $message }}
    </p>

    <div class="flex flex-wrap items-center gap-3">
        {{ $slot }}

        <x-filament::button
            type="submit"
            wire:target="{{ $wireTarget }}"
        >
            {{ $submitLabel }}
        </x-filament::button>
    </div>
</div>
