@extends('emails.text.layout')

@section('content')
{{ __('Welcome Back!') }}

{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}

{{ __('Your :planName has been resumed.', ['planName' => $planName ?? __('subscription')]) }}
{{ __('You now have full access to all your features again.') }}

{{ __('View Your Subscription') }}:
{{ config('app.url') }}/billing
@endsection
