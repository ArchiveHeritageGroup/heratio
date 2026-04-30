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
        <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">Exhibitions</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.show', ['id' => $exId]) }}">{{ $exhibition->title ?? '' }}</a></li>
        <li class="breadcrumb-item active">Storylines</li>
      </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1>{{ __('Storylines &amp; Narratives') }}</h1>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStorylineModal">
        <i class="fas fa-plus"></i> {{ __('Create Storyline') }}
      </button>
    </div>

    @if(empty($storylines) || (is_object($storylines) && method_exists($storylines, 'isEmpty') && $storylines->isEmpty()))
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-book fa-3x text-muted mb-3"></i>
          <h5>{{ __('No storylines created yet') }}</h5>
          <p class="text-muted">Create narrative journeys through your exhibition with storylines.</p>
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
              <h5 class="mb-0">{{ $sl->title ?? '' }}</h5>
              @if(!empty($sl->type))
                <small class="text-muted text-capitalize">{{ str_replace('_', ' ', $sl->type) }}</small>
              @endif
            </div>
            <div class="btn-group btn-group-sm">
              <a href="{{ route('exhibition.storyline', ['exhibitionId' => $exId, 'storylineId' => $sl->id ?? 0]) }}" class="btn btn-outline-primary">
                <i class="fas fa-eye"></i> {{ __('View') }}
              </a>
              <button type="button" class="btn btn-outline-secondary"><i class="fas fa-edit"></i></button>
              <button type="button" class="btn btn-outline-danger"><i class="fas fa-trash"></i></button>
            </div>
          </div>
          <div class="card-body">
            @if(!empty($sl->description))
              <p class="mb-3">{{ $sl->description }}</p>
            @endif
            <div class="row">
              <div class="col-md-4">
                <p class="small mb-1"><i class="fas fa-map-marker me-1"></i> <strong>{{ $sl->stop_count ?? 0 }}</strong> stops</p>
              </div>
              @if(!empty($sl->target_audience))
                <div class="col-md-4">
                  <p class="small mb-1"><i class="fas fa-users me-1"></i> Audience: <strong class="text-capitalize">{{ str_replace('_', ' ', $sl->target_audience) }}</strong></p>
                </div>
              @endif
              @if(!empty($sl->duration_minutes))
                <div class="col-md-4">
                  <p class="small mb-1"><i class="fas fa-clock me-1"></i> Duration: <strong>{{ $sl->duration_minutes }} min</strong></p>
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
          <strong>{{ is_countable($storylines) ? count($storylines) : 0 }}</strong> storylines
        </p>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Storyline Types') }}</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled small mb-0">
          <li class="mb-2"><strong>{{ __('General') }}</strong> - Default visitor tour</li>
          <li class="mb-2"><strong>{{ __('Guided Tour') }}</strong> - For docent-led visits</li>
          <li class="mb-2"><strong>{{ __('Self-Guided') }}</strong> - Independent visitor path</li>
          <li class="mb-2"><strong>{{ __('Educational') }}</strong> - School groups and learning</li>
          <li class="mb-2"><strong>{{ __('Accessible') }}</strong> - Accessibility-focused route</li>
          <li class="mb-2"><strong>{{ __('Highlights') }}</strong> - Quick overview tour</li>
          <li><strong>{{ __('Thematic') }}</strong> - Topic-specific journey</li>
        </ul>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Tips') }}</h5>
      </div>
      <div class="card-body">
        <p class="small text-muted mb-2"><strong>{{ __('Storylines') }}</strong> create narrative paths through your exhibition.</p>
        <ul class="small text-muted mb-0">
          <li>Add stops to guide visitors</li>
          <li>Link stops to specific objects</li>
          <li>Include interpretive content</li>
          <li>Create multiple tours for different audiences</li>
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
      <form method="post" action="{{ route('exhibition.storylines', ['id' => $exId]) }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Type') }}</label>
            <select name="type" class="form-select">
              <option value="general">{{ __('General') }}</option>
              <option value="guided_tour">{{ __('Guided Tour') }}</option>
              <option value="self_guided">{{ __('Self-Guided') }}</option>
              <option value="educational">{{ __('Educational') }}</option>
              <option value="accessible">{{ __('Accessible') }}</option>
              <option value="highlights">{{ __('Highlights') }}</option>
              <option value="thematic">{{ __('Thematic') }}</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="description" class="form-control" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Target Audience') }}</label>
            <select name="target_audience" class="form-select">
              <option value="">-- All visitors --</option>
              <option value="general">{{ __('General Public') }}</option>
              <option value="families">{{ __('Families with Children') }}</option>
              <option value="schools">{{ __('School Groups') }}</option>
              <option value="adults">{{ __('Adults') }}</option>
              <option value="experts">{{ __('Experts/Specialists') }}</option>
              <option value="accessible">{{ __('Accessibility Needs') }}</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Estimated Duration (minutes)') }}</label>
            <input type="number" name="duration_minutes" class="form-control" min="5" step="5">
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
@endsection
