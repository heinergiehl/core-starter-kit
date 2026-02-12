@extends('emails.text.layout')

@section('content')
{{ __('Verify Email Address') }}

{{ __('Please click the link below to verify your email address.') }}
{{ $url }}

{{ __('If you did not create an account, no further action is required.') }}
@endsection
