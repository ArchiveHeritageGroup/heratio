{{ __('DOI mint failed') }}
===========================

{{ __('The DOI registrar rejected the mint request for:') }}

  {{ $ctx['title'] ?? '' }}

@if (! empty($ctx['error_code']))
{{ __('Error code') }}:    {{ $ctx['error_code'] }}
@endif
@if (! empty($ctx['error_message']))
{{ __('Error message') }}: {{ $ctx['error_message'] }}
@endif
@if (! empty($ctx['attempted_at']))
{{ __('Attempted at') }}:  {{ $ctx['attempted_at'] }}
@endif

@if (! empty($ctx['retry_url']))
{{ __('Retry mint:') }} {{ $ctx['retry_url'] }}
@elseif (! empty($ctx['object_url']))
{{ __('Open record:') }} {{ $ctx['object_url'] }}
@endif
