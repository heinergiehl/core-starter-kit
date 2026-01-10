@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-lg px-3 py-2 text-start text-base font-semibold text-ink bg-ink/5 focus:outline-none transition duration-150 ease-in-out'
            : 'block w-full rounded-lg px-3 py-2 text-start text-base font-medium text-ink/60 hover:text-ink hover:bg-ink/5 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
