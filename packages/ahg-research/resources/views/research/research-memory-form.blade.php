{{--
  Research Memory - add / edit form (heratio#1233, Research OS Stage 16)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', ($isNew ?? false) ? 'Add memory item' : 'Edit memory item')

@section('content')
@php
  $isNew      = $isNew ?? false;
  $kinds      = $kinds ?? [];
  $statuses   = $statuses ?? [];
  $item       = $item ?? null;
  $presetKind = $presetKind ?? '';
  $currentKind   = old('kind', $item->kind ?? ($presetKind ?: 'unresolved_question'));
  $currentStatus = old('status', $item->status ?? 'open');
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">{{ __('Projects') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title ?? '') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.memory.index', $project->id) }}">{{ __('Memory') }}</a></li>
    <li class="breadcrumb-item active">{{ $isNew ? __('New') : __('Edit') }}</li>
  </ol>
</nav>

<h1 class="h2 mb-4">
  <i class="fas fa-brain text-primary me-2"></i>{{ $isNew ? __('Add memory item') : __('Edit memory item') }}
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
              ? route('research.memory.store', $project->id)
              : route('research.memory.update', ['projectId' => $project->id, 'id' => $item->id]) }}">
      @csrf
      @unless($isNew)@method('PUT')@endunless

      <div class="row mb-3">
        <div class="col-md-7">
          <label class="form-label">{{ __('Kind') }} <span class="text-danger">*</span></label>
          <select name="kind" class="form-select" required>
            @foreach($kinds as $code => $meta)
              <option value="{{ $code }}" {{ $currentKind === $code ? 'selected' : '' }}>{{ $meta['label'] }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label">{{ __('Status') }}</label>
          <select name="status" class="form-select">
            @foreach($statuses as $code => $meta)
              <option value="{{ $code }}" {{ $currentStatus === $code ? 'selected' : '' }}>{{ $meta['label'] }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" maxlength="500" required
               value="{{ old('title', $item->title ?? '') }}"
               placeholder="{{ __('e.g. Whether the 1923 boundary dispute affected later land claims') }}">
        <div class="form-text">{{ __('A one-line statement of the question, idea, source or lead.') }}</div>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Notes') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
        <textarea name="body" class="form-control" rows="5"
                  placeholder="{{ __('Context your future self - or the next project - will want: why it matters, where you got to, what to do next.') }}">{{ old('body', $item->body ?? '') }}</textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Source reference') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
        <input type="text" name="source_ref" class="form-control" maxlength="500"
               value="{{ old('source_ref', $item->source_ref ?? '') }}"
               placeholder="{{ __('e.g. dataset name, archive box, citation, decision-log entry') }}">
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ $isNew ? __('Save memory item') : __('Save changes') }}</button>
      <a href="{{ route('research.memory.index', $project->id) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </form>
  </div>
</div>
@endsection
