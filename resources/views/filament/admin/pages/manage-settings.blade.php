<x-filament-panels::page>
    <form wire:submit.prevent="save" class="space-y-6">
        {{ $this->form }}

        <x-admin.settings-form-actions />
    </form>
</x-filament-panels::page>
