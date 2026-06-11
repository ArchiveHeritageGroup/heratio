{{--
  Research Decision Log - add / edit form (heratio#1224, Research OS Stage 9)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', ($isNew ?? false) ? 'Record a decision' : 'Edit decision')

@section('content')
@php
  $isNew = $isNew ?? false;
  $types = $types ?? [];
  $entry = $entry ?? null;
  $currentType = old('decision_type', $entry->decision_type ?? 'scope_change');
  $decidedAt = old('decided_at');
  if (!$decidedAt) {
      $raw = $entry->decided_at ?? null;
      $decidedAt = $raw ? \Carbon\Carbon::parse($raw)->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i');
  }
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">{{ __('Projects') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title ?? '') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.decisions.index', $project->id) }}">{{ __('Decision Log') }}</a></li>
    <li class="breadcrumb-item active">{{ $isNew ? __('New') : __('Edit') }}</li>
  </ol>
</nav>

<h1 class="h2 mb-4">
  <i class="fas fa-clipboard-list text-primary me-2"></i>{{ $isNew ? __('Record a decision') : __('Edit decision') }}
</h1>

@if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
    </ul>
  </div>
@endif

<div class="card">
  <div class="card-body">
    <form method="POST"
          action="{{ $isNew
              ? route('research.decisions.store', $project->id)
              : route('research.decisions.update', ['projectId' => $project->id, 'id' => $entry->id]) }}">
      @csrf
      @unless($isNew)@method('PUT')@endunless

      <div class="row mb-3">
        <div class="col-md-5">
          <label class="form-label">{{ __('Decision type') }} <span class="text-danger">*</span></label>
          <select name="decision_type" class="form-select" required>
            @foreach($types as $code => $meta)
              <option value="{{ $code }}" {{ $currentType === $code ? 'selected' : '' }}>{{ $meta['label'] }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-7">
          <label class="form-label">{{ __('When was it decided?') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <input type="datetime-local" name="decided_at" class="form-control" value="{{ $decidedAt }}">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Summary') }} <span class="text-danger">*</span></label>
        <input type="text" name="summary" class="form-control" maxlength="500" required
               value="{{ old('summary', $entry->summary ?? '') }}"
               placeholder="{{ __('e.g. Excluded oral-history interviews from the corpus') }}">
        <div class="form-text">{{ __('A one-line statement of what was decided.') }}</div>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Reason') }} <span class="badge bg-secondary ms-1">{{ __('Recommended') }}</span></label>
        <textarea name="reason" class="form-control" rows="4"
                  placeholder="{{ __('Why? The reasoning that an examiner would want to see. This is what makes going backwards safe and recorded.') }}">{{ old('reason', $entry->reason ?? '') }}</textarea>
      </div>

      <div class="row mb-3">
        <div class="col-md-7">
          <label class="form-label">{{ __('Related reference') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <input type="text" name="related_ref" class="form-control" maxlength="500"
                 value="{{ old('related_ref', $entry->related_ref ?? '') }}"
                 placeholder="{{ __('e.g. excluded source id + label, dataset, case') }}">
        </div>
        <div class="col-md-5">
          <label class="form-label">{{ __('Decided by') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <input type="text" name="decided_by" class="form-control" maxlength="255"
                 value="{{ old('decided_by', $entry->decided_by ?? '') }}"
                 placeholder="{{ __('Defaults to you') }}">
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ $isNew ? __('Record decision') : __('Save changes') }}</button>
      <a href="{{ route('research.decisions.index', $project->id) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </form>
  </div>
</div>
@endsection
