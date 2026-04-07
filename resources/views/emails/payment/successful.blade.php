@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; line-height: 1.3; color: #1e293b;">{{ __('Payment Successful') }}</h1>

    <p>{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}</p>

    <p>{{ __('We have received your payment for :plan.', ['plan' => $planName ?? __('Product')]) }}</p>

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

    @if(isset($receiptUrl))
        <x-email.button :href="$receiptUrl">
            {{ __('View Receipt') }}
        </x-email.button>
    @endif

    <p style="margin: 16px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
        {{ __('Thank you for your purchase. If you have any questions, feel free to reply to this email.') }}
    </p>
@endsection
