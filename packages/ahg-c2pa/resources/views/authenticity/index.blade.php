{{--
  Heratio - public "Authenticity" front door (issue #1209, north star).

  The visible entry point to the C2PA content-credentials layer. Read-only,
  plain-language, jurisdiction-neutral. Reuses the existing /verify pages for
  per-record verification - it does not reimplement signing or verify.

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Content Credentials'))
@section('body-class', 'c2pa authenticity')

@section('content')
@php
  $enabled   = $stats['enabled'] ?? false;
  $reason    = $stats['reason'] ?? null;
  $canSign   = $stats['can_sign'] ?? false;
  $coverage  = $stats['coverage_pct'] ?? 0.0;
@endphp

{{-- Hero: what Content Credentials are, in plain language. --}}
<div class="card mb-4 border-2" style="border-color:var(--ahg-primary)">
  <div class="card-body p-4">
    <h1 class="mb-2"><i class="fas fa-certificate me-2"></i>{{ __('Content Credentials') }}</h1>
    <p class="lead mb-2">
      {{ __('Every digitisation here can be cryptographically verified.') }}
    </p>
    <p class="text-muted mb-0">
      {{ __('When we digitise a primary source we record how, when and with what it was captured, then seal that record with a tamper-evident digital signature. Anyone can check, on any device, that what they are looking at has not been altered since it was created. This is the open C2PA standard for content provenance and authenticity - the same approach used to tell real media from fakes.') }}
    </p>
  </div>
</div>

@if(!$enabled)
  {{-- Graceful "not yet enabled" / "nothing signed yet" state. --}}
  <div class="card mb-4">
    <div class="card-body">
      <p class="mb-2">
        <span class="badge bg-secondary fs-6"><i class="fas fa-info-circle me-1"></i>{{ __('Not yet enabled') }}</span>
      </p>
      @if($reason === 'not-installed' || $reason === 'unavailable')
        <p class="mb-0">{{ __('The content-credentials layer is not active on this installation yet. Once it is enabled, digitised material will be signed and this page will show how much of the collection can be verified.') }}</p>
      @elseif($reason === 'unsigned-only')
        <p class="mb-0">{{ __('Provenance is being recorded for digitised material, but nothing has been cryptographically sealed yet. Verifiable content credentials will appear here once signing is switched on.') }}</p>
      @else
        <p class="mb-0">{{ __('No content credentials have been issued yet. As digitised material is signed, this page will show how much of the collection can be verified.') }}</p>
      @endif

      @unless($canSign)
        <p class="text-muted small mb-0 mt-2"><i class="fas fa-exclamation-triangle me-1"></i>{{ $stats['capability_summary'] ?? '' }}</p>
      @endunless
    </div>
  </div>
@else
  {{-- Coverage stats as cards. --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6" style="color:var(--ahg-primary)">{{ number_format($coverage, 1) }}%</div>
          <div class="text-muted small">{{ __('of master files verifiable') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6">{{ number_format($stats['covered_masters'] ?? 0) }}</div>
          <div class="text-muted small">{{ __('signed master files') }}</div>
          <div class="text-muted small">{{ __('of') }} {{ number_format($stats['total_masters'] ?? 0) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6">{{ number_format($stats['records_with_credentials'] ?? 0) }}</div>
          <div class="text-muted small">{{ __('records with content credentials') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6">{{ number_format($stats['signed_records'] ?? 0) }}</div>
          <div class="text-muted small">{{ __('signed provenance records') }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Signing identity + freshness. --}}
  @if(!empty($stats['issuers']) || !empty($stats['last_signed_at']))
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-key me-2"></i>{{ __('Signing identity') }}</div>
      <div class="card-body">
        <dl class="row mb-0">
          @if(!empty($stats['last_signed_at']))
            <dt class="col-sm-3">{{ __('Most recently signed') }}</dt>
            <dd class="col-sm-9">{{ $stats['last_signed_at'] }}</dd>
          @endif
          @if(!empty($stats['issuers']))
            <dt class="col-sm-3">{{ __('Issuing key(s)') }}</dt>
            <dd class="col-sm-9">
              @foreach($stats['issuers'] as $issuer)
                <span class="badge bg-light text-dark border me-1 mb-1">
                  <i class="fas fa-fingerprint me-1"></i><code>{{ $issuer['kid'] }}</code>
                  <span class="text-muted">&times;{{ number_format($issuer['count']) }}</span>
                </span>
              @endforeach
            </dd>
          @endif
        </dl>
        <p class="text-muted small mb-0 mt-2">{{ $stats['capability_summary'] ?? '' }}</p>
      </div>
    </div>
  @endif
@endif

{{-- Verify a record entry point - links to the existing /verify pages. --}}
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-shield-alt me-2"></i>{{ __('Verify a record') }}</div>
  <div class="card-body">
    <p class="mb-3">{{ __('Have a reference for a specific record? Check its authenticity directly.') }}</p>
    <form action="{{ url('/verify') }}" method="get" class="row g-2 align-items-end"
          onsubmit="var v=this.elements['ref'].value.trim(); if(v===''){return false;} window.location.href='{{ url('/verify') }}/'+encodeURI(v).replace(/%2F/gi,'/'); return false;">
      <div class="col-12 col-md-8">
        <label for="c2pa-ref" class="form-label small text-muted mb-1">{{ __('Record permalink or reference') }}</label>
        <input type="text" id="c2pa-ref" name="ref" class="form-control"
               placeholder="{{ __('e.g. fonds/series/item or a record id') }}">
      </div>
      <div class="col-12 col-md-4">
        <button type="submit" class="btn btn-primary w-100">
          <i class="fas fa-search me-1"></i>{{ __('Verify') }}
        </button>
      </div>
    </form>
    <p class="text-muted small mb-0 mt-2">
      <i class="fas fa-info-circle me-1"></i>{{ __('Tip: append a record permalink to /verify/, or use /verify/id/ followed by a numeric record id.') }}
    </p>
  </div>
</div>

{{-- How it works. --}}
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-cogs me-2"></i>{{ __('How it works') }}</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-3">
        <h2 class="h6"><span class="badge bg-secondary me-1">1</span>{{ __('Capture') }}</h2>
        <p class="text-muted small mb-0">{{ __('At digitisation we record who captured the source, when, and on what device and software.') }}</p>
      </div>
      <div class="col-md-3">
        <h2 class="h6"><span class="badge bg-secondary me-1">2</span>{{ __('Sign') }}</h2>
        <p class="text-muted small mb-0">{{ __('That record is sealed with an Ed25519 digital signature over the exact captured content.') }}</p>
      </div>
      <div class="col-md-3">
        <h2 class="h6"><span class="badge bg-secondary me-1">3</span>{{ __('Embed') }}</h2>
        <p class="text-muted small mb-0">{{ __('The signed credentials are embedded in the file or stored alongside it as a sidecar, following C2PA.') }}</p>
      </div>
      <div class="col-md-3">
        <h2 class="h6"><span class="badge bg-secondary me-1">4</span>{{ __('Verify') }}</h2>
        <p class="text-muted small mb-0">{{ __('On every view the signature is re-checked live - nothing is cached - so tampering is caught immediately.') }}</p>
      </div>
    </div>
    <p class="text-muted small mb-0 mt-3">
      <i class="fas fa-lock me-1"></i>{{ __('Open standard, no vendor lock-in: anyone can independently verify a Heratio content credential using the public C2PA tooling.') }}
    </p>
  </div>
</div>
@endsection
