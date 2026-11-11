@extends('emails._layout', ['subject' => 'Your verification code'])

@section('content')
    <h2 style="margin-top:0;">{{ __('Your verification code') }}</h2>

    <p>{{ __('Hello,') }}</p>

    <p>{{ __('Use the code below to complete your sign-in to :value1:', ['value1' => config('app.name', 'Heratio')]) }}</p>

    <p style="text-align: center; margin: 30px 0;">
        <span style="display:inline-block; font-size: 28px; letter-spacing: 0.4em; font-weight: 700; padding: 12px 20px; border: 1px solid #ddd; border-radius: 4px; background: #f8f8f8;">{{ $code }}</span>
    </p>

    <p>{{ __('Destination:') }} <strong>{{ $label }}</strong></p>

    <p class="muted">{{ __('This code expires in :value1 minutes. If you did not request it, you can ignore this message - your account is still safe.', ['value1' => $ttlMinutes]) }}</p>
@endsection
