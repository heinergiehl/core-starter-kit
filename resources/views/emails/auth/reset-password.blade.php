@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">{{ __('Reset Password') }}</h1>

    <p>{{ __('You are receiving this email because we received a password reset request for your account.') }}</p>

    <x-email.button :href="$url">
        {{ __('Reset Password') }}
    </x-email.button>

    <p style="margin: 16px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
        {{ __('This password reset link will expire in :count minutes.', ['count' => $count]) }}
    </p>

    <p style="margin: 8px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
        {{ __('If you did not request a password reset, no further action is required.') }}
    </p>
@endsection
