{{--
    Base Email Layout Template
    Uses branding from settings + config
--}}
@php
    $branding = app(\App\Domain\Settings\Services\BrandingService::class);
    $contrast = \App\Support\Color\Contrast::class;
    $appName = $branding->appName();
    $appUrl = config('app.url', 'http://localhost');
    $supportEmail = config('saas.support.email');
    $logoPath = $branding->logoPath();

    $primaryColor = $branding->emailPrimaryColor();
    $secondaryColor = $branding->emailSecondaryColor();
    $buttonTextColor = $contrast::bestTextColor($primaryColor);
    $dangerColor = '#DC2626';
    $dangerHoverColor = '#B91C1C';
    $dangerTextColor = $contrast::bestTextColor($dangerColor);

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
        body, html {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: {{ $bgColor }};
            color: {{ $textColor }};
        }

        .email-body h1 {
            font-size: 24px;
            font-weight: 600;
            line-height: 1.3;
            margin: 0 0 16px;
            color: #1e293b;
        }

        .email-body p {
            margin: 0 0 16px;
            font-size: 16px;
            line-height: 1.6;
        }

        .email-body .muted {
            color: {{ $mutedColor }};
            font-size: 14px;
        }

        .email-body a {
            color: {{ $secondaryColor }};
            text-decoration: underline;
        }

        .btn {
            display: inline-block;
            padding: 14px 28px;
            background-color: {{ $primaryColor }};
            border: 1px solid {{ $secondaryColor }};
            color: {{ $buttonTextColor }} !important;
            text-decoration: none !important;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            line-height: 1.2;
        }

        .btn:hover { background-color: {{ $secondaryColor }}; }
        .btn-danger { background-color: {{ $dangerColor }}; border-color: {{ $dangerHoverColor }}; color: {{ $dangerTextColor }} !important; }
        .btn-danger:hover { background-color: {{ $dangerHoverColor }}; border-color: {{ $dangerHoverColor }}; }

        @media (max-width: 640px) {
            .email-shell {
                padding: 20px 12px !important;
            }

            .email-header,
            .email-content,
            .email-footer {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: {{ $bgColor }};">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; background-color: {{ $bgColor }};">
        <tr>
            <td align="center" class="email-shell" style="padding: 40px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px;">
                    <tr>
                        <td style="background-color: {{ $cardBg }}; border-radius: 12px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); overflow: hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
                                <tr>
                                    <td align="center" class="email-header" style="padding: 32px 32px 24px; border-bottom: 1px solid #e2e8f0;">
                                        <a href="{{ $appUrl }}" style="font-size: 24px; font-weight: 700; color: {{ $primaryColor }}; text-decoration: none;">
                                            @if ($logoPath)
                                                <img src="{{ asset($logoPath) }}" alt="{{ $appName }}" style="height: 40px; width: auto; display: block; margin: 0 auto;" />
                                            @else
                                                {{ $appName }}
                                            @endif
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-content email-body" style="padding: 32px; color: {{ $textColor }}; font-size: 16px; line-height: 1.6;">
                                        @yield('content')
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-footer" style="padding: 24px 32px; text-align: center; color: {{ $mutedColor }}; font-size: 13px; border-top: 1px solid #e2e8f0; line-height: 1.6;">
                                        <p style="margin: 0 0 8px;">&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
                                        @if($supportEmail)
                                            <p style="margin: 0 0 8px;">Questions? <a href="mailto:{{ $supportEmail }}" style="color: {{ $secondaryColor }}; text-decoration: underline;">Contact support</a></p>
                                        @endif
                                        <p style="margin: 8px 0 0;">
                                            <a href="{{ $appUrl }}" style="color: {{ $secondaryColor }}; text-decoration: underline;">Visit {{ $appName }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
