@extends('emails.layout')

@section('content')
    <h1>Your subscription is active! 🚀</h1>
    
    <p>Hi {{ $user->name ?? 'there' }},</p>
    
    <p>Great news! Your <strong>{{ $planName ?? 'subscription' }}</strong> is now active.</p>
    
    <div style="background-color: #f0fdf4; border-radius: 8px; padding: 16px; margin: 24px 0; border-left: 4px solid #22c55e;">
        <p style="margin: 0; color: #166534; font-weight: 600;">
            ✓ Payment confirmed
        </p>
        @if(isset($amount) && isset($currency))
            <p style="margin: 8px 0 0; color: #166534;">
                Amount: {{ $currency }} {{ number_format($amount / 100, 2) }}
            </p>
        @endif
    </div>
    
    <p>You now have access to all the features included in your plan. Here's what you can do:</p>
    
    <ul style="margin: 16px 0; padding-left: 24px; color: #334155;">
        @if(isset($features) && is_array($features))
            @foreach($features as $feature)
                <li style="margin-bottom: 8px;">{{ $feature }}</li>
            @endforeach
        @else
            <li style="margin-bottom: 8px;">Access all premium features</li>
            <li style="margin-bottom: 8px;">Priority support</li>
            <li style="margin-bottom: 8px;">Regular updates</li>
        @endif
    </ul>
    
    <div class="text-center">
        <a href="{{ config('app.url') }}/dashboard" class="btn">
            Start Using {{ config('app.name') }}
        </a>
    </div>
    
    <p class="muted mt-4">
        You can manage your subscription anytime from your account settings.
    </p>
@endsection
