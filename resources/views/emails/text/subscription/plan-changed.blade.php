@extends('emails.text.layout')

@section('content')
{{ __('Your plan has been updated') }}

{{ __('Hi :name,', ['name' => $user->name ?? __('there')]) }}

@if(!empty($previousPlanName))
{{ __('Your plan has been changed from :oldPlan to :newPlan.', ['oldPlan' => $previousPlanName, 'newPlan' => $newPlanName ?? __('your new plan')]) }}
@else
{{ __('Your plan is now :plan.', ['plan' => $newPlanName ?? __('updated')]) }}
@endif

@if(!empty($effectiveDate))
{{ __('Effective date:') }} {{ $effectiveDate }}
@endif

{{ __('Review Billing Details') }}:
{{ config('app.url') }}/billing
@endsection
