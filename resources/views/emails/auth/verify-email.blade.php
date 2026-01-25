@extends('emails.layout')

@section('content')
    <h1>{{ __('Verify Email Address') }}</h1>

    <p>{{ __('Please click the button below to verify your email address.') }}</p>

    <div class="text-center">
        <a href="{{ $url }}" class="btn">
            {{ __('Verify Email Address') }}
        </a>
    </div>

    <p class="muted mt-4">
        {{ __('If you did not create an account, no further action is required.') }}
    </p>
@endsection
