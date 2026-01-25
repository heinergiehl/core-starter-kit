@extends('emails.layout')

@section('content')
    <h1>Test Email</h1>

    <p>{{ $messageText }}</p>

    <p class="muted mt-4">
        If this looks right, your provider configuration is working.
    </p>
@endsection
