@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">{{ __('Welcome back') }}</h1>

    <p>{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}</p>

    <p>{{ __('Great news. Your :plan has been resumed.', ['plan' => $planName ?? __('subscription')]) }}</p>

    <div style="background-color: #d1fae5; border-radius: 8px; padding: 16px; margin: 24px 0; border-left: 4px solid #10b981;">
        <p style="margin: 0; color: #065f46; font-weight: 600;">
            {{ __('Your subscription is now active') }}
        </p>
        <p style="margin: 8px 0 0; color: #065f46; font-size: 14px;">
            {{ __('You have full access to all your features again.') }}
        </p>
    </div>

    <p>{{ __('Thank you for continuing with us. We are happy to have you back.') }}</p>

    <x-email.button :href="config('app.url') . '/billing'">
        {{ __('View Your Subscription') }}
    </x-email.button>
@endsection
