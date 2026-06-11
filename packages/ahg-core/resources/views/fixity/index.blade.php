{{--
  Fixity / integrity report (admin). Read-only coverage + recent-check view built
  from AhgCore\Services\FixityService. Shows how many digital objects carry a
  verifiable checksum baseline, how many have never been verified, the result
  roll-up of the most recent verification, a "last sweep" summary, and the most
  recent individual checks. Jurisdiction-neutral: framed against the generic
  "Integrity" functional area of the NDSA Levels of Digital Preservation.

  Big numbers + CSS progress bars, no charting library. Empty-state safe - a
  fresh install with no digital objects renders a calm "nothing to verify yet"
  card, never a 500.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Fixity / integrity'))

@section('content')
@php
  $coverage = $coverage ?? [];
  $total           = (int) ($coverage['total'] ?? 0);
  $withBaseline    = (int) ($coverage['with_baseline'] ?? 0);
  $withoutBaseline = (int) ($coverage['without_baseline'] ?? 0);
  $neverVerified   = (int) ($coverage['never_verified'] ?? 0);
  $algorithms      = $coverage['algorithms'] ?? [];
  $lastSweep       = $coverage['last_sweep'] ?? null;
  $recent          = $coverage['recent'] ?? [];
  $results         = $coverage['results'] ?? [];
  $generatedAt     = $coverage['generated_at'] ?? null;
  $hasError        = ! empty($coverage['error']);

  $coveragePct = $total > 0 ? ($withBaseline / $total) * 100 : 0;
  $verifiedCount = max(0, $withBaseline - $neverVerified);
  $verifiedPct = $withBaseline > 0 ? ($verifiedCount / $withBaseline) * 100 : 0;

  // Map a result key to a Bootstrap colour band + icon.
  $resultMeta = function (string $r): array {
      return match ($r) {
          'match'            => ['badge' => 'bg-success',  'icon' => 'fa-circle-check',        'label' => __('Match')],
          'mismatch'         => ['badge' => 'bg-danger',   'icon' => 'fa-triangle-exclamation','label' => __('Mismatch')],
          'missing_file'     => ['badge' => 'bg-warning text-dark', 'icon' => 'fa-file-circle-xmark', 'label' => __('Missing file')],
          'no_baseline'      => ['badge' => 'bg-secondary','icon' => 'fa-circle-question',     'label' => __('No baseline')],
          'skipped_oversize' => ['badge' => 'bg-info text-dark', 'icon' => 'fa-forward',       'label' => __('Skipped (oversize)')],
          default            => ['badge' => 'bg-secondary','icon' => 'fa-circle-exclamation',  'label' => __('Error')],
      };
  };
@endphp

<div class="container-fluid py-3">

  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
    <h1 class="h4 mb-0"><i class="fas fa-fingerprint me-2 text-primary"></i>{{ __('Fixity / integrity') }}</h1>
    <span class="text-muted small">{{ __('Checksum coverage and verification results for digital objects') }}</span>
    <span class="ms-auto"></span>
    @if(Route::has('preservation-maturity.index'))
      <a href="{{ route('preservation-maturity.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-shield-halved me-1"></i>{{ __('Preservation maturity') }}
      </a>
    @endif
  </div>
  <p class="text-muted small mb-3" style="max-width:880px">
    {{ __('Fixity is the assurance that a stored file has not changed since it was ingested. This read-only report shows how much of the collection carries a verifiable checksum baseline and what the most recent verification found. To verify a bounded batch, run the scheduled sweep or "php artisan ahg:fixity-sweep". It is the actionable Integrity area of the NDSA Levels of Digital Preservation, and it never changes a record.') }}
  </p>

  @if($hasError)
    <div class="alert alert-warning"><i class="fas fa-circle-exclamation me-1"></i>{{ __('The fixity report could not be fully built right now. Please try again later.') }}</div>
  @endif

  @if($total === 0)
    {{-- Clean "nothing to verify" state - never a 500. --}}
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-5">
        <div class="display-6 text-muted mb-2">{{ __('Nothing to verify yet') }}</div>
        <h2 class="h5">{{ __('No local digital objects to check') }}</h2>
        <p class="text-muted mb-0" style="max-width:600px;margin:0 auto">
          {{ __('Once digital objects are ingested with stored checksums, this report will show fixity coverage and the results of each verification sweep.') }}
        </p>
      </div>
    </div>
  @else

    {{-- Coverage headline numbers --}}
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small text-uppercase mb-1">{{ __('Digital objects') }}</div>
            <div class="display-6 mb-0">{{ number_format($total) }}</div>
            <div class="text-muted small">{{ __('local files in scope') }}</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small text-uppercase mb-1">{{ __('With checksum baseline') }}</div>
            <div class="display-6 mb-0 text-success">{{ number_format($withBaseline) }}</div>
            <div class="progress mt-2" style="height:8px" role="progressbar"
                 aria-valuenow="{{ (int) $coveragePct }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="{{ __('Checksum coverage') }}">
              <div class="progress-bar bg-success" style="width: {{ $coveragePct }}%"></div>
            </div>
            <div class="text-muted small mt-1">{{ number_format($coveragePct, 1) }}% {{ __('of objects') }}</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small text-uppercase mb-1">{{ __('Without baseline') }}</div>
            <div class="display-6 mb-0 {{ $withoutBaseline > 0 ? 'text-warning' : 'text-muted' }}">{{ number_format($withoutBaseline) }}</div>
            <div class="text-muted small">{{ __('cannot be verified until a checksum is recorded') }}</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small text-uppercase mb-1">{{ __('Never verified') }}</div>
            <div class="display-6 mb-0 {{ $neverVerified > 0 ? 'text-warning' : 'text-success' }}">{{ number_format($neverVerified) }}</div>
            <div class="progress mt-2" style="height:8px" role="progressbar"
                 aria-valuenow="{{ (int) $verifiedPct }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="{{ __('Verified share') }}">
              <div class="progress-bar bg-info" style="width: {{ $verifiedPct }}%"></div>
            </div>
            <div class="text-muted small mt-1">{{ number_format($verifiedPct, 1) }}% {{ __('of baselined objects verified') }}</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Last sweep + result roll-up --}}
    <div class="row g-3 mb-3">
      <div class="col-12 col-lg-6">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small text-uppercase mb-2"><i class="fas fa-rotate me-1"></i>{{ __('Last sweep') }}</div>
            @if($lastSweep)
              <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
                <span class="fw-semibold">{{ number_format($lastSweep['total'] ?? 0) }}</span>
                <span class="text-muted small">{{ __('checks at') }} {{ $lastSweep['checked_at'] ?? '' }}</span>
              </div>
              <div class="d-flex flex-wrap gap-2">
                @foreach(($lastSweep['counts'] ?? []) as $rk => $rc)
                  @php $m = $resultMeta((string) $rk); @endphp
                  <span class="badge {{ $m['badge'] }}"><i class="fas {{ $m['icon'] }} me-1"></i>{{ $m['label'] }}: {{ number_format($rc) }}</span>
                @endforeach
              </div>
            @else
              <p class="text-muted small mb-0">{{ __('No sweep has run yet. Run "php artisan ahg:fixity-sweep" or wait for the daily schedule.') }}</p>
            @endif
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small text-uppercase mb-2"><i class="fas fa-list-check me-1"></i>{{ __('Latest result per object') }}</div>
            @if(! empty($results))
              @php $resTotal = array_sum($results); @endphp
              @foreach($results as $rk => $rc)
                @php
                  $m = $resultMeta((string) $rk);
                  $w = $resTotal > 0 ? ($rc / $resTotal) * 100 : 0;
                @endphp
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="badge {{ $m['badge'] }}" style="min-width:8.5rem"><i class="fas {{ $m['icon'] }} me-1"></i>{{ $m['label'] }}</span>
                  <div class="progress flex-grow-1" style="height:10px">
                    <div class="progress-bar {{ $m['badge'] }}" style="width: {{ $w }}%"></div>
                  </div>
                  <span class="small text-muted" style="min-width:3rem; text-align:right">{{ number_format($rc) }}</span>
                </div>
              @endforeach
            @else
              <p class="text-muted small mb-0">{{ __('No verification results recorded yet.') }}</p>
            @endif

            @if(! empty($algorithms))
              <div class="mt-3">
                <div class="text-uppercase text-muted small fw-semibold mb-1">{{ __('Baseline algorithms') }}</div>
                @foreach($algorithms as $algo => $n)
                  <span class="badge bg-light text-dark border me-1">{{ $algo }}: {{ number_format($n) }}</span>
                @endforeach
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- Recent individual checks --}}
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="text-muted small text-uppercase mb-2"><i class="fas fa-clock-rotate-left me-1"></i>{{ __('Recent checks') }}</div>
        @if(! empty($recent))
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>{{ __('Result') }}</th>
                  <th>{{ __('Digital object') }}</th>
                  <th>{{ __('Algorithm') }}</th>
                  <th>{{ __('Detail') }}</th>
                  <th class="text-nowrap">{{ __('Checked at') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($recent as $row)
                  @php $m = $resultMeta((string) ($row['result'] ?? 'error')); @endphp
                  <tr>
                    <td><span class="badge {{ $m['badge'] }}"><i class="fas {{ $m['icon'] }} me-1"></i>{{ $m['label'] }}</span></td>
                    <td class="text-muted">#{{ (int) ($row['digital_object_id'] ?? 0) }}</td>
                    <td class="small text-muted">{{ $row['expected_algo'] ?? '' }}</td>
                    <td class="small text-muted">{{ \Illuminate\Support\Str::limit((string) ($row['detail'] ?? ''), 90) }}</td>
                    <td class="small text-nowrap text-muted">{{ $row['checked_at'] ?? '' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <p class="text-muted small mb-0">{{ __('No checks recorded yet. The first sweep will populate this list.') }}</p>
        @endif
      </div>
    </div>

    @if($generatedAt)
      <p class="text-muted small mb-0 mt-3">{{ __('Generated') }}: {{ $generatedAt }}</p>
    @endif
  @endif

</div>
@endsection
