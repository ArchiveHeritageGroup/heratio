{{--
  AI Usage transparency report - a read-only aggregate of how much AI has
  assisted the catalogue: total inferences logged, distinct records touched, the
  breakdown by inference type and by model, the human-reviewed share (framed as
  accountability), and a per-month over-time trend rendered as CSS bars.

  Framed honestly: this is transparency about WHERE AI assisted, with a human
  accountable. Nothing here implies AI decides anything - AI proposes metadata,
  a person remains responsible for the record. International, jurisdiction-neutral
  copy. Read-only, never 500s, empty-state safe.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'AI usage transparency report')
@section('body-class', 'admin reports')

@php
  // Colour the oversight gauge by band. Pure presentation, no logic of consequence.
  $reviewedPct = $reviewed_pct ?? 0.0;
  if ($reviewedPct >= 80) {
      $oversightClass = 'text-success';
      $oversightBar   = 'bg-success';
  } elseif ($reviewedPct >= 50) {
      $oversightClass = 'text-warning';
      $oversightBar   = 'bg-warning';
  } else {
      $oversightClass = 'text-danger';
      $oversightBar   = 'bg-danger';
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
  </div>
</section>
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">{{ __('About this report') }}</h6></div>
  <div class="card-body small text-muted">
    {{ __('An honest, read-only aggregate of where AI has assisted with the catalogue: which inference types ran, which models produced them, the trend over time, and how much of that AI output a human has since reviewed. AI proposes metadata; a person stays accountable for the record. It counts, it changes nothing.') }}
  </div>
</section>
@if($available && $total > 0)
<section class="card mb-3">
  <div class="card-body small">
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Inferences logged') }}</span>
      <span class="fw-bold">{{ number_format($total) }}</span>
    </div>
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Records touched') }}</span>
      <span class="fw-bold">{{ number_format($records_touched) }}</span>
    </div>
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Human-reviewed') }}</span>
      <span class="fw-bold {{ $oversightClass }}">{{ number_format($reviewedPct, 1) }}%</span>
    </div>
  </div>
</section>
@endif
@endsection

@section('title-block')
<h1>{{ __('AI usage transparency report') }}</h1>
<p class="text-muted mb-0">{{ __('Where AI assisted with the catalogue, with a human accountable') }}</p>
@endsection

@section('content')

@if(! $available || $total <= 0)
  {{-- Empty state: fresh install or no AI recorded yet. --}}
  <div class="card">
    <div class="card-body text-center py-5">
      <div class="display-6 text-muted mb-2"><i class="bi bi-robot"></i></div>
      <h2 class="h5">{{ __('No AI activity recorded') }}</h2>
      <p class="text-muted mb-0">
        {{ __('No AI inferences have been logged against the catalogue yet. When AI tools such as entity recognition, summarisation, handwritten-text recognition or translation assist with a record, each inference is recorded here so you can see exactly where AI helped and how much of it a person has reviewed.') }}
      </p>
    </div>
  </div>
@else

{{-- Headline strip: totals + human-oversight gauge (CSS only, no charting library) --}}
<div class="card mb-4">
  <div class="card-body">
    <div class="row align-items-center g-4">
      <div class="col-md-3 text-center border-end">
        <div class="display-5 fw-bold">{{ number_format($total) }}</div>
        <div class="text-uppercase small text-muted">{{ __('inferences logged') }}</div>
      </div>
      <div class="col-md-3 text-center border-end">
        <div class="display-5 fw-bold">{{ number_format($records_touched) }}</div>
        <div class="text-uppercase small text-muted">{{ __('records touched') }}</div>
      </div>
      <div class="col-md-6">
        <div class="d-flex justify-content-between mb-1">
          <span class="fw-semibold"><i class="bi bi-person-check me-1"></i>{{ __('Human oversight') }}</span>
          <span class="fw-bold {{ $oversightClass }}">{{ number_format($reviewedPct, 1) }}%</span>
        </div>
        <div class="progress" style="height: 1.25rem;" role="progressbar"
             aria-valuenow="{{ (int) round($reviewedPct) }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ __('Human-reviewed share') }}">
          <div class="progress-bar {{ $oversightBar }}" style="width: {{ max(0, min(100, $reviewedPct)) }}%;">
            {{ number_format($reviewedPct, 1) }}%
          </div>
        </div>
        <p class="text-muted small mb-0 mt-2">
          <strong>{{ number_format($reviewed) }}</strong>
          {{ __('of') }}
          <strong>{{ number_format($total) }}</strong>
          {{ __('AI inferences carry a recorded human review or correction. AI proposes; a person remains accountable for what enters the record.') }}
        </p>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  {{-- Breakdown by inference type / task --}}
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3 class="h6 mb-0">{{ __('By inference type') }}</h3></div>
      <div class="card-body">
        <p class="text-muted small mb-3">{{ __('Each AI task that has assisted the catalogue, with how many inferences it produced.') }}</p>
        @foreach($by_type as $row)
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-1">
            <span class="fw-semibold">
              {{ $row['label'] }}
              @if(! empty($row['gateway']))
              <span class="badge bg-light text-muted fw-normal ms-1" title="{{ __('Endpoint host') }}">{{ $row['gateway'] }}</span>
              @endif
            </span>
            <span class="small text-muted">{{ number_format($row['count']) }} ({{ number_format($row['pct'], 1) }}%)</span>
          </div>
          <div class="progress" style="height: 0.75rem;" role="progressbar"
               aria-valuenow="{{ (int) round($row['pct']) }}" aria-valuemin="0" aria-valuemax="100"
               aria-label="{{ $row['label'] }}">
            <div class="progress-bar bg-primary" style="width: {{ max(0, min(100, $row['pct'])) }}%;"></div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Breakdown by model --}}
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3 class="h6 mb-0">{{ __('By model') }}</h3></div>
      <div class="card-body">
        <p class="text-muted small mb-3">{{ __('Each model that has produced an inference, with how many it produced.') }}</p>
        @foreach($by_model as $row)
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-1">
            <span class="fw-semibold font-monospace small">{{ $row['label'] }}</span>
            <span class="small text-muted">{{ number_format($row['count']) }} ({{ number_format($row['pct'], 1) }}%)</span>
          </div>
          <div class="progress" style="height: 0.75rem;" role="progressbar"
               aria-valuenow="{{ (int) round($row['pct']) }}" aria-valuemin="0" aria-valuemax="100"
               aria-label="{{ $row['label'] }}">
            <div class="progress-bar bg-info" style="width: {{ max(0, min(100, $row['pct'])) }}%;"></div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
</div>

{{-- Over-time trend: inferences per month as CSS bars (no charting library) --}}
@if(! empty($trend))
<div class="card mt-4">
  <div class="card-header"><h3 class="h6 mb-0">{{ __('Inferences over time') }}</h3></div>
  <div class="card-body">
    <p class="text-muted small mb-3">{{ __('AI inferences logged per month over the trailing year. A flat or empty month simply means little or no AI assistance was recorded that month.') }}</p>
    <div class="d-flex align-items-end gap-2" style="height: 180px;" role="img"
         aria-label="{{ __('Inferences per month, last 12 months') }}">
      @foreach($trend as $m)
        @php
          $h = $trend_max > 0 ? max(2, (int) round($m['count'] / $trend_max * 100)) : 2;
        @endphp
        <div class="d-flex flex-column align-items-center justify-content-end flex-fill" style="height: 100%;">
          <div class="small text-muted mb-1">{{ $m['count'] > 0 ? number_format($m['count']) : '' }}</div>
          <div class="w-100 rounded-top {{ $m['count'] > 0 ? 'bg-primary' : 'bg-light border' }}"
               style="height: {{ $h }}%;"
               title="{{ $m['label'] }}: {{ number_format($m['count']) }} {{ __('inferences') }}"></div>
          <div class="text-muted mt-1" style="font-size: 0.7rem; white-space: nowrap;">{{ $m['label'] }}</div>
        </div>
      @endforeach
    </div>
  </div>
</div>
@endif

<p class="text-muted small mb-0 mt-4">
  {{ __('This report is read-only. It aggregates the AI inference log and the human review log only; it makes no AI calls and changes no data. AI assists with metadata as a proposal; a person remains accountable for what is published. Counts reflect data currently in the system and are not tied to any one country\'s rules.') }}
</p>

@endif
@endsection
