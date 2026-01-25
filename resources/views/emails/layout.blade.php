{{--
    Base Email Layout Template
    Uses branding from settings + config
--}}
@php
    $branding = app(\App\Domain\Settings\Services\BrandingService::class);
    $appName = $branding->appName();
    $appUrl = config('app.url', 'http://localhost');
    $supportEmail = config('saas.support.email');
    $logoPath = $branding->logoPath();

    $primaryColor = $branding->emailPrimaryColor();
    $secondaryColor = $branding->emailSecondaryColor();
    $textColor = '#334155';
    $mutedColor = '#64748b';
    $bgColor = '#f8fafc';
    $cardBg = '#ffffff';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? $appName }}</title>
    <style>
        /* Reset */
        body, html { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        
        /* Container */
        .wrapper { background-color: {{ $bgColor }}; padding: 40px 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        
        /* Card */
        .card { background-color: {{ $cardBg }}; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        
        /* Header */
        .header { padding: 32px 32px 24px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .logo { height: 40px; width: auto; }
        .logo-text { font-size: 24px; font-weight: 700; color: {{ $primaryColor }}; text-decoration: none; }
        
        /* Body */
        .body { padding: 32px; color: {{ $textColor }}; line-height: 1.6; }
        .body h1 { font-size: 24px; font-weight: 600; margin: 0 0 16px; color: #1e293b; }
        .body p { margin: 0 0 16px; font-size: 16px; }
        .body .muted { color: {{ $mutedColor }}; font-size: 14px; }
        
        /* Button */
        .btn { display: inline-block; padding: 14px 28px; background-color: {{ $primaryColor }}; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 16px 0; }
        .btn:hover { background-color: {{ $secondaryColor }}; }
        
        /* Footer */
        .footer { padding: 24px 32px; text-align: center; color: {{ $mutedColor }}; font-size: 13px; border-top: 1px solid #e2e8f0; }
        .footer a { color: {{ $mutedColor }}; text-decoration: underline; }
        
        /* Utilities */
        .text-center { text-align: center; }
        .mt-4 { margin-top: 16px; }
        .mb-4 { margin-bottom: 16px; }
        
        /* Responsive */
        @media (max-width: 640px) {
            .wrapper { padding: 20px 12px; }
            .header, .body, .footer { padding-left: 20px; padding-right: 20px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="card">
                {{-- Header with Logo --}}
                <div class="header">
                    <a href="{{ $appUrl }}" class="logo-text">
                        @if ($logoPath)
                            <img src="{{ asset($logoPath) }}" alt="{{ $appName }}" class="logo" />
                        @else
                            {{ $appName }}
                        @endif
                    </a>
                </div>
                
                {{-- Email Body --}}
                <div class="body">
                    @yield('content')
                </div>
                
                {{-- Footer --}}
                <div class="footer">
                    <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
                    @if($supportEmail)
                        <p>Questions? <a href="mailto:{{ $supportEmail }}">Contact support</a></p>
                    @endif
                    <p class="mt-4">
                        <a href="{{ $appUrl }}">Visit {{ $appName }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
