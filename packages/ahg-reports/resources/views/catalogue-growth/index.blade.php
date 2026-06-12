{{--
  Catalogue Growth report - a read-only, management-facing view of how the
  catalogue has grown and how it is composed: headline totals (records, published
  vs unpublished, digital objects, actors, repositories), a records-created-per-
  month time series (only when a real creation timestamp exists), and the
  composition of the catalogue by level of description, by holding repository, and
  by digital-surrogate presence - each rendered as a CSS bar (no charting library).

  Honesty: the time series is shown ONLY when a real creation timestamp is present
  on the schema. When it is not, the page says so plainly and shows current
  composition only - no dates are invented. There is no publication-time signal on
  this schema, so no published-per-month series is ever fabricated.

  International, jurisdiction-neutral copy. Read-only, never 500s, empty-state safe.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Catalogue growth report')
@section('body-class', 'admin reports')

@php
  // Colour the published-share gauge by band. Pure presentation, no consequence.
  $publishedPct = $published_pct ?? 0.0;
  if ($publishedPct >= 80) {
      $pubClass = 'text-success';
      $pubBar   = 'bg-success';
  } elseif ($publishedPct >= 50) {
      $pubClass = 'text-warning';
      $pubBar   = 'bg-warning';
  } else {
      $pubClass = 'text-danger';
      $pubBar   = 'bg-danger';
  }
@endphp

@section('sidebar')
<section class="card mb-3">
  <div class="card-body">
    @if(Route::has('reports.dashboard'))
    <a href="{{ route('reports.dashboard') }}" class="btn btn-outline-secondary btn-sm w-100">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Reports') }}
    </a>
    @endif
    @if(Route::has('trust.console'))
    <a href="{{ route('trust.console') }}" class="btn btn-outline-secondary btn-sm w-100 mt-2">
      <i class="bi bi-patch-check me-1"></i>{{ __('Trust and Transparency Console') }}
    </a>
    @endif
    @if(Route::has('reports.data-quality'))
    <a href="{{ route('reports.data-quality') }}" class="btn btn-outline-secondary btn-sm w-100 mt-2">
      <i class="bi bi-check2-square me-1"></i>{{ __('Data-quality report') }}
    </a>
    @endif
    @if(Route::has('reports.ai-usage'))
    <a href="{{ route('reports.ai-usage') }}" class="btn btn-outline-secondary btn-sm w-100 mt-2">
      <i class="bi bi-robot me-1"></i>{{ __('AI usage report') }}
    </a>
    @endif
  </div>
</section>
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">{{ __('About this report') }}</h6></div>
  <div class="card-body small text-muted">
    {{ __('A read-only management view of how the catalogue has grown and how it is composed: how many records exist, how many are published, how many carry a digital object, and - where a creation timestamp is recorded - how many records were created each month. It counts, it changes nothing.') }}
  </div>
</section>
@if($available && $total > 0)
<section class="card mb-3">
  <div class="card-body small">
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Total records') }}</span>
      <span class="fw-bold">{{ number_format($total) }}</span>
    </div>
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Published') }}</span>
      <span class="fw-bold {{ $pubClass }}">{{ number_format($publishedPct, 1) }}%</span>
    </div>
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('With a digital object') }}</span>
      <span class="fw-bold">{{ number_format($with_digital_pct, 1) }}%</span>
    </div>
  </div>
</section>
@endif
@endsection

@section('title-block')
<h1>{{ __('Catalogue growth report') }}</h1>
<p class="text-muted mb-0">{{ __('How the catalogue has grown and how it is composed') }}</p>
@endsection

@section('content')

@if(! $available || $total <= 0)
  {{-- Empty state: fresh install or nothing catalogued yet. --}}
  <div class="card">
    <div class="card-body text-center py-5">
      <div class="display-6 text-muted mb-2"><i class="bi bi-archive"></i></div>
      <h2 class="h5">{{ __('Nothing catalogued yet') }}</h2>
      <p class="text-muted mb-0">
        {{ __('There are no archival descriptions in the catalogue yet. As records are created and published, this report will show how the catalogue grows over time and how it is composed by level of description, by holding repository, and by digital-surrogate coverage.') }}
      </p>
    </div>
  </div>
@else

{{-- Headline strip: size + published share (CSS only, no charting library) --}}
<div class="card mb-4">
  <div class="card-body">
    <div class="row align-items-center g-4">
      <div class="col-md-3 text-center border-end">
        <div class="display-5 fw-bold">{{ number_format($total) }}</div>
        <div class="text-uppercase small text-muted">{{ __('records') }}</div>
      </div>
      <div class="col-md-3 text-center border-end">
        <div class="display-5 fw-bold">{{ number_format($with_digital) }}</div>
        <div class="text-uppercase small text-muted">{{ __('with a digital object') }}</div>
      </div>
      <div class="col-md-6">
        <div class="d-flex justify-content-between mb-1">
          <span class="fw-semibold"><i class="bi bi-eye me-1"></i>{{ __('Published') }}</span>
          <span class="fw-bold {{ $pubClass }}">{{ number_format($publishedPct, 1) }}%</span>
        </div>
        <div class="progress" style="height: 1.25rem;" role="progressbar"
             aria-valuenow="{{ (int) round($publishedPct) }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ __('Published share') }}">
          <div class="progress-bar {{ $pubBar }}" style="width: {{ max(0, min(100, $publishedPct)) }}%;">
            {{ number_format($publishedPct, 1) }}%
          </div>
        </div>
        <p class="text-muted small mb-0 mt-2">
          <strong>{{ number_format($published) }}</strong>
          {{ __('published') }},
          <strong>{{ number_format($unpublished) }}</strong>
          {{ __('not yet published, of') }}
          <strong>{{ number_format($total) }}</strong>
          {{ __('records in total.') }}
        </p>
      </div>
    </div>
  </div>
</div>

{{-- Secondary totals: digital objects, actors, repositories --}}
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body d-flex align-items-center">
        <i class="bi bi-images fs-3 text-muted me-3"></i>
        <div>
          <div class="h4 mb-0">{{ number_format($digital_objects) }}</div>
          <div class="small text-muted">{{ __('digital objects held') }}</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body d-flex align-items-center">
        <i class="bi bi-people fs-3 text-muted me-3"></i>
        <div>
          <div class="h4 mb-0">{{ number_format($actors) }}</div>
          <div class="small text-muted">{{ __('authority records (actors)') }}</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body d-flex align-items-center">
        <i class="bi bi-building fs-3 text-muted me-3"></i>
        <div>
          <div class="h4 mb-0">{{ number_format($repositories) }}</div>
          <div class="small text-muted">{{ __('repositories') }}</div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Growth over time: records created per month as CSS bars (no charting library).
     Shown ONLY when a real creation timestamp exists; otherwise an honest note. --}}
@if($has_timeline && ! empty($timeline))
<div class="card mb-4">
  <div class="card-header"><h3 class="h6 mb-0">{{ __('Records created over time') }}</h3></div>
  <div class="card-body">
    <p class="text-muted small mb-3">{{ __('Archival descriptions created per month over the trailing year, from each record\'s recorded creation timestamp. A flat or empty month simply means few or no records were created that month.') }}</p>
    <div class="d-flex align-items-end gap-2" style="height: 180px;" role="img"
         aria-label="{{ __('Records created per month, last 12 months') }}">
      @foreach($timeline as $m)
        @php
          $h = $timeline_max > 0 ? max(2, (int) round($m['count'] / $timeline_max * 100)) : 2;
        @endphp
        <div class="d-flex flex-column align-items-center justify-content-end flex-fill" style="height: 100%;">
          <div class="small text-muted mb-1">{{ $m['count'] > 0 ? number_format($m['count']) : '' }}</div>
          <div class="w-100 rounded-top {{ $m['count'] > 0 ? 'bg-primary' : 'bg-light border' }}"
               style="height: {{ $h }}%;"
               title="{{ $m['label'] }}: {{ number_format($m['count']) }} {{ __('records created') }}"></div>
          <div class="text-muted mt-1" style="font-size: 0.7rem; white-space: nowrap;">{{ $m['label'] }}</div>
        </div>
      @endforeach
    </div>
    <p class="text-muted small mb-0 mt-3">
      <i class="bi bi-info-circle me-1"></i>{{ __('This schema records a creation timestamp but no publication timestamp, so a published-per-month series is not available and none is shown. The bars above count when records were created, not when they were published.') }}
    </p>
  </div>
</div>
@else
<div class="card mb-4 border-secondary-subtle">
  <div class="card-body">
    <h3 class="h6 mb-2"><i class="bi bi-info-circle me-1"></i>{{ __('Growth over time not available') }}</h3>
    <p class="text-muted small mb-0">
      {{ __('Creation timestamps are not recorded on this installation, so a records-created-per-month series cannot be shown without inventing dates. The current composition of the catalogue is shown below instead.') }}
    </p>
  </div>
</div>
@endif

<div class="row g-4">
  {{-- Composition by level of description --}}
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3 class="h6 mb-0">{{ __('By level of description') }}</h3></div>
      <div class="card-body">
        <p class="text-muted small mb-3">{{ __('How the catalogue is composed across arrangement levels (fonds, series, file, item, and so on).') }}</p>
        @forelse($by_level as $row)
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-1">
            <span class="fw-semibold">{{ $row['label'] }}</span>
            <span class="small text-muted">{{ number_format($row['count']) }} ({{ number_format($row['pct'], 1) }}%)</span>
          </div>
          <div class="progress" style="height: 0.75rem;" role="progressbar"
               aria-valuenow="{{ (int) round($row['pct']) }}" aria-valuemin="0" aria-valuemax="100"
               aria-label="{{ $row['label'] }}">
            <div class="progress-bar bg-primary" style="width: {{ max(0, min(100, $row['pct'])) }}%;"></div>
          </div>
        </div>
        @empty
        <p class="text-muted small mb-0">{{ __('No level-of-description data to show.') }}</p>
        @endforelse
      </div>
    </div>
  </div>

  {{-- Composition by holding repository --}}
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3 class="h6 mb-0">{{ __('By repository') }}</h3></div>
      <div class="card-body">
        <p class="text-muted small mb-3">{{ __('The repositories holding the most records (top ten), with any unassigned records shown separately.') }}</p>
        @forelse($by_repository as $row)
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-1">
            <span class="fw-semibold {{ ! empty($row['is_unset']) ? 'fst-italic text-muted' : '' }}">{{ $row['label'] }}</span>
            <span class="small text-muted">{{ number_format($row['count']) }} ({{ number_format($row['pct'], 1) }}%)</span>
          </div>
          <div class="progress" style="height: 0.75rem;" role="progressbar"
               aria-valuenow="{{ (int) round($row['pct']) }}" aria-valuemin="0" aria-valuemax="100"
               aria-label="{{ $row['label'] }}">
            <div class="progress-bar {{ ! empty($row['is_unset']) ? 'bg-secondary' : 'bg-info' }}" style="width: {{ max(0, min(100, $row['pct'])) }}%;"></div>
          </div>
        </div>
        @empty
        <p class="text-muted small mb-0">{{ __('No repository data to show.') }}</p>
        @endforelse
      </div>
    </div>
  </div>
</div>

{{-- Composition by digital-surrogate presence --}}
<div class="card mt-4">
  <div class="card-header"><h3 class="h6 mb-0">{{ __('By digital surrogate') }}</h3></div>
  <div class="card-body">
    <p class="text-muted small mb-3">{{ __('How much of the catalogue carries a digital object (a scanned image, document or other surrogate) versus how much is description only.') }}</p>
    @foreach($by_digital as $row)
    <div class="mb-3">
      <div class="d-flex justify-content-between align-items-baseline mb-1">
        <span class="fw-semibold"><i class="bi bi-{{ $row['icon'] }} me-1"></i>{{ $row['label'] }}</span>
        <span class="small text-muted">{{ number_format($row['count']) }} ({{ number_format($row['pct'], 1) }}%)</span>
      </div>
      <div class="progress" style="height: 0.75rem;" role="progressbar"
           aria-valuenow="{{ (int) round($row['pct']) }}" aria-valuemin="0" aria-valuemax="100"
           aria-label="{{ $row['label'] }}">
        <div class="progress-bar {{ $loop->first ? 'bg-success' : 'bg-secondary' }}" style="width: {{ max(0, min(100, $row['pct'])) }}%;"></div>
      </div>
    </div>
    @endforeach
  </div>
</div>

<p class="text-muted small mb-0 mt-4">
  {{ __('This report is read-only. It aggregates current catalogue counts only; it makes no changes to any record. Counts reflect data currently in the system and are not tied to any one country\'s rules.') }}
</p>

@endif
@endsection
