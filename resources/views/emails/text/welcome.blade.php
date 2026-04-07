@extends('emails.text.layout')

@section('content')
{{ __('Welcome to :appName!', ['appName' => config('app.name')]) }}

{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}

{{ __('Thanks for signing up! We\'re excited to have you on board.') }}

{{ __('Here are a few things you can do to get started:') }}
- {{ __('Complete your profile settings') }}
- {{ __('Choose a plan that fits your needs') }}
- {{ __('Explore our features') }}

{{ __('Go to Dashboard') }}:
{{ config('app.url') }}/dashboard

{{ __('If you have any questions, feel free to reach out to our support team.') }}
@endsection
