@extends('emails.layout')

@section('content')
    <h1>We're sorry to see you go</h1>
    
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
    
    <div class="text-center">
        <a href="{{ config('app.url') }}/billing" class="btn">
            Reactivate Subscription
        </a>
    </div>
    
    <p class="muted mt-4">
        We'd love to hear your feedback. If there's anything we could have done better, please let us know.
    </p>
@endsection
