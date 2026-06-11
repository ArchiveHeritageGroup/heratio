{{--
  Heratio - public BROWSE of provenance-verified records (issue #1209, north star).

  The walkable verifiable corpus: a paginated list of the PUBLISHED records that
  actually carry content credentials, each with an honest status badge and a
  link to its /authenticity/{id} report. Read-only, jurisdiction-neutral, and
  honestly framed - a badge attests to a file's HISTORY, never that its content
  is "true". Reuses the existing /authenticity + /trust pages for drill-down; it
  reimplements no verification. Bounded + paginated (never the whole catalogue).

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Provenance-verified records'))
@section('body-class', 'c2pa verified-records')

@section('content')
@php
  $filter   = $corpus['filter'] ?? 'all';
  $records  = $corpus['records'] ?? [];
  $total    = (int) ($corpus['total'] ?? 0);
  $page     = (int) ($corpus['page'] ?? 1);
  $lastPage = (int) ($corpus['last_page'] ?? 1);
  $from     = (int) ($corpus['from'] ?? 0);
  $to       = (int) ($corpus['to'] ?? 0);
  $hasPrev  = (bool) ($corpus['has_prev'] ?? false);
  $hasNext  = (bool) ($corpus['has_next'] ?? false);
  $perPage  = (int) ($corpus['per_page'] ?? 24);

  // Preserve the active filter across pagination links; never the page number.
  $filterQ = $filter !== 'all' ? ('&filter=' . urlencode($filter)) : '';
@endphp

{{-- Hero: what this list is, in plain language. --}}
<div class="card mb-4 border-2" style="border-color:var(--ahg-primary)">
  <div class="card-body p-4">
    <h1 class="mb-2"><i class="fas fa-list-check me-2"></i>{{ __('Provenance-verified records') }}</h1>
    <p class="lead mb-2">
      {{ __('Browse the published records here that carry content credentials - the part of the collection you can independently check.') }}
    </p>
    <p class="text-muted mb-0">
      {{ __('Each record below has a recorded, and often cryptographically signed, account of how its digital files were captured and handled. Open any record to see its full authenticity report and to re-verify its signatures live.') }}
    </p>
  </div>
</div>

{{-- Filter chips. --}}
<div class="mb-3 d-flex flex-wrap align-items-center gap-2">
  <span class="text-muted small me-1"><i class="fas fa-filter me-1"></i>{{ __('Show') }}:</span>
  @foreach(($corpus['filters'] ?? []) as $f)
    @php
      $fq    = ($f['key'] ?? 'all') !== 'all' ? ('?filter=' . urlencode($f['key'])) : '';
      $fLbl  = match($f['key'] ?? '') {
        'all'             => __('All credentialed'),
        'has-credentials' => __('Has credentials'),
        'signed'          => __('Signed'),
        'verified'        => __('Verified'),
        default           => $f['label'] ?? ($f['key'] ?? ''),
      };
    @endphp
    <a href="{{ url('/verified-records') }}{{ $fq }}"
       class="btn btn-sm {{ ($f['active'] ?? false) ? 'btn-primary' : 'btn-outline-secondary' }}">
      {{ $fLbl }}
    </a>
  @endforeach
</div>

@if($total > 0)
  <p class="text-muted small mb-3">
    {{ __('Showing') }} {{ number_format($from) }}&ndash;{{ number_format($to) }}
    {{ __('of') }} {{ number_format($total) }}
    {{ trans_choice('record|records', $total) }}.
  </p>
@endif

@if(empty($records))
  {{-- Honest empty state - never an error, never an overclaim. --}}
  <div class="card mb-4">
    <div class="card-body text-center py-5">
      <div class="display-6 text-muted mb-2"><i class="fas fa-inbox"></i></div>
      <h2 class="h5">{{ __('No provenance-verified records yet') }}</h2>
      @if($filter !== 'all')
        <p class="text-muted mb-3">{{ __('No records match this filter yet. Try a broader view.') }}</p>
        <a href="{{ url('/verified-records') }}" class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-list me-1"></i>{{ __('Show all credentialed records') }}
        </a>
      @else
        <p class="text-muted mb-0">{{ __('As digitised material is signed with content credentials, the verifiable records will appear here. Nothing has been published with content credentials yet - that does not mean anything is wrong, only that none have been captured so far.') }}</p>
      @endif
    </div>
  </div>
@else
  {{-- The verifiable corpus, as a responsive card grid. --}}
  <div class="row g-3 mb-4">
    @foreach($records as $r)
      @php
        $title  = ($r['title'] ?? null) !== null && $r['title'] !== '' ? $r['title'] : __('Untitled record');
        $ident  = $r['identifier'] ?? null;
        $url    = $r['authenticity_url'] ?? url('/authenticity/' . (int) ($r['information_object_id'] ?? 0));
        $bClass = $r['badge_class'] ?? 'bg-secondary';
        $bLabel = match($r['badge'] ?? 'recorded') {
          'verified' => __('Signed and verified'),
          'signed'   => __('Signed'),
          'failed'   => __('Signature check failed'),
          default    => __('Content credentials recorded'),
        };
        $bIcon = match($r['badge'] ?? 'recorded') {
          'verified' => 'fa-shield-alt',
          'signed'   => 'fa-certificate',
          'failed'   => 'fa-exclamation-triangle',
          default    => 'fa-stamp',
        };
      @endphp
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="mb-2">
              <span class="badge {{ $bClass }}"><i class="fas {{ $bIcon }} me-1"></i>{{ $bLabel }}</span>
            </div>
            <h2 class="h6 mb-1">
              <a href="{{ $url }}" class="text-decoration-none">{{ $title }}</a>
            </h2>
            @if($ident)
              <div class="text-muted small mb-2"><code>{{ $ident }}</code></div>
            @endif
            <div class="text-muted small mb-3">
              <i class="fas fa-certificate me-1"></i>{{ trans_choice(':count content credential|:count content credentials', (int) ($r['credentials'] ?? 0), ['count' => number_format((int) ($r['credentials'] ?? 0))]) }}
              @if((int) ($r['signed'] ?? 0) > 0)
                <span class="mx-1">&middot;</span>{{ number_format((int) $r['signed']) }} {{ __('signed') }}
              @endif
            </div>
            <div class="mt-auto">
              <a href="{{ $url }}" class="btn btn-sm btn-outline-primary w-100">
                <i class="fas fa-search me-1"></i>{{ __('View authenticity report') }}
              </a>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Bounded prev / next pagination. The active filter is preserved; the page
       number is not over-cooked into the URL on the first page. --}}
  @if($lastPage > 1)
    <nav aria-label="{{ __('Verified records pages') }}" class="mb-4">
      <ul class="pagination justify-content-center mb-0">
        <li class="page-item {{ $hasPrev ? '' : 'disabled' }}">
          <a class="page-link"
             href="{{ $hasPrev ? url('/verified-records') . '?page=' . ($page - 1) . $filterQ : '#' }}"
             @if(!$hasPrev) tabindex="-1" aria-disabled="true" @endif>
            <i class="fas fa-chevron-left me-1"></i>{{ __('Previous') }}
          </a>
        </li>
        <li class="page-item disabled">
          <span class="page-link">{{ __('Page') }} {{ number_format($page) }} {{ __('of') }} {{ number_format($lastPage) }}</span>
        </li>
        <li class="page-item {{ $hasNext ? '' : 'disabled' }}">
          <a class="page-link"
             href="{{ $hasNext ? url('/verified-records') . '?page=' . ($page + 1) . $filterQ : '#' }}"
             @if(!$hasNext) tabindex="-1" aria-disabled="true" @endif>
            {{ __('Next') }}<i class="fas fa-chevron-right ms-1"></i>
          </a>
        </li>
      </ul>
    </nav>
  @endif
@endif

{{-- Honest framing - the standing caveat, identical to the trust dashboard. --}}
<div class="alert alert-light border d-flex align-items-start mb-4" role="note">
  <i class="fas fa-info-circle me-2 mt-1"></i>
  <div class="small mb-0">{{ $corpus['caveat'] ?? '' }}</div>
</div>

{{-- Cross-links + machine surface. --}}
<div class="d-flex flex-wrap gap-3 align-items-center small">
  @if(\Route::has('c2pa.trust'))
    <a href="{{ route('c2pa.trust') }}"><i class="fas fa-chart-pie me-1"></i>{{ __('Trust at a glance (whole-collection summary)') }}</a>
  @endif
  @if(\Route::has('c2pa.authenticity'))
    <a href="{{ route('c2pa.authenticity') }}"><i class="fas fa-certificate me-1"></i>{{ __('About content credentials') }}</a>
  @endif
  <a href="{{ url('/verified-records.json') . ($filter !== 'all' ? ('?filter=' . urlencode($filter)) : '') }}">
    <i class="fas fa-code me-1"></i>{{ __('This list as JSON') }}
  </a>
</div>
@endsection
