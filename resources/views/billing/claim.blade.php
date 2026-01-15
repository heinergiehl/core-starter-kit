@extends('layouts.marketing')

@section('title', __('Purchase confirmation') . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('Confirm your purchase and activate your workspace.'))

@section('content')
    <section class="py-16">
        <div class="glass-panel rounded-3xl p-8 text-center">
            @php
                $messages = [
                    'pending' => [
                        'title' => __('Payment pending'),
                        'body' => __('We are still confirming your payment. Please check your email in a few minutes.'),
                    ],
                    'claimed' => [
                        'title' => __('Purchase already claimed'),
                        'body' => __('This purchase has already been claimed. Please log in to continue.'),
                    ],
                    'missing_email' => [
                        'title' => __('Email missing'),
                        'body' => __('We could not find an email for this purchase. Please contact support.'),
                    ],
                    'invalid' => [
                        'title' => __('Invalid link'),
                        'body' => __('This confirmation link is invalid or has expired.'),
                    ],
                    'error' => [
                        'title' => __('Something went wrong'),
                        'body' => __('We could not claim this purchase. Please contact support.'),
                    ],
                ];
                $message = $messages[$status ?? 'invalid'] ?? $messages['invalid'];
            @endphp

            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-secondary">{{ __('Checkout') }}</p>
            <h1 class="mt-3 font-display text-3xl">{{ $message['title'] }}</h1>
            <p class="mt-3 text-sm text-ink/70">{{ $message['body'] }}</p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('login') }}" class="rounded-full border border-ink/15 px-4 py-2 text-sm font-semibold text-ink/70 hover:text-ink">
                    {{ __('Log in') }}
                </a>
                <a href="{{ route('pricing') }}" class="rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90">
                    {{ __('View pricing') }}
                </a>
            </div>
        </div>
    </section>
@endsection
