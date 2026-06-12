{{--
  Collection Data-Quality report - a read-only, archivist-facing dashboard of
  ISAD(G) descriptive completeness across the PUBLISHED catalogue. Shows, per
  core ISAD(G) element, how many published records are missing it (count +
  share-of-total + a CSS progress bar), a headline completeness gauge (records
  carrying every core element), and a short "top gaps" summary.

  ISAD(G) is the international descriptive standard. This report is
  jurisdiction-neutral: it measures against the standard's element set, not any
  one country's cataloguing rule. Read-only, never 500s, empty-state safe.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Collection data-quality report')
@section('body-class', 'admin reports')

@php
  // Colour the gauge by band. Pure presentation, no logic of consequence.
  $score = $score ?? 0.0;
  if ($score >= 80) {
      $gaugeClass = 'text-success';
      $gaugeBar   = 'bg-success';
  } elseif ($score >= 50) {
      $gaugeClass = 'text-warning';
      $gaugeBar   = 'bg-warning';
  } else {
      $gaugeClass = 'text-danger';
      $gaugeBar   = 'bg-danger';
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
  </div>
</section>
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">{{ __('About this report') }}</h6></div>
  <div class="card-body small text-muted">
    {{ __('A descriptive-completeness check over the published catalogue, measured against the core ISAD(G) elements (the international standard for archival description). For each element it shows how many published records are missing it, so a cataloguer can see exactly where to focus. Read-only: it counts, it changes nothing.') }}
  </div>
</section>
@if($available && $total > 0)
<section class="card mb-3">
  <div class="card-body small">
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Published records') }}</span>
      <span class="fw-bold">{{ number_format($total) }}</span>
    </div>
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Fully described') }}</span>
      <span class="fw-bold text-success">{{ number_format($present) }}</span>
    </div>
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Completeness') }}</span>
      <span class="fw-bold {{ $gaugeClass }}">{{ number_format($score, 1) }}%</span>
    </div>
  </div>
</section>
@endif
@endsection

@section('title-block')
<h1>{{ __('Collection data-quality report') }}</h1>
<p class="text-muted mb-0">{{ __('ISAD(G) descriptive completeness across the published catalogue') }}</p>
@endsection

@section('content')

@if(! $available || $total <= 0)
  {{-- Empty state: fresh install or nothing published yet. --}}
  <div class="card">
    <div class="card-body text-center py-5">
      <div class="display-6 text-muted mb-2"><i class="bi bi-clipboard-data"></i></div>
      <h2 class="h5">{{ __('Nothing to measure yet') }}</h2>
      <p class="text-muted mb-0">
        {{ __('There are no published archival descriptions to assess for descriptive completeness. Once records are described and published, this report will show how completely they populate the core ISAD(G) elements.') }}
      </p>
    </div>
  </div>
@else

{{-- Headline completeness gauge (CSS only, no charting library) --}}
<div class="card mb-4">
  <div class="card-body">
    <div class="row align-items-center g-4">
      <div class="col-md-4 text-center">
        <div class="display-3 fw-bold {{ $gaugeClass }}">{{ number_format($score, 1) }}<span class="fs-3">%</span></div>
        <div class="text-uppercase small text-muted">{{ __('overall completeness') }}</div>
      </div>
      <div class="col-md-8">
        <p class="mb-2">
          <strong>{{ number_format($present) }}</strong>
          {{ __('of') }}
          <strong>{{ number_format($total) }}</strong>
          {{ __('published records carry every core ISAD(G) element: a title, a reference code, a date, a creator, scope and content, extent and medium, a level of description, and a repository.') }}
        </p>
        <div class="progress" style="height: 1.5rem;" role="progressbar"
             aria-valuenow="{{ (int) round($score) }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ __('Overall completeness') }}">
          <div class="progress-bar {{ $gaugeBar }}" style="width: {{ max(0, min(100, $score)) }}%;">
            {{ number_format($score, 1) }}%
          </div>
        </div>
        <p class="text-muted small mb-0 mt-2">
          {{ __('Completeness is the share of published records that carry all of the core ISAD(G) elements at once. A record missing any one element is not counted as fully described.') }}
        </p>
      </div>
    </div>
  </div>
</div>

{{-- Top gaps summary --}}
@if(! empty($top_gaps))
<div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 mb-4">
  <span class="fw-semibold me-1"><i class="bi bi-exclamation-triangle text-warning me-1"></i>{{ __('Most-missing elements:') }}</span>
  @foreach($top_gaps as $gap)
    <span class="badge bg-warning-subtle text-warning-emphasis">
      {{ __($gap['label']) }}
      <span class="fw-normal">- {{ number_format($gap['missing']) }} {{ __('missing') }} ({{ number_format($gap['missing_pct'], 1) }}%)</span>
    </span>
  @endforeach
</div>
@endif

{{-- Per-element completeness --}}
<h3 class="h5 mb-3">{{ __('Completeness by ISAD(G) element') }}</h3>
<div class="row g-3">
  @foreach($elements as $el)
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-start mb-2">
          <span class="fs-4 me-2 text-primary"><i class="bi bi-{{ $el['icon'] }}"></i></span>
          <div class="flex-grow-1">
            <h5 class="card-title mb-0">{{ __($el['label']) }}</h5>
            <span class="text-muted small">ISAD(G) {{ $el['isad'] }}</span>
          </div>
          <div class="text-end">
            <div class="fw-bold {{ $el['missing'] > 0 ? 'text-danger' : 'text-success' }}">
              {{ number_format($el['missing']) }}
            </div>
            <div class="text-muted small text-uppercase">{{ __('missing') }}</div>
          </div>
        </div>

        <p class="card-text text-muted small mb-2">{{ __($el['desc']) }}</p>

        {{-- Present-share progress bar (CSS only) --}}
        <div class="progress mb-1" style="height: 0.9rem;" role="progressbar"
             aria-valuenow="{{ (int) round($el['present_pct']) }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ __($el['label']) }} {{ __('present share') }}">
          <div class="progress-bar {{ $el['present_pct'] >= 80 ? 'bg-success' : ($el['present_pct'] >= 50 ? 'bg-warning' : 'bg-danger') }}"
               style="width: {{ max(0, min(100, $el['present_pct'])) }}%;"></div>
        </div>
        <div class="d-flex justify-content-between small text-muted">
          <span>{{ number_format($el['present']) }} {{ __('present') }} ({{ number_format($el['present_pct'], 1) }}%)</span>
          <span>{{ number_format($el['missing']) }} {{ __('missing') }} ({{ number_format($el['missing_pct'], 1) }}%)</span>
        </div>

        @if(! empty($el['filter_url']))
        <div class="mt-2">
          <a href="{{ $el['filter_url'] }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-funnel me-1"></i>{{ __('Show these records') }}
          </a>
        </div>
        @endif
      </div>
    </div>
  </div>
  @endforeach
</div>

<p class="text-muted small mb-0 mt-4">
  {{ __('This report is read-only and counts only published records (the synthetic root is excluded). Completeness is measured against the core ISAD(G) elements, the international standard for archival description; it is not tied to any one country\'s cataloguing rules. Counts reflect data currently in the system.') }}
</p>

@endif
@endsection
