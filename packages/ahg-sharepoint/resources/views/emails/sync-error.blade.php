@extends('emails._layout', ['subject' => __('SharePoint sync error')])

@section('content')
    <h2 style="margin-top:0; color: #b00020;">{{ __('SharePoint sync error') }}</h2>

    <p>{{ __('A SharePoint sync run encountered errors. Manual intervention may be required.') }}</p>

    <ul>
        <li><strong>{{ __('Connection') }}:</strong> {{ $ctx['connection_name'] ?? '' }}</li>
        @if (! empty($ctx['site_url']))
            <li><strong>{{ __('Site') }}:</strong> <a href="{{ $ctx['site_url'] }}">{{ $ctx['site_url'] }}</a></li>
        @endif
        <li><strong>{{ __('Error kind') }}:</strong> <code>{{ $ctx['error_kind'] ?? 'other' }}</code></li>
        <li><strong>{{ __('Failed items') }}:</strong> {{ $ctx['failed_items'] ?? 0 }}</li>
        @if (! empty($ctx['last_success_at']))
            <li><strong>{{ __('Last success') }}:</strong> {{ $ctx['last_success_at'] }}</li>
        @endif
        @if (! empty($ctx['run_id']))
            <li><strong>{{ __('Run ID') }}:</strong> <code>{{ $ctx['run_id'] }}</code></li>
        @endif
    </ul>

    @if (! empty($ctx['error_message']))
        <p><strong>{{ __('Details') }}:</strong></p>
        <pre style="background:#f4f4f4; padding:12px; border-radius:4px; overflow:auto;">{{ $ctx['error_message'] }}</pre>
    @endif

    @if (! empty($ctx['dashboard_url']))
        <p style="text-align: center; margin: 24px 0;">
            <a href="{{ $ctx['dashboard_url'] }}" class="btn">{{ __('Open sync dashboard') }}</a>
        </p>
    @endif

    <p class="muted">{{ __('Repeated auth failures usually mean the app registration token has expired. Network errors typically clear on the next scheduled run.') }}</p>
@endsection
