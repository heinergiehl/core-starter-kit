@php
    $branding = app(\App\Domain\Settings\Services\BrandingService::class);
    $appName = $branding->appName();
    $appUrl = config('app.url', 'http://localhost');
    $supportEmail = config('saas.support.email');
@endphp
{{ $subject ?? $appName }}

@yield('content')

--
{{ $appName }}
{{ $appUrl }}
@if($supportEmail)
Support: {{ $supportEmail }}
@endif

