@extends('emails._layout', ['subject' => __('DOI mint failed')])

@section('content')
    <h2 style="margin-top:0; color: #b00020;">{{ __('DOI mint failed') }}</h2>

    <p>{{ __('The DOI registrar rejected the mint request for:') }}</p>

    <p><strong>{{ $ctx['title'] ?? '' }}</strong></p>

    <ul>
        @if (! empty($ctx['error_code']))
            <li><strong>{{ __('Error code') }}:</strong> <code>{{ $ctx['error_code'] }}</code></li>
        @endif
        @if (! empty($ctx['error_message']))
            <li><strong>{{ __('Error message') }}:</strong> {{ $ctx['error_message'] }}</li>
        @endif
        @if (! empty($ctx['attempted_at']))
            <li><strong>{{ __('Attempted at') }}:</strong> {{ $ctx['attempted_at'] }}</li>
        @endif
    </ul>

    <p>{{ __('Common causes: missing required metadata (title, creator, publisher), URL not reachable from the registrar, or rate-limit / credential issue.') }}</p>

    @if (! empty($ctx['retry_url']))
        <p style="text-align: center; margin: 24px 0;">
            <a href="{{ $ctx['retry_url'] }}" class="btn">{{ __('Retry mint') }}</a>
        </p>
    @elseif (! empty($ctx['object_url']))
        <p style="text-align: center; margin: 24px 0;">
            <a href="{{ $ctx['object_url'] }}" class="btn">{{ __('Open record') }}</a>
        </p>
    @endif
@endsection
