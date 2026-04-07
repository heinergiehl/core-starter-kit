@extends('emails.text.layout')

@section('content')
{{ __('Your trial has started!') }}

{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}

{{ __('Your :planName trial is now active.', ['planName' => $planName ?? __('subscription')]) }}
@if(!empty($trialEndsAt))
{{ __('Trial ends:') }} {{ $trialEndsAt }}
@endif

{{ __('Included features:') }}
@if(isset($features) && is_array($features) && count($features))
@foreach($features as $feature)
- {{ $feature }}
@endforeach
@else
- {{ __('Explore all premium features') }}
- {{ __('Complete your profile') }}
- {{ __('Start using the app') }}
@endif

{{ __('Start Your Trial') }}:
{{ config('app.url') }}/dashboard
@endsection
