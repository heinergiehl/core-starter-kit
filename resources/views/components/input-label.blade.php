@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-ink/70']) }}>
    {{ $value ?? $slot }}
</label>
