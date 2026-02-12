@extends('emails.text.layout')

@section('content')
Test Email

{{ $messageText }}

If this looks right, your provider configuration is working.
@endsection
