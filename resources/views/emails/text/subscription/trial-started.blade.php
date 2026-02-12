@extends('emails.text.layout')

@section('content')
Your trial has started

Hi {{ $user->name ?? 'there' }},

Your {{ $planName ?? 'subscription' }} trial is now active.
@if(!empty($trialEndsAt))
Trial ends: {{ $trialEndsAt }}
@endif

Included features:
@if(isset($features) && is_array($features) && count($features))
@foreach($features as $feature)
- {{ $feature }}
@endforeach
@else
- Explore all premium features
- Complete your profile
- Start using the app
@endif

Start your trial:
{{ config('app.url') }}/dashboard
@endsection
