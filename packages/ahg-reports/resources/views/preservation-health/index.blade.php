{{--
  Preservation Health report - a read-only, operator-facing view of the
  operational state of the digital collection's integrity. It surfaces what needs
  attention: fixity passes versus failures and objects never checked; objects
  flagged with a missing file; format-identification (PUID) coverage; and virus-
  scan posture. It closes with a short list of the most recent preservation
  failures and warnings. Each metric is a count, a share, and a CSS bar (no
  charting library); the recent list is a small table.

  Honesty: this surfaces what needs attention. It reads the canonical
  preservation stores owned by the ahg-preservation package and changes nothing -
  no writes, no ALTER. International, jurisdiction-neutral copy. Never 500s;
  empty-state safe (no preservation activity yet shows a calm note).

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Preservation health report')
@section('body-class', 'admin reports')

@php
  // Colour the fixity-pass gauge by band. Pure presentation, no consequence.
  $fixPct = $fixity_pass_pct ?? 0.0;
  if ($fixPct >= 95) {
      $fixClass = 'text-success';
      $fixBar   = 'bg-success';
  } elseif ($fixPct >= 80) {
      $fixClass = 'text-warning';
      $fixBar   = 'bg-warning';
  } else {
      $fixClass = 'text-danger';
      $fixBar   = 'bg-danger';
  }

  $needsAttention = ($fixity_fail ?? 0) + ($missing_files ?? 0) + ($virus_flagged ?? 0);
@endphp

@section('sidebar')
<section class="card mb-3">
  <div class="card-body">
    @if(Route::has('reports.dashboard'))
    <a href="{{ route('reports.dashboard') }}" class="btn btn-outline-secondary btn-sm w-100">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Reports') }}
    </a>
    @endif
    @if(Route::has('fixity.index'))
    <a href="{{ route('fixity.index') }}" class="btn btn-outline-secondary btn-sm w-100 mt-2">
      <i class="bi bi-shield-check me-1"></i>{{ __('Fixity dashboard') }}
    </a>
    @endif
    @if(Route::has('preservation-maturity.index'))
    <a href="{{ route('preservation-maturity.index') }}" class="btn btn-outline-secondary btn-sm w-100 mt-2">
      <i class="bi bi-graph-up me-1"></i>{{ __('Preservation maturity') }}
    </a>
    @endif
    @if(Route::has('preservation.formats'))
    <a href="{{ route('preservation.formats') }}" class="btn btn-outline-secondary btn-sm w-100 mt-2">
      <i class="bi bi-filetype-raw me-1"></i>{{ __('Format identification log') }}
    </a>
    @endif
    @if(Route::has('preservation.virus-scan'))
    <a href="{{ route('preservation.virus-scan') }}" class="btn btn-outline-secondary btn-sm w-100 mt-2">
      <i class="bi bi-bug me-1"></i>{{ __('Virus-scan log') }}
    </a>
    @endif
    @if(Route::has('trust.console'))
    <a href="{{ route('trust.console') }}" class="btn btn-outline-secondary btn-sm w-100 mt-2">
      <i class="bi bi-patch-check me-1"></i>{{ __('Trust and Transparency Console') }}
    </a>
    @endif
  </div>
</section>
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">{{ __('About this report') }}</h6></div>
  <div class="card-body small text-muted">
    {{ __('A read-only operator view of the preservation state of the digital collection: which files passed or failed their integrity check, which have never been checked, which are flagged as missing, which carry no format identification, and which were flagged by a virus scan. It surfaces what needs attention; it changes nothing.') }}
  </div>
</section>
@if($available && ($total_objects ?? 0) > 0)
<section class="card mb-3">
  <div class="card-body small">
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Digital objects') }}</span>
      <span class="fw-bold">{{ number_format($total_objects) }}</span>
    </div>
    @if($fixity_available)
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Fixity pass rate') }}</span>
      <span class="fw-bold {{ $fixClass }}">{{ number_format($fixity_pass_pct, 1) }}%</span>
    </div>
    @endif
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Needs attention') }}</span>
      <span class="fw-bold {{ $needsAttention > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($needsAttention) }}</span>
    </div>
  </div>
</section>
@endif
@endsection

@section('title-block')
<h1>{{ __('Preservation health report') }}</h1>
<p class="text-muted mb-0">{{ __('The operational state of the digital collection\'s integrity') }}</p>
@endsection

@section('content')

@if(! $available)
  {{-- Fresh install: the digital_object table itself is not present. --}}
  <div class="card">
    <div class="card-body text-center py-5">
      <div class="display-6 text-muted mb-2"><i class="bi bi-hdd-stack"></i></div>
      <h2 class="h5">{{ __('No digital collection yet') }}</h2>
      <p class="text-muted mb-0">
        {{ __('There are no digital objects in the system yet, so there is nothing to preserve or check. As digital objects are added and preservation actions run, this report will show their integrity state.') }}
      </p>
    </div>
  </div>
@elseif(! $has_activity)
  {{-- Digital objects exist but no preservation activity has run yet. --}}
  <div class="card">
    <div class="card-body text-center py-5">
      <div class="display-6 text-muted mb-2"><i class="bi bi-shield"></i></div>
      <h2 class="h5">{{ __('No preservation data yet') }}</h2>
      <p class="text-muted mb-2">
        {{ __('There are :n digital objects, but no fixity checks, format identifications or virus scans have been recorded against them yet. Once preservation actions run, this report will show what passed, what failed, and what still needs attention.', ['n' => number_format($total_objects)]) }}
      </p>
      @if(Route::has('fixity.index'))
      <a href="{{ route('fixity.index') }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-shield-check me-1"></i>{{ __('Open the fixity dashboard') }}
      </a>
      @endif
    </div>
  </div>
@else

{{-- Headline strip: fixity pass rate + what needs attention --}}
<div class="card mb-4">
  <div class="card-body">
    <div class="row align-items-center g-4">
      <div class="col-md-3 text-center border-end">
        <div class="display-5 fw-bold">{{ number_format($total_objects) }}</div>
        <div class="text-uppercase small text-muted">{{ __('digital objects') }}</div>
      </div>
      <div class="col-md-3 text-center border-end">
        <div class="display-5 fw-bold {{ $needsAttention > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($needsAttention) }}</div>
        <div class="text-uppercase small text-muted">{{ __('need attention') }}</div>
      </div>
      <div class="col-md-6">
        @if($fixity_available)
        <div class="d-flex justify-content-between mb-1">
          <span class="fw-semibold"><i class="bi bi-shield-check me-1"></i>{{ __('Fixity pass rate (of checked objects)') }}</span>
          <span class="fw-bold {{ $fixClass }}">{{ number_format($fixity_pass_pct, 1) }}%</span>
        </div>
        <div class="progress" style="height: 1.25rem;" role="progressbar"
             aria-valuenow="{{ (int) round($fixity_pass_pct) }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ __('Fixity pass rate') }}">
          <div class="progress-bar {{ $fixBar }}" style="width: {{ max(0, min(100, $fixity_pass_pct)) }}%;">
            {{ number_format($fixity_pass_pct, 1) }}%
          </div>
        </div>
        <p class="text-muted small mb-0 mt-2">
          <strong>{{ number_format($fixity_pass) }}</strong> {{ __('passed') }},
          <strong>{{ number_format($fixity_fail) }}</strong> {{ __('failed') }},
          <strong>{{ number_format($fixity_unchecked) }}</strong> {{ __('never checked, of') }}
          <strong>{{ number_format($total_objects) }}</strong> {{ __('digital objects.') }}
        </p>
        @else
        <p class="text-muted small mb-0">
          <i class="bi bi-info-circle me-1"></i>{{ __('No fixity results store is present, so an integrity pass rate cannot be shown.') }}
        </p>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  {{-- Integrity (fixity) --}}
  @if($fixity_available)
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0"><i class="bi bi-shield-check me-1"></i>{{ __('Integrity (fixity checks)') }}</h3>
        @if(Route::has('fixity.index'))
        <a href="{{ route('fixity.index') }}" class="small">{{ __('Open dashboard') }}</a>
        @endif
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">{{ __('The latest fixity result for each object - whether its stored checksum still matches the file - plus objects that have never been checked. Shares are of all digital objects.') }}</p>
        @foreach($fixity_rows as $row)
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-1">
            <span class="fw-semibold"><i class="bi bi-{{ $row['icon'] }} me-1 text-{{ $row['tone'] }}"></i>{{ $row['label'] }}</span>
            <span class="small text-muted">{{ number_format($row['count']) }} ({{ number_format($row['pct'], 1) }}%)</span>
          </div>
          <div class="progress" style="height: 0.75rem;" role="progressbar"
               aria-valuenow="{{ (int) round($row['pct']) }}" aria-valuemin="0" aria-valuemax="100"
               aria-label="{{ $row['label'] }}">
            <div class="progress-bar bg-{{ $row['tone'] }}" style="width: {{ max(0, min(100, $row['pct'])) }}%;"></div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
  @endif

  {{-- Format identification --}}
  @if($format_available)
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0"><i class="bi bi-filetype-raw me-1"></i>{{ __('Format identification') }}</h3>
        @if(Route::has('preservation.formats'))
        <a href="{{ route('preservation.formats') }}" class="small">{{ __('Open log') }}</a>
        @endif
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">{{ __('Coverage of file-format identification: how many digital objects carry a recorded format (a PUID or a format name) versus how many have none yet. Identified formats let preservation planning spot obsolescence risk.') }}</p>
        @foreach($format_rows as $row)
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-1">
            <span class="fw-semibold"><i class="bi bi-{{ $row['icon'] }} me-1 text-{{ $row['tone'] }}"></i>{{ $row['label'] }}</span>
            <span class="small text-muted">{{ number_format($row['count']) }} ({{ number_format($row['pct'], 1) }}%)</span>
          </div>
          <div class="progress" style="height: 0.75rem;" role="progressbar"
               aria-valuenow="{{ (int) round($row['pct']) }}" aria-valuemin="0" aria-valuemax="100"
               aria-label="{{ $row['label'] }}">
            <div class="progress-bar bg-{{ $row['tone'] }}" style="width: {{ max(0, min(100, $row['pct'])) }}%;"></div>
          </div>
        </div>
        @endforeach
        <p class="text-muted small mb-0">
          <strong>{{ number_format($format_pct, 1) }}%</strong> {{ __('of digital objects have an identified format.') }}
        </p>
      </div>
    </div>
  </div>
  @endif

  {{-- Missing files --}}
  @if($events_available)
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3 class="h6 mb-0"><i class="bi bi-exclamation-octagon me-1"></i>{{ __('Missing files') }}</h3></div>
      <div class="card-body">
        <p class="text-muted small mb-3">{{ __('Objects whose file could not be found when last looked for - recorded as a "file missing" preservation event. These need attention: the stored file may have been moved, deleted, or never copied across.') }}</p>
        <div class="d-flex align-items-center mb-3">
          <div class="display-6 fw-bold {{ $missing_files > 0 ? 'text-danger' : 'text-success' }} me-3">{{ number_format($missing_files) }}</div>
          <div class="small text-muted">
            {{ __('object(s) flagged with a missing file') }}
            <div>{{ number_format($missing_pct, 1) }}% {{ __('of all digital objects') }}</div>
          </div>
        </div>
        <div class="progress mb-3" style="height: 0.75rem;" role="progressbar"
             aria-valuenow="{{ (int) round($missing_pct) }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ __('Share of objects with a missing file') }}">
          <div class="progress-bar bg-danger" style="width: {{ max(0, min(100, $missing_pct)) }}%;"></div>
        </div>
        @if(! empty($missing_rows))
        <div class="table-responsive">
          <table class="table table-sm small mb-0">
            <thead><tr>
              <th>{{ __('Digital object') }}</th>
              <th>{{ __('When') }}</th>
              <th>{{ __('Detail') }}</th>
            </tr></thead>
            <tbody>
              @foreach($missing_rows as $r)
              <tr>
                <td class="text-nowrap">#{{ $r['digital_object_id'] }}</td>
                <td class="text-nowrap text-muted">{{ $r['when'] ?: '-' }}</td>
                <td class="text-muted">{{ \Illuminate\Support\Str::limit($r['detail'], 80) ?: '-' }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <p class="text-success small mb-0"><i class="bi bi-check-circle me-1"></i>{{ __('No missing files recorded. Every checked file was found in place.') }}</p>
        @endif
      </div>
    </div>
  </div>
  @endif

  {{-- Virus scan --}}
  @if($virus_available)
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0"><i class="bi bi-bug me-1"></i>{{ __('Virus scan') }}</h3>
        @if(Route::has('preservation.virus-scan'))
        <a href="{{ route('preservation.virus-scan') }}" class="small">{{ __('Open log') }}</a>
        @endif
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">{{ __('The latest virus-scan result per object: clean versus flagged. "Flagged" includes any object where a threat was named or where the most recent scan could not confirm the file is clean (so it warrants a re-scan).') }}</p>
        @foreach($virus_rows as $row)
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-1">
            <span class="fw-semibold"><i class="bi bi-{{ $row['icon'] }} me-1 text-{{ $row['tone'] }}"></i>{{ $row['label'] }}</span>
            <span class="small text-muted">{{ number_format($row['count']) }} ({{ number_format($row['pct'], 1) }}%)</span>
          </div>
          <div class="progress" style="height: 0.75rem;" role="progressbar"
               aria-valuenow="{{ (int) round($row['pct']) }}" aria-valuemin="0" aria-valuemax="100"
               aria-label="{{ $row['label'] }}">
            <div class="progress-bar bg-{{ $row['tone'] }}" style="width: {{ max(0, min(100, $row['pct'])) }}%;"></div>
          </div>
        </div>
        @endforeach
        <p class="text-muted small mb-0">
          {{ number_format($virus_scanned) }} {{ __('object(s) scanned in total.') }}
        </p>
      </div>
    </div>
  </div>
  @endif
</div>

{{-- Recent preservation failures and warnings --}}
<div class="card mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="h6 mb-0"><i class="bi bi-list-check me-1"></i>{{ __('Recent preservation failures and warnings') }}</h3>
    @if(Route::has('preservation.events'))
    <a href="{{ route('preservation.events') }}" class="small">{{ __('Open event log') }}</a>
    @endif
  </div>
  <div class="card-body">
    <p class="text-muted small mb-3">{{ __('The most recent preservation events whose outcome was a failure or a warning, newest first. Each is something an operator may want to follow up on the affected digital object.') }}</p>
    @if(! empty($recent))
    <div class="table-responsive">
      <table class="table table-sm small mb-0 align-middle">
        <thead><tr>
          <th>{{ __('Outcome') }}</th>
          <th>{{ __('Type') }}</th>
          <th>{{ __('When') }}</th>
          <th>{{ __('Digital object') }}</th>
          <th>{{ __('Detail') }}</th>
        </tr></thead>
        <tbody>
          @foreach($recent as $r)
          <tr>
            <td class="text-nowrap">
              @if($r['outcome'] === 'failure')
                <span class="badge bg-danger">{{ __('Failure') }}</span>
              @else
                <span class="badge bg-warning text-dark">{{ __('Warning') }}</span>
              @endif
            </td>
            <td class="text-nowrap">{{ $r['type'] }}</td>
            <td class="text-nowrap text-muted">{{ $r['when'] ?: '-' }}</td>
            <td class="text-nowrap">{{ $r['digital_object_id'] > 0 ? '#' . $r['digital_object_id'] : '-' }}</td>
            <td class="text-muted">{{ \Illuminate\Support\Str::limit($r['detail'], 90) ?: '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @else
    <p class="text-success small mb-0"><i class="bi bi-check-circle me-1"></i>{{ __('No preservation failures or warnings recorded. Nothing needs attention right now.') }}</p>
    @endif
  </div>
</div>

@endif

<p class="text-muted small mb-0 mt-4">
  {{ __('This report is read-only. It aggregates the current state of the canonical preservation stores and makes no changes to any record or file. It surfaces what needs attention; acting on it happens elsewhere. Counts reflect data currently in the system and are not tied to any one country\'s rules.') }}
</p>

@endsection
