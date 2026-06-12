{{--
  Preservation maturity self-assessment dashboard (admin). Read-only assessment
  that scores the running instance, evidence-based, against the five functional
  areas of the NDSA Levels of Digital Preservation. Built from
  AhgCore\Services\PreservationMaturityService. Jurisdiction-neutral: the NDSA
  Levels are a generic self-assessment grid, used here without country-specific
  framing.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Preservation maturity'))

@section('content')
@php
  $assessment = $assessment ?? [];
  $areas = $assessment['areas'] ?? [];
  $overallLevel = (int) ($assessment['overall_level'] ?? 0);
  $overallLevelName = $assessment['overall_level_name'] ?? __('Not yet');
  $maxLevel = (int) ($assessment['max_level'] ?? 4);
  $framework = $assessment['framework'] ?? 'NDSA Levels of Digital Preservation';
  $frameworkNote = $assessment['framework_note'] ?? '';
  $digitalObjects = (int) ($assessment['digital_objects'] ?? 0);
  $generatedAt = $assessment['generated_at'] ?? null;
  $hasError = ! empty($assessment['error']);

  // Map a level (0..4) to a Bootstrap colour band. Higher is greener.
  $levelClass = function (int $lvl): array {
      return match (true) {
          $lvl >= 4 => ['text' => 'text-success', 'bg' => 'bg-success', 'badge' => 'bg-success'],
          $lvl === 3 => ['text' => 'text-success', 'bg' => 'bg-success', 'badge' => 'bg-success'],
          $lvl === 2 => ['text' => 'text-warning', 'bg' => 'bg-warning', 'badge' => 'bg-warning text-dark'],
          $lvl === 1 => ['text' => 'text-warning', 'bg' => 'bg-warning', 'badge' => 'bg-warning text-dark'],
          default    => ['text' => 'text-danger', 'bg' => 'bg-danger', 'badge' => 'bg-secondary'],
      };
  };

  // Icon per functional area, matched by key.
  $areaIcon = [
      'storage'   => 'fa-database',
      'integrity' => 'fa-fingerprint',
      'control'   => 'fa-user-shield',
      'metadata'  => 'fa-tags',
      'content'   => 'fa-file-lines',
  ];
@endphp

<div class="container-fluid py-3">

  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
    <h1 class="h4 mb-0"><i class="fas fa-shield-halved me-2 text-primary"></i>{{ __('Preservation maturity') }}</h1>
    <span class="text-muted small">{{ __('How this repository scores against the NDSA Levels of Digital Preservation') }}</span>
    <span class="ms-auto"></span>
    @if(Route::has('preservation-self-assessment.index'))
      <a href="{{ route('preservation-self-assessment.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-clipboard-list me-1"></i>{{ __('Self-assessment') }}
      </a>
    @endif
    @if(Route::has('data-quality.index'))
      <a href="{{ route('data-quality.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-clipboard-check me-1"></i>{{ __('Metadata completeness') }}
      </a>
    @endif
  </div>
  <p class="text-muted small mb-3" style="max-width:880px">
    {{ __('This self-assessment scores the running repository, from concrete evidence in its own records, against the five functional areas of the NDSA Levels of Digital Preservation. Each area is graded from Not yet through Level 4. The scoring is deliberately conservative: where the platform holds no evidence for a practice, the area is graded lower and a recommendation is shown. It is read-only and never changes a record.') }}
  </p>

  @if($hasError)
    <div class="alert alert-warning"><i class="fas fa-circle-exclamation me-1"></i>{{ __('The maturity assessment could not be fully built from the catalogue right now. Some areas may read as Not yet. Please try again later.') }}</div>
  @endif

  @if(empty($areas))
    {{-- Clean "nothing to assess" state - never a 500. --}}
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-5">
        <div class="display-6 text-muted mb-2">{{ __('Not yet') }}</div>
        <h2 class="h5">{{ __('No preservation evidence to assess yet') }}</h2>
        <p class="text-muted mb-0" style="max-width:600px;margin:0 auto">
          {{ __('Once digital objects are ingested with checksums, format identification and preservation metadata, this dashboard will score the repository against each NDSA level and highlight the next gaps to close.') }}
        </p>
      </div>
    </div>
  @else
    @php
      $ov = $levelClass($overallLevel);
      $ovWidth = max(0, min(100, $maxLevel > 0 ? ($overallLevel / $maxLevel) * 100 : 0));
    @endphp

    {{-- Overall summary --}}
    <div class="row g-3 mb-3">
      <div class="col-12 col-lg-5">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small text-uppercase mb-1">{{ __('Overall maturity') }}</div>
            <div class="d-flex align-items-baseline gap-2">
              <div class="display-5 mb-0 {{ $ov['text'] }}">{{ __($overallLevelName) }}</div>
              <div class="text-muted small">{{ __('of') }} {{ __('Level') }} {{ $maxLevel }}</div>
            </div>
            <div class="progress mt-2" style="height:10px" role="progressbar"
                 aria-valuenow="{{ $overallLevel }}" aria-valuemin="0" aria-valuemax="{{ $maxLevel }}"
                 aria-label="{{ __('Overall preservation maturity') }}">
              <div class="progress-bar {{ $ov['bg'] }}" style="width: {{ $ovWidth }}%"></div>
            </div>
            <p class="text-muted small mb-0 mt-2">
              {{ __('Overall maturity is the lowest level achieved across the five areas - a preservation programme is only as strong as its weakest area.') }}
            </p>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-7">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
              <span class="fw-semibold">{{ $framework }}</span>
              @if($digitalObjects > 0)
                <span class="ms-auto text-muted small">{{ number_format($digitalObjects) }} {{ __('digital object(s) in scope') }}</span>
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
          $aLevel = (int) ($area['level'] ?? 0);
          $aLevelName = $area['level_name'] ?? __('Not yet');
          $aEvidence = $area['evidence'] ?? '';
          $aGap = $area['gap'] ?? '';
          $cls = $levelClass($aLevel);
          $icon = $areaIcon[$aKey] ?? 'fa-shield-halved';
          $aWidth = max(0, min(100, $maxLevel > 0 ? ($aLevel / $maxLevel) * 100 : 0));
        @endphp
        <div class="col-12 col-xl-6">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
              <div class="d-flex align-items-start gap-3">
                <div class="text-center" style="min-width:5.5rem">
                  <span class="badge {{ $cls['badge'] }} fs-6 px-3 py-2 d-inline-block">{{ __($aLevelName) }}</span>
                </div>
                <div class="flex-grow-1">
                  <h2 class="h6 mb-0"><i class="fas {{ $icon }} me-2 text-muted"></i>{{ __($aName) }}</h2>
                  <div class="text-muted small">{{ __($aSub) }}</div>
                </div>
              </div>

              <div class="progress mt-3" style="height:8px" role="progressbar"
                   aria-valuenow="{{ $aLevel }}" aria-valuemin="0" aria-valuemax="{{ $maxLevel }}"
                   aria-label="{{ __($aName) }}">
                <div class="progress-bar {{ $cls['bg'] }}" style="width: {{ $aWidth }}%"></div>
              </div>

              @if($aEvidence !== '')
                <div class="mt-3">
                  <div class="text-uppercase text-muted small fw-semibold mb-1"><i class="fas fa-magnifying-glass me-1"></i>{{ __('Evidence') }}</div>
                  <p class="small mb-0">{{ __($aEvidence) }}</p>
                </div>
              @endif

              @if($aGap !== '')
                <div class="mt-3">
                  <div class="text-uppercase text-muted small fw-semibold mb-1"><i class="fas fa-arrow-trend-up me-1"></i>{{ __('Next step') }}</div>
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
