@extends('emails.text.layout')

@section('content')
{{ __('Reset Password') }}

{{ __('You are receiving this email because we received a password reset request for your account.') }}

{{ __('Reset Password') }}:
{{ $url }}

{{ __('This password reset link will expire in :count minutes.', ['count' => $count]) }}

{{ __('If you did not request a password reset, no further action is required.') }}
@endsection
