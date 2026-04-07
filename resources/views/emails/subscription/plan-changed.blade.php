@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">{{ __('Your plan has been updated') }}</h1>

    <p>{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}</p>

    @if(!empty($previousPlanName))
        <p>{{ __('Your plan changed from :oldPlan to :newPlan.', ['oldPlan' => $previousPlanName, 'newPlan' => $newPlanName ?? __('your new plan')]) }}</p>
    @else
        <p>{{ __('Your plan is now :plan.', ['plan' => $newPlanName ?? __('updated')]) }}</p>
    @endif

    @if(!empty($effectiveDate))
        <div style="background-color: #eef2ff; border-radius: 8px; padding: 16px; margin: 24px 0; border-left: 4px solid #6366f1;">
            <p style="margin: 0; color: #3730a3; font-weight: 600;">
                {{ __('Effective Date') }}: {{ $effectiveDate }}
            </p>
        </div>
    @endif

    <p>{{ __('If you have any questions about this change, you can review your billing details below.') }}</p>

    <x-email.button :href="config('app.url') . '/billing'">
        {{ __('Manage Billing') }}
    </x-email.button>

    <p style="margin: 16px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
        {{ __('Thank you for continuing with :appName.', ['appName' => config('app.name')]) }}
    </p>
@endsection
