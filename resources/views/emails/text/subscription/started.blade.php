@extends('emails.text.layout')

@section('content')
{{ __('Your subscription is active') }}

{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}

{{ __('Your :planName is now active.', ['planName' => $planName ?? __('subscription')]) }}
@if(isset($amount) && isset($currency))
{{ __('Amount') }}: {{ $currency }} {{ number_format($amount / 100, 2) }}
@endif

{{ __('Included features:') }}
@if(isset($features) && is_array($features) && count($features))
@foreach($features as $feature)
- {{ $feature }}
@endforeach
@else
- {{ __('Access all premium features') }}
- {{ __('Priority support') }}
- {{ __('Regular updates') }}
@endif

{{ __('Start using :appName', ['appName' => config('app.name')]) }}:
{{ config('app.url') }}/dashboard
@endsection
