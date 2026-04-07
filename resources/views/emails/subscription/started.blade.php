@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">{{ __('Your subscription is active') }}</h1>

    <p>{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}</p>

    <p>{{ __('Great news. Your :plan is now active.', ['plan' => $planName ?? __('subscription')]) }}</p>

    <div style="background-color: #f0fdf4; border-radius: 8px; padding: 16px; margin: 24px 0; border-left: 4px solid #22c55e;">
        <p style="margin: 0; color: #166534; font-weight: 600;">
            {{ __('Payment confirmed') }}
        </p>
        @if(isset($amount) && isset($currency))
            <p style="margin: 8px 0 0; color: #166534;">
                {{ __('Amount') }}: {{ $currency }} {{ number_format($amount / 100, 2) }}
            </p>
        @endif
    </div>

    <p>{{ __('You now have access to all the features included in your plan:') }}</p>

    <ul style="margin: 16px 0; padding-left: 24px; color: #334155;">
        @if(isset($features) && is_array($features))
            @foreach($features as $feature)
                <li style="margin-bottom: 8px;">{{ $feature }}</li>
            @endforeach
        @else
            <li style="margin-bottom: 8px;">{{ __('Access all premium features') }}</li>
            <li style="margin-bottom: 8px;">{{ __('Priority support') }}</li>
            <li style="margin-bottom: 8px;">{{ __('Regular updates') }}</li>
        @endif
    </ul>

    <x-email.button :href="config('app.url') . '/dashboard'">
        {{ __('Start Using :appName', ['appName' => config('app.name')]) }}
    </x-email.button>

    <p style="margin: 16px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
        {{ __('You can manage your subscription anytime from your account settings.') }}
    </p>
@endsection
