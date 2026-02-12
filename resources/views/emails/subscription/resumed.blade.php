@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">Welcome back</h1>

    <p>Hi {{ $user->name ?? 'there' }},</p>

    <p>Great news. Your <strong>{{ $planName ?? 'subscription' }}</strong> has been resumed.</p>

    <div style="background-color: #d1fae5; border-radius: 8px; padding: 16px; margin: 24px 0; border-left: 4px solid #10b981;">
        <p style="margin: 0; color: #065f46; font-weight: 600;">
            Your subscription is now active
        </p>
        <p style="margin: 8px 0 0; color: #065f46; font-size: 14px;">
            You have full access to all your features again.
        </p>
    </div>

    <p>Thank you for continuing with us. We are happy to have you back.</p>

    <x-email.button :href="config('app.url') . '/billing'">
        View Your Subscription
    </x-email.button>
@endsection
