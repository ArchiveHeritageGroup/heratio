@extends('emails._layout', ['subject' => 'Password Reset Request'])

@section('content')
    <h2 style="margin-top:0;">{{ __('Password Reset Request') }}</h2>

    <p>Hello {{ $username }},</p>

    <p>{{ __('You have requested to reset your password. Click the button below to set a new password:') }}</p>

    <p style="text-align: center; margin: 30px 0;">
        <a href="{{ $resetUrl }}" class="btn">{{ __('Reset Password') }}</a>
    </p>

    <p>{{ __('If the button above does not work, copy and paste the following URL into your browser:') }}</p>
    <p style="word-break: break-all;"><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></p>

    <p class="muted">{{ __('This link will expire in 1 hour. If you did not request a password reset, you can safely ignore this email.') }}</p>
@endsection
