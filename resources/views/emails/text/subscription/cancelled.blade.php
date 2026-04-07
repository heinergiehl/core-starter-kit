@extends('emails.text.layout')

@section('content')
{{ __('We\'re sorry to see you go') }}

{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}

{{ __('Your :planName has been cancelled.', ['planName' => $planName ?? __('subscription')]) }}
{{ __('Access until:') }} {{ $accessUntil ?? __('End of billing period') }}

{{ __('Reactivate Anytime') }}:
{{ config('app.url') }}/billing

{{ __('If there is anything we could have done better, please let us know.') }}
@endsection
