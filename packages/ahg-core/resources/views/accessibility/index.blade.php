{{--
  Digital accessibility coverage dashboard (admin). Read-only HEURISTIC coverage
  report over the accessibility-relevant metadata Heratio stores - image
  descriptions, captions / subtitles, transcripts, 3D-model alternative text, and
  multilingual reach. Built from AhgCore\Services\AccessibilityReportService.
  This is NOT a WCAG conformance audit; it cites WCAG 2.1 AA success criteria as a
  neutral, international reference grid. Jurisdiction-neutral. Absence of a stored
  signal is reported honestly as "Not measured" with a gap recommendation, never
  invented coverage.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Digital accessibility'))

@section('content')
@php
  $report = $report ?? [];
  $areas = $report['areas'] ?? [];
  $total = (int) ($report['total_published'] ?? 0);
  $overallLevel = (int) ($report['overall_level'] ?? -1);
  $overallLevelName = $report['overall_level_name'] ?? __('Not measured');
  $framework = $report['framework'] ?? 'WCAG 2.1 AA';
  $frameworkNote = $report['framework_note'] ?? '';
  $generatedAt = $report['generated_at'] ?? null;
  $hasError = ! empty($report['error']);

  $maxLevel = 4;

  // Map a level (-1..4) to a Bootstrap colour band. -1 = not measured (neutral).
  $levelClass = function (int $lvl): array {
      return match (true) {
          $lvl >= 3   => ['text' => 'text-success', 'bg' => 'bg-success', 'badge' => 'bg-success'],
          $lvl === 2  => ['text' => 'text-warning', 'bg' => 'bg-warning', 'badge' => 'bg-warning text-dark'],
          $lvl === 1  => ['text' => 'text-warning', 'bg' => 'bg-warning', 'badge' => 'bg-warning text-dark'],
          $lvl === 0  => ['text' => 'text-danger',  'bg' => 'bg-danger',  'badge' => 'bg-danger'],
          default     => ['text' => 'text-muted',   'bg' => 'bg-secondary', 'badge' => 'bg-secondary'],
      };
  };

  // Icon per area, matched by key.
  $areaIcon = [
      'image_alt'    => 'fa-image',
      'captions'     => 'fa-closed-captioning',
      'transcripts'  => 'fa-file-lines',
      'model_alt'    => 'fa-cube',
      'multilingual' => 'fa-language',
  ];
@endphp

<div class="container-fluid py-3">

  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
    <h1 class="h4 mb-0"><i class="fas fa-universal-access me-2 text-primary"></i>{{ __('Digital accessibility') }}</h1>
    <span class="text-muted small">{{ __('How much of the published collection carries accessibility metadata') }}</span>
    <span class="ms-auto"></span>
    @if(Route::has('data-quality.index'))
      <a href="{{ route('data-quality.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-clipboard-check me-1"></i>{{ __('Metadata completeness') }}
      </a>
    @endif
    @if(Route::has('preservation-maturity.index'))
      <a href="{{ route('preservation-maturity.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-shield-halved me-1"></i>{{ __('Preservation maturity') }}
      </a>
    @endif
  </div>
  <p class="text-muted small mb-2" style="max-width:900px">
    {{ __('This dashboard reports how much of the published collection carries the accessibility-relevant metadata Heratio stores: text descriptions for images, captions or subtitles and transcripts for audio-visual material, alternative text for 3D models, and how much of the catalogue is readable in more than one language. Each area shows a coverage level and a recommendation for closing the gap. It is read-only and never changes a record.') }}
  </p>

  {{-- Honest framing: this is a coverage heuristic, not a conformance audit. --}}
  <div class="alert alert-info py-2 small mb-3" style="max-width:900px">
    <i class="fas fa-circle-info me-1"></i>
    {{ __('This is a heuristic coverage report over stored metadata - not a WCAG conformance audit. A full conformance audit also requires reviewing the running interface (keyboard operability, colour contrast, focus order, and more). The success criteria below are cited from :fw as an international reference.', ['fw' => $framework]) }}
  </div>

  @if($hasError)
    <div class="alert alert-warning"><i class="fas fa-circle-exclamation me-1"></i>{{ __('The accessibility report could not be fully built from the catalogue right now. Some areas may read as Not measured. Please try again later.') }}</div>
  @endif

  @if(empty($areas))
    {{-- Clean "nothing to assess" state - never a 500. --}}
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-5">
        <div class="display-6 text-muted mb-2"><i class="fas fa-universal-access"></i></div>
        <h2 class="h5">{{ __('No accessibility coverage to report yet') }}</h2>
        <p class="text-muted mb-0" style="max-width:620px;margin:0 auto">
          {{ __('Once content is published with image descriptions, captions, transcripts or multilingual titles, this dashboard will show how much of the collection is reachable by visitors who rely on those alternatives, and highlight the next gaps to close.') }}
        </p>
      </div>
    </div>
  @else
    @php
      $ov = $levelClass($overallLevel);
      $ovWidth = $overallLevel < 0 ? 0 : max(0, min(100, ($overallLevel / $maxLevel) * 100));

      // Count the measured areas so the summary can be honest about scope.
      $measuredCount = 0;
      foreach ($areas as $a) { if (! empty($a['measured'])) { $measuredCount++; } }
    @endphp

    {{-- Overall summary --}}
    <div class="row g-3 mb-3">
      <div class="col-12 col-lg-5">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small text-uppercase mb-1">{{ __('Overall accessibility coverage') }}</div>
            <div class="display-5 mb-0 {{ $ov['text'] }}">{{ __($overallLevelName) }}</div>
            <div class="progress mt-2" style="height:10px" role="progressbar"
                 aria-valuenow="{{ max(0, $overallLevel) }}" aria-valuemin="0" aria-valuemax="{{ $maxLevel }}"
                 aria-label="{{ __('Overall accessibility coverage') }}">
              <div class="progress-bar {{ $ov['bg'] }}" style="width: {{ $ovWidth }}%"></div>
            </div>
            <p class="text-muted small mb-0 mt-2">
              @if($measuredCount > 0)
                {{ __('Overall coverage is the lowest level across the measured areas - the collection is only as reachable as its weakest area. Areas with no applicable content, or with no place in the schema to record the signal, are shown as Not measured and excluded from this score.') }}
              @else
                {{ __('None of the accessibility areas could be measured from the catalogue yet. Each card below explains what is missing and what to record.') }}
              @endif
            </p>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-7">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
              <span class="fw-semibold">{{ $framework }}</span>
              @if($total > 0)
                <span class="ms-auto text-muted small">{{ number_format($total) }} {{ __('published record(s) in scope') }}</span>
              @endif
            </div>
            <p class="text-muted small mb-0">{{ __($frameworkNote) }}</p>
          </div>
        </div>
      </div>
    </div>

    {{-- Per-area cards --}}
    <div class="row g-3">
      @foreach($areas as $area)
        @php
          $aKey = $area['key'] ?? '';
          $aName = $area['name'] ?? $aKey;
          $aSub = $area['subtitle'] ?? '';
          $aWcag = $area['wcag'] ?? '';
          $aMeasured = ! empty($area['measured']);
          $aWith = (int) ($area['with'] ?? 0);
          $aTotal = (int) ($area['total'] ?? 0);
          $aPct = (float) ($area['pct'] ?? 0);
          $aLevel = (int) ($area['level'] ?? -1);
          $aLevelName = $area['level_name'] ?? __('Not measured');
          $aEvidence = $area['evidence'] ?? '';
          $aGap = $area['gap'] ?? '';
          $cls = $levelClass($aLevel);
          $icon = $areaIcon[$aKey] ?? 'fa-universal-access';
          $barWidth = max(0, min(100, $aPct));
        @endphp
        <div class="col-12 col-xl-6">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
              <div class="d-flex align-items-start gap-3">
                <div class="text-center" style="min-width:6.5rem">
                  <span class="badge {{ $cls['badge'] }} fs-6 px-3 py-2 d-inline-block">{{ __($aLevelName) }}</span>
                </div>
                <div class="flex-grow-1">
                  <h2 class="h6 mb-0"><i class="fas {{ $icon }} me-2 text-muted"></i>{{ __($aName) }}</h2>
                  <div class="text-muted small">{{ __($aSub) }}</div>
                  @if($aWcag !== '')
                    <div class="text-muted small mt-1"><i class="fas fa-book me-1"></i>{{ $aWcag }}</div>
                  @endif
                </div>
              </div>

              @if($aMeasured && $aTotal > 0)
                {{-- Big numbers --}}
                <div class="d-flex align-items-baseline gap-2 mt-3">
                  <div class="display-6 mb-0 {{ $cls['text'] }}">{{ number_format($aWith) }}</div>
                  <div class="text-muted">{{ __('of') }} {{ number_format($aTotal) }}</div>
                  <div class="ms-auto fs-5 fw-semibold {{ $cls['text'] }}">{{ rtrim(rtrim(number_format($aPct, 1), '0'), '.') }}%</div>
                </div>
                <div class="progress mt-1" style="height:8px" role="progressbar"
                     aria-valuenow="{{ $aWith }}" aria-valuemin="0" aria-valuemax="{{ $aTotal }}"
                     aria-label="{{ __($aName) }}">
                  <div class="progress-bar {{ $cls['bg'] }}" style="width: {{ $barWidth }}%"></div>
                </div>
              @else
                <div class="mt-3">
                  <span class="badge bg-secondary"><i class="fas fa-circle-minus me-1"></i>{{ __('Not measured') }}</span>
                </div>
              @endif

              @if($aEvidence !== '')
                <div class="mt-3">
                  <div class="text-uppercase text-muted small fw-semibold mb-1"><i class="fas fa-magnifying-glass me-1"></i>{{ __('Evidence') }}</div>
                  <p class="small mb-0">{{ __($aEvidence) }}</p>
                </div>
              @endif

              @if($aGap !== '')
                <div class="mt-3">
                  <div class="text-uppercase text-muted small fw-semibold mb-1"><i class="fas fa-arrow-trend-up me-1"></i>{{ __('Recommendation') }}</div>
                  <p class="small mb-0">{{ __($aGap) }}</p>
                </div>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>

    @if($generatedAt)
      <p class="text-muted small mb-0 mt-3">{{ __('Generated') }}: {{ $generatedAt }}</p>
    @endif
  @endif

</div>
@endsection
