@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">{{ __('Verify Email Address') }}</h1>

    <p>{{ __('Please click the button below to verify your email address.') }}</p>

    <x-email.button :href="$url">
        {{ __('Verify Email Address') }}
    </x-email.button>

    <p style="margin: 16px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
        {{ __('If you did not create an account, no further action is required.') }}
    </p>
@endsection
