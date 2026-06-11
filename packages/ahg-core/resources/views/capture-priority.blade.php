{{--
  heratio#1205 - Capture / at-risk register (first slice of the endangered-heritage
  capture network). Which records to capture next, and why. Jurisdiction-neutral.
  Read-only prioritisation aid built from catalogue signals.
--}}
@extends('theme::layouts.1col')
@section('title', __('Capture priority register'))

@section('content')
@php
  $report = $report ?? [];
  $rows = $report['rows'] ?? [];
  $summary = $report['summary'] ?? ['total' => 0, 'scored' => 0, 'no_master' => 0, 'poor_condition' => 0, 'endangered' => 0];
  $reasonCounts = $report['reason_counts'] ?? [];
  $weights = $report['weights'] ?? [];
  $notes = $report['notes'] ?? ['condition_reports' => false, 'museum_metadata' => false];
  $maxScore = 0;
  foreach ($weights as $w) { $maxScore += (int) $w; }
@endphp

<div class="container-fluid py-3">

  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
    <h1 class="h4 mb-0"><i class="fas fa-triangle-exclamation me-2 text-danger"></i>{{ __('Capture priority register') }}</h1>
    <span class="text-muted small">{{ __('Which records to capture next, and why') }}</span>
  </div>
  <p class="text-muted small mb-3" style="max-width:820px">
    {{ __('This register surfaces the records most in need of digitisation or most at risk of loss. It is the detection-and-triage step of a capture network: catalogue signals (no preservation copy yet, recorded condition, fragility/decay notes) are combined into a transparent priority score so limited capture effort goes where loss is most likely first.') }}
  </p>

  @if(!empty($report['error']))
    <div class="alert alert-warning"><i class="fas fa-circle-exclamation me-1"></i>{{ __('The register could not be built from the catalogue right now. Please try again later.') }}</div>
  @endif

  {{-- Summary cards --}}
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body py-2">
          <div class="text-muted small text-uppercase">{{ __('At risk (ranked)') }}</div>
          <div class="h3 mb-0">{{ number_format($summary['scored']) }}</div>
          <div class="text-muted small">{{ __('of') }} {{ number_format($summary['total']) }} {{ __('records scanned') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body py-2">
          <div class="text-muted small text-uppercase">{{ __('No master surrogate') }}</div>
          <div class="h3 mb-0 text-danger">{{ number_format($summary['no_master']) }}</div>
          <div class="text-muted small">{{ __('not yet captured / backed up') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body py-2">
          <div class="text-muted small text-uppercase">{{ __('Poor condition') }}</div>
          <div class="h3 mb-0 text-warning">{{ number_format($summary['poor_condition']) }}</div>
          <div class="text-muted small">{{ __('assessed poor / unstable') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body py-2">
          <div class="text-muted small text-uppercase">{{ __('Fragility / decay flags') }}</div>
          <div class="h3 mb-0 text-warning">{{ number_format($summary['endangered']) }}</div>
          <div class="text-muted small">{{ __('flagged in the catalogue') }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Counts by reason + active weights --}}
  <div class="row g-3 mb-3">
    @if(!empty($reasonCounts))
    <div class="col-md-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent py-2 small fw-semibold">{{ __('Records by top reason') }}</div>
        <ul class="list-group list-group-flush small">
          @foreach($reasonCounts as $reason => $count)
            <li class="list-group-item d-flex justify-content-between align-items-center py-1">
              <span>{{ $reason }}</span>
              <span class="badge bg-secondary rounded-pill">{{ number_format($count) }}</span>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
    @endif
    <div class="col-md-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent py-2 small fw-semibold">{{ __('Scoring signals (weights)') }}</div>
        <ul class="list-group list-group-flush small">
          @foreach($weights as $key => $w)
            <li class="list-group-item d-flex justify-content-between align-items-center py-1">
              <span class="text-capitalize">{{ str_replace('_', ' ', $key) }}</span>
              <span class="badge bg-light text-dark border">+{{ $w }}</span>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>

  {{-- Ranked table --}}
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex flex-wrap align-items-center gap-2 py-2">
      <span class="fw-semibold">{{ __('Capture queue') }}</span>
      <span class="text-muted small">{{ __('highest priority first') }}</span>
      <form method="get" class="ms-auto d-flex align-items-center gap-1">
        <label class="text-muted small mb-0" for="cpLimit">{{ __('Show') }}</label>
        <select name="limit" id="cpLimit" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
          @foreach([50, 100, 250, 500, 0] as $opt)
            <option value="{{ $opt }}" @selected((int)($limit ?? 100) === $opt)>{{ $opt === 0 ? __('All') : $opt }}</option>
          @endforeach
        </select>
      </form>
    </div>

    @if(empty($rows))
      <div class="card-body text-muted">
        <i class="fas fa-circle-check me-1 text-success"></i>{{ __('No at-risk records detected from the current catalogue signals. As records are catalogued and condition is assessed, anything needing capture will appear here.') }}
      </div>
    @else
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:3rem" class="text-end">#</th>
            <th style="width:9rem">{{ __('Priority') }}</th>
            <th>{{ __('Record') }}</th>
            <th>{{ __('Why it is prioritised') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $i => $r)
            @php
              $pct = $maxScore > 0 ? min(100, round($r['score'] / $maxScore * 100)) : 0;
              $bar = $pct >= 66 ? 'bg-danger' : ($pct >= 33 ? 'bg-warning' : 'bg-secondary');
            @endphp
            <tr>
              <td class="text-end text-muted">{{ $i + 1 }}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge {{ $bar }}">{{ $r['score'] }}</span>
                  <div class="progress flex-grow-1" style="height:6px;min-width:48px" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar {{ $bar }}" style="width: {{ $pct }}%"></div>
                  </div>
                </div>
              </td>
              <td>
                @if(!empty($r['slug']))
                  <a href="{{ url('/'.$r['slug']) }}" target="_blank" rel="noopener">{{ $r['title'] }}</a>
                @else
                  {{ $r['title'] }}
                @endif
                <span class="text-muted small d-block">#{{ $r['id'] }}</span>
              </td>
              <td>
                @foreach($r['reasons'] as $reason)
                  <span class="badge bg-light text-dark border me-1 mb-1 fw-normal">{{ $reason }}</span>
                @endforeach
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  {{-- Honest note --}}
  <p class="text-muted small mt-3 mb-0" style="max-width:820px">
    <i class="fas fa-circle-info me-1"></i>{{ __('This is a prioritisation aid, not a verdict. The score is a transparent weighted sum of catalogue signals - it reflects only what has been recorded, so a record with sparse cataloguing may be under-ranked, and local knowledge always takes precedence. Use it to triage capture effort, then confirm against the physical material.') }}
    @if(!$notes['condition_reports'] || !$notes['museum_metadata'])
      <br><span class="text-muted">{{ __('Some condition sources are not available on this install, so condition-based signals may be incomplete.') }}</span>
    @endif
  </p>

</div>
@endsection
