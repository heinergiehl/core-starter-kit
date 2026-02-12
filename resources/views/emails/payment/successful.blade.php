@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">Payment Successful</h1>

    <p>Hi {{ $user->name ?? 'there' }},</p>

    <p>We have received your payment for <strong>{{ $planName ?? 'Product' }}</strong>.</p>

    <div style="background-color: #f0fdf4; border-radius: 8px; padding: 16px; margin: 24px 0; border-left: 4px solid #22c55e;">
        <p style="margin: 0; color: #166534; font-weight: 600;">
            Payment confirmed
        </p>
        @if(isset($amount) && isset($currency))
            <p style="margin: 8px 0 0; color: #166534;">
                Amount: {{ $currency }} {{ number_format($amount / 100, 2) }}
            </p>
        @endif
    </div>

    @if(isset($receiptUrl))
        <x-email.button :href="$receiptUrl">
            View Receipt
        </x-email.button>
    @endif

    <p style="margin: 16px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
        Thank you for your purchase. If you have any questions, feel free to reply to this email.
    </p>
@endsection
