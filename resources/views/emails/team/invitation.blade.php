@extends('emails.layout')

@section('content')
    <h1>You're invited to join {{ $teamName ?? 'a team' }}! 🤝</h1>
    
    <p>Hi there,</p>
    
    <p><strong>{{ $inviterName ?? 'Someone' }}</strong> has invited you to join their workspace on {{ config('app.name') }}.</p>
    
    <div style="background-color: #eff6ff; border-radius: 8px; padding: 20px; margin: 24px 0; text-align: center;">
        <p style="margin: 0 0 8px; color: #1e40af; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em;">
            Team Invitation
        </p>
        <p style="margin: 0; color: #1e3a8a; font-size: 24px; font-weight: 700;">
            {{ $teamName ?? 'Team' }}
        </p>
        @if(isset($role))
            <p style="margin: 8px 0 0; color: #3b82f6; font-size: 14px;">
                Role: {{ ucfirst($role) }}
            </p>
        @endif
    </div>
    
    <div class="text-center">
        <a href="{{ $inviteUrl ?? config('app.url') }}" class="btn">
            Accept Invitation
        </a>
    </div>
    
    <p class="muted mt-4">
        This invitation will expire in {{ $expiresIn ?? '7 days' }}. If you weren't expecting this invite, you can safely ignore this email.
    </p>
@endsection
