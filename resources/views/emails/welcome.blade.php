@extends('emails.layout')

@section('content')
    <h1>Welcome to {{ config('app.name') }}! 🎉</h1>
    
    <p>Hi {{ $user->name ?? 'there' }},</p>
    
    <p>Thanks for signing up! We're excited to have you on board.</p>
    
    <p>Here are a few things you can do to get started:</p>
    
    <ul style="margin: 16px 0; padding-left: 24px; color: #334155;">
        <li style="margin-bottom: 8px;">Complete your profile settings</li>
        <li style="margin-bottom: 8px;">Create or join a team</li>
        <li style="margin-bottom: 8px;">Explore our features</li>
    </ul>
    
    <div class="text-center">
        <a href="{{ config('app.url') }}/dashboard" class="btn">
            Go to Dashboard
        </a>
    </div>
    
    <p class="muted mt-4">
        If you have any questions, feel free to reach out to our support team. We're here to help!
    </p>
@endsection
