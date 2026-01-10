@php
    use Illuminate\Support\Str;

    $primary = $colors['primary'] ?? '14 116 144';
    $secondary = $colors['secondary'] ?? '245 158 11';
    $accent = $colors['accent'] ?? '239 68 68';
    $bg = $colors['bg'] ?? '250 250 249';
    $fg = $colors['fg'] ?? '15 23 42';
    $fontSans = $fonts['sans'] ?? 'Instrument Sans';
    $fontDisplay = $fonts['display'] ?? 'Instrument Serif';
    $fgComma = str_replace(' ', ', ', $fg);

    $safeTitle = Str::limit($title, 80);
    $safeSubtitle = Str::limit($subtitle, 140);
@endphp
<svg width="1200" height="630" viewBox="0 0 1200 630" fill="none" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="halo" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="rgb({{ $primary }})" stop-opacity="0.2" />
            <stop offset="60%" stop-color="rgb({{ $secondary }})" stop-opacity="0.12" />
            <stop offset="100%" stop-color="rgb({{ $accent }})" stop-opacity="0.08" />
        </linearGradient>
    </defs>
    <rect width="1200" height="630" fill="rgb({{ $bg }})" />
    <rect x="60" y="60" width="1080" height="510" rx="40" fill="url(#halo)" />
    <rect x="90" y="90" width="1020" height="450" rx="32" fill="white" fill-opacity="0.85" />

    <text x="140" y="210" fill="rgb({{ $fg }})" font-family="{{ $fontDisplay }}, serif" font-size="56" font-weight="600">
        {{ $safeTitle }}
    </text>
    <text x="140" y="280" fill="rgba({{ $fgComma }}, 0.7)" font-family="{{ $fontSans }}, sans-serif" font-size="26">
        {{ $safeSubtitle }}
    </text>

    <text x="140" y="520" fill="rgba({{ $fgComma }}, 0.6)" font-family="{{ $fontSans }}, sans-serif" font-size="22" letter-spacing="4">
        {{ strtoupper($brandName) }}
    </text>
</svg>
