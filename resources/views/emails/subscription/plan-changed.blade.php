@extends('emails.layout')

@section('content')
    <h1>Your plan has been updated</h1>

    <p>Hi {{ $user->name ?? 'there' }},</p>

    @if(!empty($previousPlanName))
        <p>Your plan changed from <strong>{{ $previousPlanName }}</strong> to <strong>{{ $newPlanName ?? 'your new plan' }}</strong>.</p>
    @else
        <p>Your plan is now <strong>{{ $newPlanName ?? 'updated' }}</strong>.</p>
    @endif

    @if(!empty($effectiveDate))
        <div style="background-color: #eef2ff; border-radius: 8px; padding: 16px; margin: 24px 0; border-left: 4px solid #6366f1;">
            <p style="margin: 0; color: #3730a3; font-weight: 600;">
                Effective Date: {{ $effectiveDate }}
            </p>
        </div>
    @endif

    <p>If you have any questions about this change, you can review your billing details below.</p>

    <div class="text-center">
        <a href="{{ config('app.url') }}/billing" class="btn">
            Manage Billing
        </a>
    </div>

    <p class="muted mt-4">
        Thank you for continuing with {{ config('app.name') }}.
    </p>
@endsection
