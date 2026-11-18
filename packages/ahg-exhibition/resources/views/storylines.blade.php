{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Storylines')

@section('content')
@php
  $exhibition = $exhibition ?? (object) [];
  $exId = $exhibition->id ?? 0;
  $storylines = $exhibition->storylines ?? collect();
@endphp

<div class="row">
  <div class="col-md-8">
    <nav aria-label="{{ __('breadcrumb') }}">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">{{ __('Exhibitions') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.show', ['id' => $exId]) }}">{{ $exhibition->title ?? '' }}</a></li>
        <li class="breadcrumb-item active">{{ __('Storylines') }}</li>
      </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1>{{ __('Storylines & Narratives') }}</h1>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStorylineModal">
        <i class="fas fa-plus"></i> {{ __('Create Storyline') }}
      </button>
    </div>

    @if(empty($storylines) || (is_object($storylines) && method_exists($storylines, 'isEmpty') && $storylines->isEmpty()))
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-book fa-3x text-muted mb-3"></i>
          <h5>{{ __('No storylines created yet') }}</h5>
          <p class="text-muted">{{ __('Create narrative journeys through your exhibition with storylines.') }}</p>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStorylineModal">
            <i class="fas fa-plus"></i> {{ __('Create First Storyline') }}
          </button>
        </div>
      </div>
    @else
      @foreach($storylines as $storyline)
        @php $sl = (object) $storyline; @endphp
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">{{ $sl->title ?? '' }}
                @if(!empty($sl->is_primary))<span class="badge bg-success ms-1">{{ __('Primary') }}</span>@endif
              </h5>
              @if(!empty($sl->narrative_type))
                <small class="text-muted">{{ $sl->narrative_type_label ?? $sl->narrative_type }}</small>
              @endif
            </div>
            <div class="btn-group btn-group-sm">
              <a href="{{ route('exhibition.storyline', ['exhibitionId' => $exId, 'storylineId' => $sl->id ?? 0]) }}" class="btn btn-outline-primary">
                <i class="fas fa-eye"></i> {{ __('View') }}
              </a>
              <button type="button" class="btn btn-outline-secondary" title="{{ __('Edit') }}"
                      data-bs-toggle="modal" data-bs-target="#editStorylineModal"
                      data-action="{{ route('exhibition.storylines.update', ['id' => $exId, 'storylineId' => $sl->id]) }}"
                      data-title="{{ $sl->title ?? '' }}"
                      data-narrative-type="{{ $sl->narrative_type ?? '' }}"
                      data-description="{{ $sl->description ?? '' }}"
                      data-introduction="{{ $sl->introduction ?? '' }}"
                      data-audience="{{ $sl->target_audience ?? '' }}"
                      data-duration="{{ $sl->estimated_duration_minutes ?? '' }}"
                      data-primary="{{ !empty($sl->is_primary) ? '1' : '0' }}">
                <i class="fas fa-edit"></i>
              </button>
              <form method="post" action="{{ route('exhibition.storylines.delete', ['id' => $exId, 'storylineId' => $sl->id]) }}" class="d-inline"
                    data-confirm="{{ __('Delete storyline ":title" and all its stops?', ['title' => $sl->title ?? '']) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </div>
          <div class="card-body">
            @if(!empty($sl->description))
              <p class="mb-3">{{ $sl->description }}</p>
            @endif
            <div class="row">
              <div class="col-md-4">
                <p class="small mb-1"><i class="fas fa-map-marker me-1"></i> {{ __(':count stops', ['count' => $sl->stop_count ?? 0]) }}</p>
              </div>
              @if(!empty($sl->target_audience))
                <div class="col-md-4">
                  <p class="small mb-1"><i class="fas fa-users me-1"></i> {{ __('Audience:') }} <strong>{{ $audiences[$sl->target_audience] ?? $sl->target_audience }}</strong></p>
                </div>
              @endif
              @if(!empty($sl->estimated_duration_minutes))
                <div class="col-md-4">
                  <p class="small mb-1"><i class="fas fa-clock me-1"></i> {{ __('Duration:') }} <strong>{{ __(':count min', ['count' => $sl->estimated_duration_minutes]) }}</strong></p>
                </div>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    @endif
  </div>

  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Exhibition Info') }}</h5>
      </div>
      <div class="card-body">
        <h6>{{ $exhibition->title ?? '' }}</h6>
        <p class="small text-muted mb-2">
          <span class="badge bg-secondary">{{ $exhibition->status ?? '' }}</span>
        </p>
        <p class="small mb-0">
          {{ __(':count storylines', ['count' => is_countable($storylines) ? count($storylines) : 0]) }}
        </p>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Storyline Types') }}</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled small mb-0">
          @foreach(($narrativeTypes ?? []) as $label)
            <li class="mb-1">{{ $label }}</li>
          @endforeach
        </ul>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Tips') }}</h5>
      </div>
      <div class="card-body">
        <p class="small text-muted mb-2">{{ __('Storylines create narrative paths through your exhibition.') }}</p>
        <ul class="small text-muted mb-0">
          <li>{{ __('Add stops to guide visitors') }}</li>
          <li>{{ __('Link stops to specific objects') }}</li>
          <li>{{ __('Include interpretive content') }}</li>
          <li>{{ __('Create multiple tours for different audiences') }}</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addStorylineModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Create Storyline') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="{{ route('exhibition.storylines.store', ['id' => $exId]) }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="addTitle">{{ __('Title') }} <span class="text-danger">*</span></label>
            <input type="text" name="title" id="addTitle" class="form-control" maxlength="255" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="addNarrativeType">{{ __('Type') }}</label>
            <select name="narrative_type" id="addNarrativeType" class="form-select">
              @foreach(($narrativeTypes ?? []) as $code => $label)
                <option value="{{ $code }}" @selected($code === 'thematic')>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="addDescription">{{ __('Description') }}</label>
            <textarea name="description" id="addDescription" class="form-control" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label" for="addIntroduction">{{ __('Introduction') }}</label>
            <textarea name="introduction" id="addIntroduction" class="form-control" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label" for="addAudience">{{ __('Target Audience') }}</label>
            <select name="target_audience" id="addAudience" class="form-select">
              @foreach(($audiences ?? []) as $code => $label)
                <option value="{{ $code }}" @selected($code === 'all')>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="addDuration">{{ __('Estimated Duration (minutes)') }}</label>
            <input type="number" name="estimated_duration_minutes" id="addDuration" class="form-control" min="0" step="5">
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" name="is_primary" class="form-check-input" value="1" id="addPrimary">
            <label class="form-check-label" for="addPrimary">{{ __('Primary storyline') }}</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Create Storyline') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editStorylineModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Edit Storyline') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="" id="editStorylineForm">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="editTitle">{{ __('Title') }} <span class="text-danger">*</span></label>
            <input type="text" name="title" id="editTitle" class="form-control" maxlength="255" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editNarrativeType">{{ __('Type') }}</label>
            <select name="narrative_type" id="editNarrativeType" class="form-select">
              @foreach(($narrativeTypes ?? []) as $code => $label)
                <option value="{{ $code }}" @selected($code === 'thematic')>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editDescription">{{ __('Description') }}</label>
            <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editIntroduction">{{ __('Introduction') }}</label>
            <textarea name="introduction" id="editIntroduction" class="form-control" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editAudience">{{ __('Target Audience') }}</label>
            <select name="target_audience" id="editAudience" class="form-select">
              @foreach(($audiences ?? []) as $code => $label)
                <option value="{{ $code }}" @selected($code === 'all')>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editDuration">{{ __('Estimated Duration (minutes)') }}</label>
            <input type="number" name="estimated_duration_minutes" id="editDuration" class="form-control" min="0" step="5">
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" name="is_primary" class="form-check-input" value="1" id="editPrimary">
            <label class="form-check-label" for="editPrimary">{{ __('Primary storyline') }}</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('js')
<script nonce="{{ csp_nonce() }}">
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) { e.preventDefault(); } });
  });

  document.getElementById('editStorylineModal').addEventListener('show.bs.modal', function (event) {
    var d = event.relatedTarget.dataset;
    document.getElementById('editStorylineForm').action = d.action;
    document.getElementById('editTitle').value = d.title || '';
    document.getElementById('editNarrativeType').value = d.narrativeType || 'thematic';
    document.getElementById('editDescription').value = d.description || '';
    document.getElementById('editIntroduction').value = d.introduction || '';
    document.getElementById('editAudience').value = d.audience || 'all';
    document.getElementById('editDuration').value = d.duration || '';
    document.getElementById('editPrimary').checked = d.primary === '1';
  });
});
</script>
@endpush
