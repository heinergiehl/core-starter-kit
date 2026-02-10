@php
    $logoPath = filled($appLogoPath ?? null) ? (string) $appLogoPath : null;
    $logoFile = $logoPath ? public_path($logoPath) : null;
    $shouldRenderImage = $logoPath && $logoFile && is_file($logoFile);
@endphp

@if ($shouldRenderImage)
    <img src="{{ asset($logoPath) }}" alt="{{ $appBrandName ?? 'Logo' }}" {{ $attributes->class('block') }} />
@else
    <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" fill="none" {{ $attributes->class('block') }}>
        <path d="M45 16C42 13 38 11 33 11C25 11 19 15 19 21C19 27 24 30 31 31C39 32 44 35 44 40C44 46 39 50 32 50C26 50 21 48 17 44" fill="none" stroke="#8B5CF6" stroke-width="8.25" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
@endif
