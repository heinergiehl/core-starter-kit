@extends('emails.text.layout')

@section('content')
Payment Successful

Hi {{ $user->name ?? 'there' }},

We have received your payment for {{ $planName ?? 'Product' }}.
@if(isset($amount) && isset($currency))
Amount: {{ $currency }} {{ number_format($amount / 100, 2) }}
@endif

@if(isset($receiptUrl))
View receipt:
{{ $receiptUrl }}
@endif

Thank you for your purchase. If you have any questions, feel free to reply to this email.
@endsection
