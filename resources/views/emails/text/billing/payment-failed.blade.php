@extends('emails.text.layout')

@section('content')
{{ __('Action Required: Payment Failed') }}

{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}

{{ __('We were unable to process your payment for :planName.', ['planName' => $planName ?? __('your subscription')]) }}
@if(isset($amount) && isset($currency))
{{ __('Amount') }}: {{ $currency }} {{ number_format($amount / 100, 2) }}
@endif
@if(isset($failureReason))
{{ __('Reason') }}: {{ $failureReason }}
@endif

{{ __('Update Payment Method') }}:
{{ config('app.url') }}/billing

{{ __('Common reasons for payment failure:') }}
- {{ __('Card expired or cancelled') }}
- {{ __('Insufficient funds') }}
- {{ __('Bank declined the transaction') }}

{{ __('If you need help, our support team is here for you.') }}
@endsection
