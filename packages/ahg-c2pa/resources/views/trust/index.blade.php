{{--
  Heratio - public collection-wide TRUST DASHBOARD (issue #1209, north star).

  "Trust at a glance": a read-only, jurisdiction-neutral summary of how much of
  everything published here carries verifiable authenticity. Big numbers + simple
  CSS bars (no charting library). Reuses the existing /verify and
  /authenticity/{id} pages for drill-down; it does not reimplement verification.
  Honest framing throughout: content credentials attest to a file's HISTORY, not
  that its content is true.

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Trust at a glance'))
@section('body-class', 'c2pa trust-dashboard')

@section('content')
@php
  $cc  = $trust['content_credentials'] ?? [];
  $ai  = $trust['ai_inference'] ?? [];
  $any = $trust['has_any_signal'] ?? false;

  $pubRecords = (int) ($cc['published_records'] ?? 0);
  $withCreds  = (int) ($cc['records_with_credentials'] ?? 0);
  $recsSigned = (int) ($cc['records_signed'] ?? 0);
  $mastTotal  = (int) ($cc['masters_total'] ?? 0);
  $mastSigned = (int) ($cc['masters_signed'] ?? 0);
  $mastUnsign = (int) ($cc['masters_unsigned'] ?? 0);
  $verified   = (int) ($cc['signed_verified'] ?? 0);
  $failed     = (int) ($cc['signed_failed'] ?? 0);
  $coverage   = (float) ($cc['coverage_pct'] ?? 0.0);
  $credsPct   = (float) ($cc['credentials_pct'] ?? 0.0);
  $verifPct   = (float) ($cc['verified_pct'] ?? 0.0);

  $aiRecords  = (int) ($ai['records_with_ai'] ?? 0);
  $aiTotal    = (int) ($ai['inferences_total'] ?? 0);
  $aiReviewed = (int) ($ai['reviewed'] ?? 0);
  $aiPending  = (int) ($ai['pending'] ?? 0);
  $aiCovPct   = (float) ($ai['ai_coverage_pct'] ?? 0.0);
  $aiRevPct   = (float) ($ai['reviewed_pct'] ?? 0.0);

  // Tiny inline helper for a labelled CSS bar segment width.
  $pct = function ($num, $den) {
      $den = (int) $den;
      if ($den <= 0) { return 0.0; }
      return round(min(100, max(0, $num / $den * 100)), 1);
  };
@endphp

{{-- Hero. --}}
<div class="card mb-4 border-2" style="border-color:var(--ahg-primary)">
  <div class="card-body p-4">
    <h1 class="mb-2"><i class="fas fa-shield-alt me-2"></i>{{ __('Trust at a glance') }}</h1>
    <p class="lead mb-2">
      {{ __('How much of what is published here can be independently verified.') }}
    </p>
    <p class="text-muted mb-0">
      {{ __('This is a live, collection-wide summary of our verifiable-authenticity signals: how many published records and digitised files carry content credentials, how many are cryptographically signed and still verify, and how much of our metadata involved AI - with a human kept accountable. The figures cover published records only.') }}
    </p>
  </div>
</div>

{{-- Standing honest caveat - shown on every state, never hidden. --}}
<div class="alert alert-secondary d-flex align-items-start mb-4" role="note">
  <i class="fas fa-info-circle me-2 mt-1"></i>
  <div>{{ __($trust['caveat'] ?? '') }}</div>
</div>

@unless($any)
  {{-- Honest empty state. --}}
  <div class="card mb-4">
    <div class="card-body text-center py-5">
      <div class="display-6 text-muted mb-3"><i class="fas fa-seedling"></i></div>
      <h2 class="h4 mb-2">{{ __('Authenticity signals are still being established') }}</h2>
      <p class="text-muted mb-0">
        {{ __('No content credentials or AI-inference records have been published yet. As digitised material is signed and recorded, this dashboard will fill in - showing how much of the collection can be verified.') }}
      </p>
      @if(($cc['reason'] ?? null) === 'not-installed')
        <p class="text-muted small mb-0 mt-2">{{ __('The content-credentials layer is not active on this installation yet.') }}</p>
      @endif
    </div>
  </div>
@else

  {{-- ============================ Content credentials ====================== --}}
  <h2 class="h4 mt-2 mb-3"><i class="fas fa-certificate me-2"></i>{{ __('Content credentials') }}</h2>

  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6" style="color:var(--ahg-primary)">{{ number_format($coverage, 1) }}%</div>
          <div class="text-muted small">{{ __('of master files signed') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6">{{ number_format($mastSigned) }}</div>
          <div class="text-muted small">{{ __('signed master files') }}</div>
          <div class="text-muted small">{{ __('of') }} {{ number_format($mastTotal) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6">{{ number_format($withCreds) }}</div>
          <div class="text-muted small">{{ __('records with content credentials') }}</div>
          <div class="text-muted small">{{ __('of') }} {{ number_format($pubRecords) }} {{ __('published') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6">{{ number_format($recsSigned) }}</div>
          <div class="text-muted small">{{ __('records cryptographically signed') }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Master-file signing bar: signed (verified) / signed (failed) / unsigned. --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between small text-muted mb-1">
        <span>{{ __('Master files') }}</span>
        <span>{{ number_format($mastTotal) }} {{ __('total') }}</span>
      </div>
      @php
        $wVer = $pct($verified, $mastTotal);
        $wFail = $pct($failed, $mastTotal);
        $wUns = max(0, round(100 - $wVer - $wFail, 1));
      @endphp
      <div class="progress" role="img" style="height:1.5rem"
           aria-label="{{ __('Master files by signing status') }}">
        <div class="progress-bar bg-success" style="width: {{ $wVer }}%" title="{{ __('Signed and verifiable') }}">
          @if($wVer >= 8){{ number_format($verified) }}@endif
        </div>
        @if($failed > 0)
          <div class="progress-bar bg-danger" style="width: {{ $wFail }}%" title="{{ __('Signed but failed verification') }}">
            @if($wFail >= 8){{ number_format($failed) }}@endif
          </div>
        @endif
        <div class="progress-bar bg-secondary" style="width: {{ $wUns }}%" title="{{ __('Not yet signed') }}">
          @if($wUns >= 8){{ number_format($mastUnsign) }}@endif
        </div>
      </div>
      <div class="d-flex flex-wrap gap-3 small mt-2">
        <span><span class="badge bg-success">&nbsp;</span> {{ __('Signed and verifiable') }}: {{ number_format($verified) }}</span>
        @if($failed > 0)
          <span><span class="badge bg-danger">&nbsp;</span> {{ __('Signed but failed verification') }}: {{ number_format($failed) }}</span>
        @endif
        <span><span class="badge bg-secondary">&nbsp;</span> {{ __('Not yet signed') }}: {{ number_format($mastUnsign) }}</span>
      </div>
      @if($failed > 0)
        <p class="text-danger small mb-0 mt-2">
          <i class="fas fa-exclamation-triangle me-1"></i>{{ __('Some signed files did not re-verify. Each affected record is flagged on its own authenticity report; this can mean a file was altered after signing.') }}
        </p>
      @endif
    </div>
  </div>

  {{-- Records-with-credentials bar. --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between small text-muted mb-1">
        <span>{{ __('Published records carrying content credentials') }}</span>
        <span>{{ number_format($credsPct, 1) }}%</span>
      </div>
      <div class="progress" role="img" style="height:1rem"
           aria-label="{{ __('Share of published records with content credentials') }}">
        <div class="progress-bar" style="width: {{ $pct($withCreds, $pubRecords) }}%; background-color:var(--ahg-primary)"></div>
      </div>
      <div class="small text-muted mt-1">
        {{ number_format($withCreds) }} {{ __('of') }} {{ number_format($pubRecords) }} {{ __('published records') }}
      </div>
    </div>
  </div>

  {{-- Signing identity / freshness. --}}
  @if(!empty($cc['last_signed_at']) || (int)($cc['issuers'] ?? 0) > 0 || (int)($cc['manifests_total'] ?? 0) > 0)
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-key me-2"></i>{{ __('Signing') }}</div>
      <div class="card-body">
        <dl class="row mb-0">
          @if((int)($cc['manifests_total'] ?? 0) > 0)
            <dt class="col-sm-4">{{ __('Signed content credentials issued') }}</dt>
            <dd class="col-sm-8">{{ number_format((int)$cc['manifests_total']) }}</dd>
          @endif
          @if((int)($cc['issuers'] ?? 0) > 0)
            <dt class="col-sm-4">{{ __('Distinct signing key(s)') }}</dt>
            <dd class="col-sm-8">{{ number_format((int)$cc['issuers']) }}</dd>
          @endif
          @if(!empty($cc['last_signed_at']))
            <dt class="col-sm-4">{{ __('Most recently signed') }}</dt>
            <dd class="col-sm-8">{{ $cc['last_signed_at'] }}</dd>
          @endif
        </dl>
        @unless($cc['can_sign'] ?? false)
          <p class="text-muted small mb-0 mt-2"><i class="fas fa-exclamation-triangle me-1"></i>{{ __('Cryptographic signing is unavailable on this host; provenance can be recorded but not sealed.') }}</p>
        @endunless
      </div>
    </div>
  @endif

  {{-- ============================ AI inference ============================ --}}
  @if(($ai['layer_installed'] ?? false) && $aiTotal > 0)
    <h2 class="h4 mt-4 mb-3"><i class="fas fa-robot me-2"></i>{{ __('AI in our metadata') }}</h2>

    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body">
            <div class="display-6" style="color:var(--ahg-primary)">{{ number_format($aiCovPct, 1) }}%</div>
            <div class="text-muted small">{{ __('of records involved AI') }}</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body">
            <div class="display-6">{{ number_format($aiRecords) }}</div>
            <div class="text-muted small">{{ __('records with a recorded AI step') }}</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body">
            <div class="display-6">{{ number_format($aiTotal) }}</div>
            <div class="text-muted small">{{ __('AI inferences on record') }}</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card h-100 text-center">
          <div class="card-body">
            <div class="display-6">{{ number_format($aiRevPct, 1) }}%</div>
            <div class="text-muted small">{{ __('reviewed by a person') }}</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Reviewed-vs-pending bar. --}}
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between small text-muted mb-1">
          <span>{{ __('Human accountability for AI steps') }}</span>
          <span>{{ number_format($aiTotal) }} {{ __('total') }}</span>
        </div>
        @php $wRev = $pct($aiReviewed, $aiTotal); $wPend = max(0, round(100 - $wRev, 1)); @endphp
        <div class="progress" role="img" style="height:1.5rem"
             aria-label="{{ __('AI inferences by review status') }}">
          <div class="progress-bar bg-success" style="width: {{ $wRev }}%" title="{{ __('Reviewed by a curator') }}">
            @if($wRev >= 8){{ number_format($aiReviewed) }}@endif
          </div>
          <div class="progress-bar bg-warning text-dark" style="width: {{ $wPend }}%" title="{{ __('AI-suggested, not yet reviewed') }}">
            @if($wPend >= 8){{ number_format($aiPending) }}@endif
          </div>
        </div>
        <div class="d-flex flex-wrap gap-3 small mt-2">
          <span><span class="badge bg-success">&nbsp;</span> {{ __('Reviewed by a curator') }}: {{ number_format($aiReviewed) }}</span>
          <span><span class="badge bg-warning text-dark">&nbsp;</span> {{ __('AI-suggested, not yet reviewed') }}: {{ number_format($aiPending) }}</span>
        </div>
        <p class="text-muted small mb-0 mt-2">
          {{ __('An AI step shown as "reviewed" means a person accepted, corrected, or rejected it - the original AI output is kept on record either way. A step "not yet reviewed" is a suggestion, never presented as verified.') }}
        </p>
      </div>
    </div>
  @endif

@endunless

{{-- Drill-down + verify entry points - reuse the existing pages. --}}
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-search me-2"></i>{{ __('Check a specific record') }}</div>
  <div class="card-body">
    <p class="mb-3">{{ __('Have a reference for one record? See its full authenticity report - what we can and cannot verify for it.') }}</p>
    <form method="get" class="row g-2 align-items-end"
          onsubmit="var v=this.elements['ref'].value.trim(); if(v===''){return false;} window.location.href='{{ url('/authenticity') }}/'+encodeURI(v).replace(/%2F/gi,'/'); return false;">
      <div class="col-12 col-md-8">
        <label for="trust-ref" class="form-label small text-muted mb-1">{{ __('Record permalink or reference') }}</label>
        <input type="text" id="trust-ref" name="ref" class="form-control"
               placeholder="{{ __('e.g. fonds/series/item or a record id') }}">
      </div>
      <div class="col-12 col-md-4">
        <button type="submit" class="btn btn-primary w-100">
          <i class="fas fa-shield-alt me-1"></i>{{ __('View authenticity report') }}
        </button>
      </div>
    </form>
    <p class="small mb-0 mt-3">
      <i class="fas fa-certificate me-1"></i><a href="{{ url('/verify') }}">{{ __('Learn how content credentials work, or verify a file directly.') }}</a>
    </p>
    @if(\Route::has('c2pa.explainer'))
      <p class="small mb-0 mt-1">
        <i class="fas fa-info-circle me-1"></i><a href="{{ route('c2pa.explainer') }}">{{ __('New to content credentials? Read what they are.') }}</a>
      </p>
    @endif
    <p class="small text-muted mb-0 mt-2">
      <i class="fas fa-code me-1"></i><a href="{{ url('/trust.json') }}">{{ __('Machine-readable summary (JSON)') }}</a>
    </p>
  </div>
</div>

<p class="text-muted small text-end mb-0">
  {{ __('Generated') }}: {{ $trust['generated_at'] ?? '' }}
</p>
@endsection
