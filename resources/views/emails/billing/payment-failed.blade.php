@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">Action Required: Payment Failed</h1>

    <p>Hi {{ $user->name ?? 'there' }},</p>

    <p>We were not able to process your payment for <strong>{{ $planName ?? 'your subscription' }}</strong>.</p>

    <div style="background-color: #fef2f2; border-radius: 8px; padding: 16px; margin: 24px 0; border-left: 4px solid #ef4444;">
        <p style="margin: 0; color: #991b1b; font-weight: 600;">
            Payment Failed
        </p>
        @if(isset($amount) && isset($currency))
            <p style="margin: 8px 0 0; color: #991b1b;">
                Amount: {{ $currency }} {{ number_format($amount / 100, 2) }}
            </p>
        @endif
        @if(isset($failureReason))
            <p style="margin: 8px 0 0; color: #991b1b; font-size: 14px;">
                Reason: {{ $failureReason }}
            </p>
        @endif
    </div>

    <p>To avoid interruption to your service, please update your payment method:</p>

    <x-email.button :href="config('app.url') . '/billing'" variant="danger">
        Update Payment Method
    </x-email.button>

    <p style="margin-top: 24px;">Common reasons for payment failures:</p>
    <ul style="margin: 8px 0; padding-left: 24px; color: #64748b; font-size: 14px;">
        <li style="margin-bottom: 4px;">Card expired or cancelled</li>
        <li style="margin-bottom: 4px;">Insufficient funds</li>
        <li style="margin-bottom: 4px;">Bank declined the transaction</li>
    </ul>

    <p style="margin: 16px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
        If you need help, our support team is here for you. We will retry the payment automatically in a few days.
    </p>
@endsection
