@extends('emails.text.layout')

@section('content')
Welcome to {{ config('app.name') }}!

Hi {{ $user->name ?? 'there' }},

Thanks for signing up. We are excited to have you on board.

Here are a few things you can do to get started:
- Complete your profile settings
- Choose a plan that fits your needs
- Explore our features

Go to Dashboard:
{{ config('app.url') }}/dashboard

If you have any questions, feel free to reach out to our support team.
@endsection
