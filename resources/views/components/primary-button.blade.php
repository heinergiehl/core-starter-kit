{{--
    Primary Button
    
    Simple button component. For Livewire forms, use wire:loading directly in the view.
--}}

<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-wait']) }}>
    {{ $slot }}
</button>
