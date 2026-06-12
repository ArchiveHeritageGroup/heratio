{{--
  Preservation maturity SELF-ASSESSMENT - rating form (admin). heratio#1244.

  Rate each section of the chosen model (NDSA Levels / DPC RAM) on the shared 0..4
  maturity scale, with each level's descriptor shown so the rater knows what each
  level means, plus an optional evidence note per section. The level options are
  built from the Dropdown Manager group maturity_level (passed in as $levelLabels);
  the section catalogue + per-level descriptors come from the service. Bootstrap 5 +
  central theme. Jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Rate preservation maturity'))

@section('content')
@php
  $run         = $run ?? [];
  $sections    = $run['sections'] ?? [];
  $levelLabels = $levelLabels ?? [];
  $maxLevel    = (int) ($maxLevel ?? 4);
  $minLevel    = 0;
@endphp

<div class="container-fluid py-3">

  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
    <a href="{{ route('preservation-self-assessment.index') }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
    </a>
    <h1 class="h4 mb-0 ms-2"><i class="fas fa-clipboard-check me-2 text-primary"></i>{{ __('Rate preservation maturity') }}</h1>
    <span class="ms-auto"></span>
    <span class="badge bg-light text-dark border">{{ __($run['model_name'] ?? '') }}</span>
  </div>

  @if(! empty($run['model_note']))
    <p class="text-muted small mb-3" style="max-width:920px">{{ __($run['model_note']) }}</p>
  @endif

  @foreach(['success' => 'alert-success', 'error' => 'alert-danger'] as $key => $cls)
    @if(session($key))
      <div class="alert {{ $cls }} py-2"><i class="fas fa-circle-info me-1"></i>{{ session($key) }}</div>
    @endif
  @endforeach

  @if($errors->any())
    <div class="alert alert-danger py-2">
      <i class="fas fa-triangle-exclamation me-1"></i>{{ __('Please correct the highlighted fields.') }}
    </div>
  @endif

  <form method="POST" action="{{ route('preservation-self-assessment.update', ['id' => $run['id']]) }}">
    @csrf

    {{-- Run metadata --}}
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold" for="title">{{ __('Title') }}</label>
            <input type="text" class="form-control form-control-sm" id="title" name="title" maxlength="255"
                   value="{{ old('title', $run['title']) }}">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold" for="assessor">{{ __('Assessor') }}</label>
            <input type="text" class="form-control form-control-sm" id="assessor" name="assessor" maxlength="255"
                   value="{{ old('assessor', $run['assessor']) }}">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold" for="assessment_date">{{ __('Assessment date') }}</label>
            <input type="date" class="form-control form-control-sm" id="assessment_date" name="assessment_date"
                   value="{{ old('assessment_date', $run['assessment_date']) }}">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold" for="notes">{{ __('Scope notes') }}</label>
            <textarea class="form-control form-control-sm" id="notes" name="notes" rows="2" maxlength="10000">{{ old('notes', $run['notes']) }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Sections --}}
    @if(empty($sections))
      <div class="alert alert-warning py-2">{{ __('This model has no sections to rate.') }}</div>
    @else
      @foreach($sections as $i => $section)
        @php
          $sKey  = $section['key'];
          $sName = $section['name'];
          $sDesc = $section['description'] ?? '';
          $sLevel = (int) ($section['level'] ?? 0);
          $descriptors = $section['level_descriptors'] ?? [];
        @endphp
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-body">
            <div class="d-flex align-items-baseline gap-2 mb-2">
              <span class="badge bg-secondary">{{ $i + 1 }}</span>
              <h2 class="h6 mb-0">{{ __($sName) }}</h2>
            </div>
            @if($sDesc !== '')
              <p class="text-muted small mb-3">{{ __($sDesc) }}</p>
            @endif

            <div class="row g-2 mb-3">
              @for($lvl = $minLevel; $lvl <= $maxLevel; $lvl++)
                @php $label = $levelLabels[$lvl] ?? (string) $lvl; @endphp
                <div class="col-12 col-md">
                  <div class="form-check border rounded p-2 h-100">
                    <input class="form-check-input" type="radio"
                           name="ratings[{{ $sKey }}][level]"
                           id="rt-{{ $sKey }}-{{ $lvl }}"
                           value="{{ $lvl }}" {{ $sLevel === $lvl ? 'checked' : '' }}>
                    <label class="form-check-label d-block" for="rt-{{ $sKey }}-{{ $lvl }}">
                      <span class="fw-semibold d-block">{{ $lvl }} - {{ __($label) }}</span>
                      @if(! empty($descriptors[$lvl]))
                        <span class="text-muted small">{{ __($descriptors[$lvl]) }}</span>
                      @endif
                    </label>
                  </div>
                </div>
              @endfor
            </div>

            <label class="form-label small fw-semibold" for="ev-{{ $sKey }}">{{ __('Evidence / notes (optional)') }}</label>
            <textarea class="form-control form-control-sm" id="ev-{{ $sKey }}"
                      name="ratings[{{ $sKey }}][evidence]" rows="2" maxlength="10000">{{ old('ratings.'.$sKey.'.evidence', $section['evidence'] ?? '') }}</textarea>
          </div>
        </div>
      @endforeach
    @endif

    <div class="d-flex flex-wrap gap-2 mb-4">
      <button type="submit" name="status" value="draft" class="btn btn-outline-primary">
        <i class="fas fa-floppy-disk me-1"></i>{{ __('Save draft') }}
      </button>
      <button type="submit" name="status" value="complete" class="btn btn-primary">
        <i class="fas fa-circle-check me-1"></i>{{ __('Save and mark complete') }}
      </button>
      <a href="{{ route('preservation-self-assessment.profile', ['id' => $run['id']]) }}" class="btn btn-outline-secondary ms-auto">
        <i class="fas fa-chart-column me-1"></i>{{ __('View profile') }}
      </a>
    </div>

  </form>

</div>
@endsection
