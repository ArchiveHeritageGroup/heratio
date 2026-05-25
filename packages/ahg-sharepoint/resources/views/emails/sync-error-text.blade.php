{{ __('SharePoint sync error') }}
=================================

{{ __('A SharePoint sync run encountered errors. Manual intervention may be required.') }}

{{ __('Connection') }}:    {{ $ctx['connection_name'] ?? '' }}
@if (! empty($ctx['site_url']))
{{ __('Site') }}:          {{ $ctx['site_url'] }}
@endif
{{ __('Error kind') }}:    {{ $ctx['error_kind'] ?? 'other' }}
{{ __('Failed items') }}:  {{ $ctx['failed_items'] ?? 0 }}
@if (! empty($ctx['last_success_at']))
{{ __('Last success') }}:  {{ $ctx['last_success_at'] }}
@endif
@if (! empty($ctx['run_id']))
{{ __('Run ID') }}:        {{ $ctx['run_id'] }}
@endif

@if (! empty($ctx['error_message']))
{{ __('Details') }}:
{{ $ctx['error_message'] }}
@endif

@if (! empty($ctx['dashboard_url']))
{{ __('Open sync dashboard:') }} {{ $ctx['dashboard_url'] }}
@endif
