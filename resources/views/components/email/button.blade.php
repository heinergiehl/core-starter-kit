@props([
    'href',
    'variant' => 'primary',
    'align' => 'center',
])

@php
    $branding = app(\App\Domain\Settings\Services\BrandingService::class);
    $contrast = \App\Support\Color\Contrast::class;

    $isDanger = $variant === 'danger';
    $backgroundColor = $isDanger ? '#DC2626' : $branding->emailPrimaryColor();
    $borderColor = $isDanger ? '#B91C1C' : $branding->emailSecondaryColor();
    $textColor = $contrast::bestTextColor($backgroundColor);
@endphp

<div style="text-align: {{ $align }}; margin: 16px 0;">
    <a
        href="{{ $href }}"
        class="btn {{ $isDanger ? 'btn-danger' : '' }}"
        style="display: inline-block; padding: 14px 28px; background-color: {{ $backgroundColor }}; border: 1px solid {{ $borderColor }}; color: {{ $textColor }} !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; line-height: 1.2;"
    >
        {{ $slot }}
    </a>
</div>
