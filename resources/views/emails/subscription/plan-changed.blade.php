@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">Your plan has been updated</h1>

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

    <x-email.button :href="config('app.url') . '/billing'">
        Manage Billing
    </x-email.button>

    <p style="margin: 16px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
        Thank you for continuing with {{ config('app.name') }}.
    </p>
@endsection
