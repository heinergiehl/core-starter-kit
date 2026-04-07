@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">{{ __('Your trial has started') }}</h1>

    <p>{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}</p>

    <p>{{ __('Your :plan trial is now active.', ['plan' => $planName ?? __('subscription')]) }}</p>

    <div style="background-color: #eef2ff; border-radius: 8px; padding: 16px; margin: 24px 0; border-left: 4px solid #6366f1;">
        <p style="margin: 0; color: #3730a3; font-weight: 600;">
            {{ __('Trial started') }}
        </p>
        @if(!empty($trialEndsAt))
            <p style="margin: 8px 0 0; color: #3730a3;">
                {{ __('Trial ends') }}: {{ $trialEndsAt }}
            </p>
        @endif
    </div>

    <p>{{ __('You have full access to your plan features during the trial:') }}</p>

    <ul style="margin: 16px 0; padding-left: 24px; color: #334155;">
        @if(isset($features) && is_array($features) && count($features))
            @foreach($features as $feature)
                <li style="margin-bottom: 8px;">{{ $feature }}</li>
            @endforeach
        @else
            <li style="margin-bottom: 8px;">{{ __('Explore all premium features') }}</li>
            <li style="margin-bottom: 8px;">{{ __('Complete your profile') }}</li>
            <li style="margin-bottom: 8px;">{{ __('Start using the app') }}</li>
        @endif
    </ul>

    <x-email.button :href="config('app.url') . '/dashboard'">
        {{ __('Start Your Trial') }}
    </x-email.button>

    <p style="margin: 16px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
        {{ __('You can manage your subscription anytime from your account settings.') }}
    </p>
@endsection
