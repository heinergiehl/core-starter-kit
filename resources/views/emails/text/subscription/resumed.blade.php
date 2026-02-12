@extends('emails.text.layout')

@section('content')
Welcome back

Hi {{ $user->name ?? 'there' }},

Your {{ $planName ?? 'subscription' }} has been resumed.
You now have full access to your features again.

View your subscription:
{{ config('app.url') }}/billing
@endsection
