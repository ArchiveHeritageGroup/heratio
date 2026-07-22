{{--
  Scope of an access request, rendered from the columns the service selects
  out of access_request_scope.

  Expects: $request (row with scope_type and, when scoped, scope_object_id /
  scope_object_title / scope_include_descendants).

  'single' is the historical default that every request carried before the
  scope selector existed, so it is shown neutrally rather than as a claim
  about what the requester actually wanted.
--}}
@php
    $scopeType = $request->scope_type ?? 'single';
    $scopeTitle = trim((string) ($request->scope_object_title ?? ''));
    $scopeId = $request->scope_object_id ?? null;
@endphp

@if($scopeType === 'all')
    <span class="badge bg-dark"><i class="fas fa-globe me-1" aria-hidden="true"></i>{{ __('Everything') }}</span>
@elseif($scopeType === 'collection')
    <span class="badge bg-primary"><i class="fas fa-boxes-stacked me-1" aria-hidden="true"></i>{{ __('Collection') }}</span>
    @if($scopeTitle !== '')
        <span class="d-block small text-muted">{{ \Illuminate\Support\Str::limit($scopeTitle, 60) }}</span>
    @endif
    @if(!empty($request->scope_include_descendants))
        <span class="d-block small text-muted">{{ __('includes everything inside it') }}</span>
    @endif
@elseif($scopeType === 'item')
    <span class="badge bg-info text-dark"><i class="fas fa-file me-1" aria-hidden="true"></i>{{ __('Single item') }}</span>
    @if($scopeTitle !== '')
        <span class="d-block small text-muted">{{ \Illuminate\Support\Str::limit($scopeTitle, 60) }}</span>
    @endif
@else
    <span class="badge bg-secondary">{{ __('Not specified') }}</span>
@endif
