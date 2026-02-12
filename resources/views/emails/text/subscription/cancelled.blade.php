@extends('emails.text.layout')

@section('content')
We are sorry to see you go

Hi {{ $user->name ?? 'there' }},

Your {{ $planName ?? 'subscription' }} has been cancelled.
Access until: {{ $accessUntil ?? 'End of billing period' }}

Reactivate anytime:
{{ config('app.url') }}/billing

If there is anything we could have done better, please let us know.
@endsection
