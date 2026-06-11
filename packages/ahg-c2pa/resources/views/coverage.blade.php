{{--
  Heratio - C2PA "authenticity coverage" admin dashboard (deepens #1201 / #1209).

  Shows how much of the collection actually carries content credentials so an
  institution can see and close the gap: headline coverage %, the verified /
  invalid / unsigned split, and a per-holding-repository table. Admin-gated,
  read-only, jurisdiction-neutral. Bootstrap 5; matches the existing
  authenticity / provenance admin views. Every number is defensive - missing
  tables or zero objects render a clean "no data yet" state, never a 500.

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Authenticity Coverage'))
@section('body-class', 'admin c2pa coverage')

@section('content')
@php
  $r            = $report ?? [];
  $enabled      = $r['enabled'] ?? false;
  $reason       = $r['reason'] ?? null;
  $canSign      = $r['can_sign'] ?? false;

  $totalMasters = (int) ($r['total_masters'] ?? 0);
  $covered      = (int) ($r['covered_masters'] ?? 0);
  $uncovered    = (int) ($r['uncovered_masters'] ?? 0);
  $coveragePct  = (float) ($r['coverage_pct'] ?? 0.0);

  $signed       = (int) ($r['signed_records'] ?? 0);
  $unsigned     = (int) ($r['unsigned_records'] ?? 0);
  $verified     = (int) ($r['verified_records'] ?? 0);
  $invalid      = (int) ($r['invalid_records'] ?? 0);
  $verifiedPct  = (float) ($r['verified_pct'] ?? 0.0);

  $recordsTotal = (int) ($r['records_total'] ?? 0);
  $withCreds    = (int) ($r['records_with_credentials'] ?? 0);

  $breakdown    = $r['breakdown'] ?? [];
  $truncated    = $r['breakdown_truncated'] ?? false;
  $issuers      = $r['issuers'] ?? [];
  $lastSigned   = $r['last_signed_at'] ?? null;

  // Defensive clamp for progress-bar widths.
  $clamp = static fn ($v) => max(0.0, min(100.0, (float) $v));
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-shield-alt me-2"></i>{{ __('Authenticity Coverage') }}</h1>
  <div>
    <a href="{{ url('/verify') }}" class="btn btn-sm atom-btn-white" target="_blank" rel="noopener">
      <i class="fas fa-external-link-alt me-1"></i>{{ __('Public authenticity page') }}
    </a>
  </div>
</div>

<p class="text-muted">
  {{ __('How much of your digitised collection carries verifiable content credentials, and where the gaps are. Use this to plan which holdings still need signing.') }}
</p>

@if(!$canSign)
  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-1"></i>{{ $r['capability_summary'] ?? __('Signing is unavailable on this host.') }}
  </div>
@endif

@if($totalMasters <= 0)
  {{-- Clean "no data yet" state: no master digital objects to measure against. --}}
  <div class="card">
    <div class="card-body text-center text-muted py-5">
      <i class="fas fa-shield-alt fa-2x mb-3 d-block"></i>
      @if($reason === 'not-installed' || $reason === 'unavailable')
        <p class="mb-1">{{ __('The content-credentials layer is not active on this installation yet.') }}</p>
        <p class="mb-0 small">{{ __('Once it is enabled and you have digitised master files, this dashboard will show your coverage and the gaps to close.') }}</p>
      @else
        <p class="mb-1">{{ __('No master digital objects found to measure.') }}</p>
        <p class="mb-0 small">{{ __('As master files are digitised and signed, your authenticity coverage will appear here.') }}</p>
      @endif

      {{-- Even with no data, show the 0% headline so the page shape is stable. --}}
      <div class="row g-3 mt-3 justify-content-center">
        <div class="col-6 col-md-3">
          <div class="border rounded p-3">
            <div class="display-6 text-secondary">0%</div>
            <div class="small text-muted">{{ __('master files verifiable') }}</div>
            <div class="progress mt-2" style="height:.5rem" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
              <div class="progress-bar bg-secondary" style="width:0%"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@else
  {{-- HEADLINE: coverage % with progress bar. --}}
  <div class="card mb-4 border-2" style="border-color:var(--ahg-primary)">
    <div class="card-body p-4">
      <div class="row align-items-center g-3">
        <div class="col-md-3 text-center">
          <div class="display-3 fw-bold" style="color:var(--ahg-primary)">{{ number_format($coveragePct, 1) }}%</div>
          <div class="text-muted">{{ __('of master files verifiable') }}</div>
        </div>
        <div class="col-md-9">
          <div class="d-flex justify-content-between small text-muted mb-1">
            <span>{{ __('Signed master files') }}: <strong>{{ number_format($covered) }}</strong></span>
            <span>{{ __('Total master files') }}: <strong>{{ number_format($totalMasters) }}</strong></span>
          </div>
          <div class="progress mb-2" style="height:1.5rem" role="progressbar"
               aria-valuenow="{{ (int) round($clamp($coveragePct)) }}" aria-valuemin="0" aria-valuemax="100"
               aria-label="{{ __('Collection coverage') }}">
            <div class="progress-bar bg-success" style="width:{{ $clamp($coveragePct) }}%">
              @if($coveragePct >= 8){{ number_format($coveragePct, 1) }}%@endif
            </div>
          </div>
          <p class="small text-muted mb-0">
            <i class="fas fa-exclamation-circle me-1"></i>{{ number_format($uncovered) }}
            {{ __('master files are not yet covered by a signed content credential. These are your gap.') }}
          </p>
        </div>
      </div>
    </div>
  </div>

  {{-- Verified / invalid / unsigned counts. --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6 text-success">{{ number_format($verified) }}</div>
          <div class="small text-muted">{{ __('verified records') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6 {{ $invalid > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($invalid) }}</div>
          <div class="small text-muted">{{ __('invalid / tampered') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6 text-warning">{{ number_format($unsigned) }}</div>
          <div class="small text-muted">{{ __('unsigned records') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6">{{ number_format($withCreds) }}</div>
          <div class="small text-muted">{{ __('records with credentials') }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Signed-integrity bar: of the signed records, how many still verify. --}}
  @if($signed > 0)
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-fingerprint me-2"></i>{{ __('Integrity of signed records') }}</div>
      <div class="card-body">
        <div class="d-flex justify-content-between small text-muted mb-1">
          <span>{{ __('Verified') }}: <strong>{{ number_format($verified) }}</strong></span>
          <span>{{ __('of') }} {{ number_format($signed) }} {{ __('signed') }} ({{ number_format($verifiedPct, 1) }}%)</span>
        </div>
        <div class="progress" style="height:1rem" role="progressbar"
             aria-valuenow="{{ (int) round($clamp($verifiedPct)) }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ __('Signed-record integrity') }}">
          <div class="progress-bar bg-success" style="width:{{ $clamp($verifiedPct) }}%"></div>
          @if($invalid > 0)
            <div class="progress-bar bg-danger" style="width:{{ $clamp(100 - $verifiedPct) }}%"></div>
          @endif
        </div>
        @if($invalid > 0)
          <p class="small text-danger mb-0 mt-2">
            <i class="fas fa-exclamation-triangle me-1"></i>{{ number_format($invalid) }}
            {{ __('signed record(s) failed live verification and may have been tampered with. Review these first.') }}
          </p>
        @else
          <p class="small text-muted mb-0 mt-2">
            <i class="fas fa-check-circle me-1"></i>{{ __('All signed records currently verify.') }}
          </p>
        @endif
      </div>
    </div>
  @endif

  {{-- Per-holding-repository breakdown: where the gaps live. --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-warehouse me-2"></i>{{ __('Coverage by holding repository') }}</span>
      @if($truncated)
        <span class="badge bg-secondary">{{ __('Top') }} {{ count($breakdown) }}</span>
      @endif
    </div>
    @if(empty($breakdown))
      <div class="card-body text-center text-muted py-4">
        <i class="fas fa-warehouse fa-lg mb-2 d-block"></i>
        {{ __('No holdings to break down yet.') }}
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>{{ __('Repository') }}</th>
              <th class="text-end">{{ __('Master files') }}</th>
              <th class="text-end">{{ __('Signed') }}</th>
              <th class="text-end">{{ __('Gap') }}</th>
              <th style="min-width:12rem">{{ __('Coverage') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($breakdown as $row)
              @php
                $rMasters = (int) ($row['masters'] ?? 0);
                $rCovered = (int) ($row['covered'] ?? 0);
                $rGap     = max(0, $rMasters - $rCovered);
                $rPct     = (float) ($row['coverage_pct'] ?? 0.0);
                $barClass = $rPct >= 99.95 ? 'bg-success' : ($rPct > 0 ? 'bg-info' : 'bg-secondary');
              @endphp
              <tr>
                <td>{{ $row['label'] ?? __('Unknown') }}</td>
                <td class="text-end">{{ number_format($rMasters) }}</td>
                <td class="text-end">{{ number_format($rCovered) }}</td>
                <td class="text-end {{ $rGap > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($rGap) }}</td>
                <td>
                  <div class="d-flex align-items-center">
                    <div class="progress flex-grow-1 me-2" style="height:.75rem" role="progressbar"
                         aria-valuenow="{{ (int) round($clamp($rPct)) }}" aria-valuemin="0" aria-valuemax="100">
                      <div class="progress-bar {{ $barClass }}" style="width:{{ $clamp($rPct) }}%"></div>
                    </div>
                    <span class="small text-muted" style="width:3.5rem">{{ number_format($rPct, 1) }}%</span>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
@endif

{{-- Signing identity + freshness (mirrors the public page). --}}
@if(!empty($issuers) || !empty($lastSigned))
  <div class="card mb-4">
    <div class="card-header"><i class="fas fa-key me-2"></i>{{ __('Signing identity') }}</div>
    <div class="card-body">
      <dl class="row mb-0">
        @if(!empty($lastSigned))
          <dt class="col-sm-3">{{ __('Most recently signed') }}</dt>
          <dd class="col-sm-9">{{ $lastSigned }}</dd>
        @endif
        @if(!empty($issuers))
          <dt class="col-sm-3">{{ __('Issuing key(s)') }}</dt>
          <dd class="col-sm-9">
            @foreach($issuers as $issuer)
              <span class="badge bg-light text-dark border me-1 mb-1">
                <i class="fas fa-fingerprint me-1"></i><code>{{ $issuer['kid'] ?? '' }}</code>
                <span class="text-muted">&times;{{ number_format((int) ($issuer['count'] ?? 0)) }}</span>
              </span>
            @endforeach
          </dd>
        @endif
      </dl>
    </div>
  </div>
@endif
@endsection
