@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">Welcome to {{ config('app.name') }}!</h1>

    <p>Hi {{ $user->name ?? 'there' }},</p>

    <p>Thanks for signing up! We are excited to have you on board.</p>

    <p>Here are a few things you can do to get started:</p>

    <ul style="margin: 16px 0; padding-left: 24px; color: #334155;">
        <li style="margin-bottom: 8px;">Complete your profile settings</li>
        <li style="margin-bottom: 8px;">Choose a plan that fits your needs</li>
        <li style="margin-bottom: 8px;">Explore our features</li>
    </ul>

    <x-email.button :href="config('app.url') . '/dashboard'">
        Go to Dashboard
    </x-email.button>

    <p style="margin: 16px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
        If you have any questions, feel free to reach out to our support team. We are here to help.
    </p>
@endsection
