{{ __('DOI minted successfully') }}
===================================

{{ __('A DOI has been minted for:') }}

  {{ $ctx['title'] ?? '' }}

DOI:      {{ $ctx['doi'] ?? '' }}
@if (! empty($ctx['resolver_url']))
{{ __('Resolver') }}: {{ $ctx['resolver_url'] }}
@endif

@if (! empty($ctx['object_url']))
{{ __('Open record:') }} {{ $ctx['object_url'] }}
@endif
