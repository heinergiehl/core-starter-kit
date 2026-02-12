@extends('emails.text.layout')

@section('content')
Your subscription is active

Hi {{ $user->name ?? 'there' }},

Your {{ $planName ?? 'subscription' }} is now active.
@if(isset($amount) && isset($currency))
Amount: {{ $currency }} {{ number_format($amount / 100, 2) }}
@endif

Included features:
@if(isset($features) && is_array($features) && count($features))
@foreach($features as $feature)
- {{ $feature }}
@endforeach
@else
- Access all premium features
- Priority support
- Regular updates
@endif

Start using {{ config('app.name') }}:
{{ config('app.url') }}/dashboard
@endsection
