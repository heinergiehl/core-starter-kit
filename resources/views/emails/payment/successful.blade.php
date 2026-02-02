@extends('emails.layout')

@section('content')
    <h1>Payment Successful 💸</h1>

    <p>Hi {{ $user->name ?? 'there' }},</p>

    <p>We verify that we have received your payment for <strong>{{ $planName ?? 'Product' }}</strong>.</p>

    <div style="background-color: #f0fdf4; border-radius: 8px; padding: 16px; margin: 24px 0; border-left: 4px solid #22c55e;">
        <p style="margin: 0; color: #166534; font-weight: 600;">
            ✓ Payment confirmed
        </p>
        @if(isset($amount) && isset($currency))
            <p style="margin: 8px 0 0; color: #166534;">
                Amount: {{ $currency }} {{ number_format($amount / 100, 2) }}
            </p>
        @endif
    </div>

    @if(isset($receiptUrl))
        <div class="text-center" style="margin-top: 24px; margin-bottom: 24px;">
            <a href="{{ $receiptUrl }}" class="btn">
                View Receipt
            </a>
        </div>
    @endif

    <p class="muted mt-4">
        Thank you for your purchase. If you have any questions, feel free to reply to this email.
    </p>
@endsection
