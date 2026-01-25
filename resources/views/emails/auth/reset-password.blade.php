@extends('emails.layout')

@section('content')
    <h1>{{ __('Reset Password') }}</h1>

    <p>{{ __('You are receiving this email because we received a password reset request for your account.') }}</p>

    <div class="text-center">
        <a href="{{ $url }}" class="btn">
            {{ __('Reset Password') }}
        </a>
    </div>

    <p class="muted mt-4">
        {{ __('This password reset link will expire in :count minutes.', ['count' => $count]) }}
    </p>

    <p class="muted">
        {{ __('If you did not request a password reset, no further action is required.') }}
    </p>
@endsection
