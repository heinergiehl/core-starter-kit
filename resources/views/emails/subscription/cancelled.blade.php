@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">We are sorry to see you go</h1>

    <p>Hi {{ $user->name ?? 'there' }},</p>

    <p>Your <strong>{{ $planName ?? 'subscription' }}</strong> has been cancelled.</p>

    <div style="background-color: #fef3c7; border-radius: 8px; padding: 16px; margin: 24px 0; border-left: 4px solid #f59e0b;">
        <p style="margin: 0; color: #92400e; font-weight: 600;">
            Access Until: {{ $accessUntil ?? 'End of billing period' }}
        </p>
        <p style="margin: 8px 0 0; color: #92400e; font-size: 14px;">
            You can continue using your current features until this date.
        </p>
    </div>

    <p>If you cancelled by mistake or changed your mind, you can reactivate your subscription at any time.</p>

    <x-email.button :href="config('app.url') . '/billing'">
        Reactivate Subscription
    </x-email.button>

    <p style="margin: 16px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
        We would love to hear your feedback. If there is anything we could have done better, please let us know.
    </p>
@endsection
