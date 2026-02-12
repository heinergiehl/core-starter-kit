@extends('emails.text.layout')

@section('content')
Action Required: Payment Failed

Hi {{ $user->name ?? 'there' }},

We were not able to process your payment for {{ $planName ?? 'your subscription' }}.
@if(isset($amount) && isset($currency))
Amount: {{ $currency }} {{ number_format($amount / 100, 2) }}
@endif
@if(isset($failureReason))
Reason: {{ $failureReason }}
@endif

Update your payment method:
{{ config('app.url') }}/billing

Common reasons:
- Card expired or cancelled
- Insufficient funds
- Bank declined the transaction

If you need help, our support team is here for you.
@endsection
