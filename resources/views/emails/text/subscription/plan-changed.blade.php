@extends('emails.text.layout')

@section('content')
Your plan has been updated

Hi {{ $user->name ?? 'there' }},

@if(!empty($previousPlanName))
Your plan changed from {{ $previousPlanName }} to {{ $newPlanName ?? 'your new plan' }}.
@else
Your plan is now {{ $newPlanName ?? 'updated' }}.
@endif

@if(!empty($effectiveDate))
Effective date: {{ $effectiveDate }}
@endif

Review billing details:
{{ config('app.url') }}/billing
@endsection
