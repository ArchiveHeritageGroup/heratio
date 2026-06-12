{{--
  Preservation maturity SELF-ASSESSMENT - landing page (admin). heratio#1244.

  The human, organisational counterpart to the read-only computed
  /admin/preservation-maturity dashboard. Lists past assessment runs (progress over
  time), shows a maturity history trend, and offers a "start a new assessment" form
  with a model picker (NDSA Levels / DPC RAM). Enumerated values come from the
  Dropdown Manager (assessment_model + maturity_level), never hardcoded. Bootstrap 5
  + central theme; CSS-only bars (no charting library). Jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Preservation maturity self-assessment'))

@section('content')
@php
  $available    = $available ?? false;
  $runs         = $runs ?? [];
  $history      = $history ?? [];
  $modelOptions = $modelOptions ?? [];
  $levelLabels  = $levelLabels ?? [];
  $maxLevel     = (int) ($maxLevel ?? 4);

  $statusBadge = function (string $s): string {
      return $s === 'complete' ? 'bg-success' : 'bg-secondary';
  };
  // Map an average level (0..max) to a Bootstrap colour band.
  $bandClass = function (float $lvl) use ($maxLevel): string {
      $ratio = $maxLevel > 0 ? $lvl / $maxLevel : 0;
      return match (true) {
          $ratio >= 0.75 => 'bg-success',
          $ratio >= 0.5  => 'bg-info',
          $ratio >= 0.25 => 'bg-warning',
          default        => 'bg-danger',
      };
  };
@endphp

<div class="container-fluid py-3">

  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
    <h1 class="h4 mb-0"><i class="fas fa-clipboard-list me-2 text-primary"></i>{{ __('Preservation maturity self-assessment') }}</h1>
    <span class="text-muted small">{{ __('Rate your institution against recognised international maturity models') }}</span>
    <span class="ms-auto"></span>
    @if(Route::has('preservation-maturity.index'))
      <a href="{{ route('preservation-maturity.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-shield-halved me-1"></i>{{ __('Computed maturity dashboard') }}
      </a>
    @endif
  </div>
  <p class="text-muted small mb-3" style="max-width:920px">
    {{ __('This self-assessment records what your institution says about its own digital-preservation practice, rated section by section against a recognised international maturity model. It complements the computed maturity dashboard, which derives a score from the records in this instance: this one captures human, organisational judgement and tracks how it changes over time. Two models are supported: the NDSA Levels of Digital Preservation and the DPC Rapid Assessment Model (DPC RAM).') }}
  </p>

  @foreach(['success' => 'alert-success', 'error' => 'alert-danger'] as $key => $cls)
    @if(session($key))
      <div class="alert {{ $cls }} py-2"><i class="fas fa-circle-info me-1"></i>{{ session($key) }}</div>
    @endif
  @endforeach

  @if(! $available)
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-5">
        <div class="display-6 text-muted mb-2"><i class="fas fa-clipboard-list"></i></div>
        <h2 class="h5">{{ __('Self-assessment is being set up') }}</h2>
        <p class="text-muted mb-0" style="max-width:560px;margin:0 auto">
          {{ __('The self-assessment tables are not installed on this instance yet. They are created automatically on the next boot; please check back shortly.') }}
        </p>
      </div>
    </div>
  @else
    <div class="row g-3">

      {{-- Start a new assessment --}}
      <div class="col-12 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h2 class="h6 mb-3"><i class="fas fa-plus-circle me-2 text-success"></i>{{ __('Start a new assessment') }}</h2>
            <form method="POST" action="{{ route('preservation-self-assessment.start') }}">
              @csrf
              <div class="mb-3">
                <label class="form-label small fw-semibold" for="model">{{ __('Maturity model') }}</label>
                <select class="form-select form-select-sm" id="model" name="model" required>
                  @foreach($modelOptions as $opt)
                    <option value="{{ $opt['code'] }}">{{ __($opt['label']) }}</option>
                  @endforeach
                </select>
                @error('model')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold" for="title">{{ __('Title (optional)') }}</label>
                <input type="text" class="form-control form-control-sm" id="title" name="title" maxlength="255"
                       value="{{ old('title') }}" placeholder="{{ __('e.g. Annual review') }}">
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold" for="assessor">{{ __('Assessor (optional)') }}</label>
                <input type="text" class="form-control form-control-sm" id="assessor" name="assessor" maxlength="255"
                       value="{{ old('assessor') }}" placeholder="{{ __('Defaults to your account') }}">
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold" for="assessment_date">{{ __('Assessment date') }}</label>
                <input type="date" class="form-control form-control-sm" id="assessment_date" name="assessment_date"
                       value="{{ old('assessment_date', now()->toDateString()) }}">
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold" for="notes">{{ __('Scope notes (optional)') }}</label>
                <textarea class="form-control form-control-sm" id="notes" name="notes" rows="2" maxlength="10000">{{ old('notes') }}</textarea>
              </div>
              <button type="submit" class="btn btn-sm btn-primary w-100">
                <i class="fas fa-play me-1"></i>{{ __('Begin assessment') }}
              </button>
            </form>
          </div>
        </div>
      </div>

      {{-- Past assessments + trend --}}
      <div class="col-12 col-lg-8">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h2 class="h6 mb-3"><i class="fas fa-clock-rotate-left me-2 text-muted"></i>{{ __('Past assessments and progress') }}</h2>

            @if(empty($runs))
              <p class="text-muted small mb-0">
                {{ __('No assessments recorded yet. Start your first self-assessment on the left to build a maturity profile you can track over time.') }}
              </p>
            @else
              {{-- Per-model trend (CSS-only mini bars, chronological) --}}
              @foreach($history as $model => $series)
                @php
                  $modelName = $runs[0]['model_name'] ?? $model;
                  foreach ($runs as $r) { if ($r['model'] === $model) { $modelName = $r['model_name']; break; } }
                @endphp
                <div class="mb-3">
                  <div class="text-uppercase text-muted small fw-semibold mb-1">{{ __($modelName) }}</div>
                  <div class="d-flex align-items-end gap-2" style="height:72px">
                    @foreach($series as $pt)
                      @php
                        $h = $maxLevel > 0 ? max(6, ($pt['overall'] / $maxLevel) * 100) : 6;
                      @endphp
                      <a href="{{ route('preservation-self-assessment.profile', ['id' => $pt['id']]) }}"
                         class="text-decoration-none d-flex flex-column align-items-center" style="min-width:38px"
                         title="{{ ($pt['date'] ?? '') }} - {{ __('overall') }} {{ $pt['overall'] }}/{{ $maxLevel }}">
                        <div class="w-100 d-flex align-items-end" style="height:54px">
                          <div class="w-100 rounded-top {{ $bandClass((float) $pt['overall']) }}" style="height: {{ $h }}%"></div>
                        </div>
                        <div class="text-muted" style="font-size:.65rem">{{ $pt['overall'] }}</div>
                      </a>
                    @endforeach
                  </div>
                </div>
              @endforeach

              {{-- Run table --}}
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr class="small text-muted">
                      <th>{{ __('Date') }}</th>
                      <th>{{ __('Model') }}</th>
                      <th>{{ __('Title') }}</th>
                      <th>{{ __('Assessor') }}</th>
                      <th class="text-center">{{ __('Overall') }}</th>
                      <th>{{ __('Status') }}</th>
                      <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($runs as $r)
                      <tr>
                        <td class="small">{{ $r['assessment_date'] ?? '-' }}</td>
                        <td class="small">{{ __($r['model_name']) }}</td>
                        <td class="small">{{ $r['title'] ?? '-' }}</td>
                        <td class="small">{{ $r['assessor'] ?? '-' }}</td>
                        <td class="text-center">
                          <span class="badge {{ $bandClass((float) $r['overall']) }}">{{ $r['overall'] }} / {{ $maxLevel }}</span>
                        </td>
                        <td><span class="badge {{ $statusBadge($r['status']) }}">{{ __(ucfirst($r['status'])) }}</span></td>
                        <td class="text-end text-nowrap">
                          <a href="{{ route('preservation-self-assessment.profile', ['id' => $r['id']]) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('Profile') }}"><i class="fas fa-chart-column"></i></a>
                          <a href="{{ route('preservation-self-assessment.edit', ['id' => $r['id']]) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('Edit') }}"><i class="fas fa-pen"></i></a>
                          <a href="{{ route('preservation-self-assessment.export', ['id' => $r['id']]) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('Export JSON') }}"><i class="fas fa-file-export"></i></a>
                          <form method="POST" action="{{ route('preservation-self-assessment.destroy', ['id' => $r['id']]) }}" class="d-inline"
                                onsubmit="return confirm('{{ __('Delete this assessment?') }}');">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                          </form>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif
          </div>
        </div>
      </div>

    </div>
  @endif

</div>
@endsection
