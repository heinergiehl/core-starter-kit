@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-full bg-ink/5 px-3 py-2 text-sm font-semibold text-ink focus:outline-none transition duration-150 ease-in-out'
            : 'inline-flex items-center rounded-full px-3 py-2 text-sm font-medium text-ink/60 hover:bg-ink/5 hover:text-ink focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
