@extends('emails.text.layout')

@section('content')
{{ __('Payment Successful') }}

{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}

{{ __('We\'ve received your payment for :planName.', ['planName' => $planName ?? __('Product')]) }}
@if(isset($amount) && isset($currency))
{{ __('Amount') }}: {{ $currency }} {{ number_format($amount / 100, 2) }}
@endif

@if(isset($receiptUrl))
{{ __('View Receipt') }}:
{{ $receiptUrl }}
@endif

{{ __('Thank you for your purchase!') }} {{ __('If you have any questions, feel free to reach out to our support team.') }}
@endsection
