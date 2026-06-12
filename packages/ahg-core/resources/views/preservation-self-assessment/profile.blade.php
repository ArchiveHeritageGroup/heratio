{{--
  Preservation maturity SELF-ASSESSMENT - profile (admin). heratio#1244.

  The maturity profile for one saved run: overall self-rated maturity plus a
  per-section view as a CSS-only radar (conic-gradient-free SVG polygon, no charting
  library) and horizontal level bars, with each section's evidence note. A .json
  export and an "edit" link round it out. Bootstrap 5 + central theme.
  Jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Preservation maturity profile'))

@section('content')
@php
  $run         = $run ?? [];
  $sections    = $run['sections'] ?? [];
  $levelLabels = $levelLabels ?? [];
  $maxLevel    = (int) ($maxLevel ?? 4);
  $overall     = (float) ($run['overall'] ?? 0);

  $bandClass = function (float $lvl) use ($maxLevel): string {
      $ratio = $maxLevel > 0 ? $lvl / $maxLevel : 0;
      return match (true) {
          $ratio >= 0.75 => 'bg-success',
          $ratio >= 0.5  => 'bg-info',
          $ratio >= 0.25 => 'bg-warning',
          default        => 'bg-danger',
      };
  };

  // ---- CSS-only radar: compute an SVG polygon from section levels ----
  $n = count($sections);
  $cx = 150; $cy = 150; $r = 120;
  $polyPoints = [];
  $axisLines  = [];
  $axisLabels = [];
  if ($n > 0) {
    foreach (array_values($sections) as $idx => $section) {
      $angle = (-90 + (360 / $n) * $idx) * M_PI / 180;
      $ratio = $maxLevel > 0 ? ((int) $section['level']) / $maxLevel : 0;
      $px = $cx + cos($angle) * $r * $ratio;
      $py = $cy + sin($angle) * $r * $ratio;
      $polyPoints[] = round($px, 1).','.round($py, 1);
      // axis spoke to the outer ring
      $axisLines[] = [round($cx + cos($angle) * $r, 1), round($cy + sin($angle) * $r, 1)];
      // short axis label position just outside the ring
      $axisLabels[] = [
        'x' => round($cx + cos($angle) * ($r + 14), 1),
        'y' => round($cy + sin($angle) * ($r + 14), 1),
        'name' => $section['name'],
      ];
    }
  }
  $polyAttr = implode(' ', $polyPoints);
@endphp

<div class="container-fluid py-3">

  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
    <a href="{{ route('preservation-self-assessment.index') }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
    </a>
    <h1 class="h4 mb-0 ms-2"><i class="fas fa-chart-column me-2 text-primary"></i>{{ __('Maturity profile') }}</h1>
    <span class="ms-auto"></span>
    <a href="{{ route('preservation-self-assessment.edit', ['id' => $run['id']]) }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-pen me-1"></i>{{ __('Edit') }}
    </a>
    <a href="{{ route('preservation-self-assessment.export', ['id' => $run['id']]) }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-file-export me-1"></i>{{ __('Export JSON') }}
    </a>
  </div>

  @foreach(['success' => 'alert-success', 'error' => 'alert-danger'] as $key => $cls)
    @if(session($key))
      <div class="alert {{ $cls }} py-2"><i class="fas fa-circle-info me-1"></i>{{ session($key) }}</div>
    @endif
  @endforeach

  <div class="row g-3 mb-3">
    {{-- Overall + meta --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body">
          <div class="text-muted small text-uppercase mb-1">{{ __('Overall self-rated maturity') }}</div>
          <div class="d-flex align-items-baseline gap-2">
            <div class="display-5 mb-0">{{ $overall }}</div>
            <div class="text-muted small">{{ __('of') }} {{ $maxLevel }}</div>
          </div>
          <div class="progress mt-2" style="height:10px" role="progressbar"
               aria-valuenow="{{ $overall }}" aria-valuemin="0" aria-valuemax="{{ $maxLevel }}">
            <div class="progress-bar {{ $bandClass($overall) }}" style="width: {{ $maxLevel > 0 ? min(100, ($overall / $maxLevel) * 100) : 0 }}%"></div>
          </div>
          <dl class="row small mt-3 mb-0">
            <dt class="col-5 text-muted">{{ __('Model') }}</dt><dd class="col-7">{{ __($run['model_name'] ?? '') }}</dd>
            <dt class="col-5 text-muted">{{ __('Date') }}</dt><dd class="col-7">{{ $run['assessment_date'] ?? '-' }}</dd>
            <dt class="col-5 text-muted">{{ __('Assessor') }}</dt><dd class="col-7">{{ $run['assessor'] ?? '-' }}</dd>
            <dt class="col-5 text-muted">{{ __('Status') }}</dt>
            <dd class="col-7"><span class="badge {{ ($run['status'] ?? '') === 'complete' ? 'bg-success' : 'bg-secondary' }}">{{ __(ucfirst($run['status'] ?? 'draft')) }}</span></dd>
          </dl>
          @if(! empty($run['notes']))
            <p class="text-muted small mt-2 mb-0">{{ $run['notes'] }}</p>
          @endif
        </div>
      </div>
    </div>

    {{-- CSS/SVG radar --}}
    <div class="col-12 col-lg-8">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body">
          <h2 class="h6 mb-3"><i class="fas fa-radar me-2 text-muted"></i>{{ __('Maturity profile by section') }}</h2>
          @if($n === 0)
            <p class="text-muted small mb-0">{{ __('No sections to display.') }}</p>
          @else
            <div class="d-flex justify-content-center">
              <svg viewBox="0 0 300 300" width="100%" style="max-width:360px" role="img"
                   aria-label="{{ __('Maturity radar') }}">
                {{-- concentric rings for each level --}}
                @for($ring = 1; $ring <= $maxLevel; $ring++)
                  <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ round($r * $ring / $maxLevel, 1) }}"
                          fill="none" stroke="#dee2e6" stroke-width="1"></circle>
                @endfor
                {{-- axis spokes --}}
                @foreach($axisLines as $ax)
                  <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $ax[0] }}" y2="{{ $ax[1] }}" stroke="#e9ecef" stroke-width="1"></line>
                @endforeach
                {{-- the data polygon --}}
                @if(count($polyPoints) >= 3)
                  <polygon points="{{ $polyAttr }}" fill="rgba(13,110,253,.20)" stroke="#0d6efd" stroke-width="2"></polygon>
                @elseif(count($polyPoints) > 0)
                  <polyline points="{{ $polyAttr }}" fill="none" stroke="#0d6efd" stroke-width="2"></polyline>
                @endif
                {{-- vertex dots --}}
                @foreach($polyPoints as $pt)
                  @php [$vx, $vy] = explode(',', $pt); @endphp
                  <circle cx="{{ $vx }}" cy="{{ $vy }}" r="3" fill="#0d6efd"></circle>
                @endforeach
              </svg>
            </div>
            <div class="d-flex flex-wrap justify-content-center gap-2 small text-muted">
              @foreach($axisLabels as $al)
                <span class="badge bg-light text-dark border">{{ __($al['name']) }}</span>
              @endforeach
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Per-section bars + evidence --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <h2 class="h6 mb-3"><i class="fas fa-list-ol me-2 text-muted"></i>{{ __('Section ratings') }}</h2>
      @if(empty($sections))
        <p class="text-muted small mb-0">{{ __('No section ratings recorded.') }}</p>
      @else
        @foreach($sections as $section)
          @php
            $lvl = (int) ($section['level'] ?? 0);
            $label = $levelLabels[$lvl] ?? (string) $lvl;
            $w = $maxLevel > 0 ? min(100, ($lvl / $maxLevel) * 100) : 0;
          @endphp
          <div class="mb-3">
            <div class="d-flex align-items-baseline gap-2">
              <span class="fw-semibold small">{{ __($section['name']) }}</span>
              <span class="ms-auto badge {{ $bandClass((float) $lvl) }}">{{ $lvl }} - {{ __($label) }}</span>
            </div>
            <div class="progress mt-1" style="height:8px" role="progressbar"
                 aria-valuenow="{{ $lvl }}" aria-valuemin="0" aria-valuemax="{{ $maxLevel }}">
              <div class="progress-bar {{ $bandClass((float) $lvl) }}" style="width: {{ $w }}%"></div>
            </div>
            @if(! empty($section['evidence']))
              <p class="text-muted small mb-0 mt-1"><i class="fas fa-quote-left me-1"></i>{{ $section['evidence'] }}</p>
            @endif
          </div>
        @endforeach
      @endif
    </div>
  </div>

  <p class="text-muted small mb-0 mt-3">
    {{ __('This is a human, organisational self-assessment. For the automatically computed maturity reading derived from the records in this instance, see the computed maturity dashboard.') }}
    @if(Route::has('preservation-maturity.index'))
      <a href="{{ route('preservation-maturity.index') }}">{{ __('Open computed dashboard') }}</a>
    @endif
  </p>

</div>
@endsection
