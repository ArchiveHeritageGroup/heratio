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

@section('title', 'Storyline')

@section('content')
@php
  $exhibition = $exhibition ?? (object) [];
  $exId = $exhibition->id ?? 0;
  $storyline = (object) ($storyline ?? []);
  $slId = $storyline->id ?? 0;
  $stops = $storyline->stops ?? [];
  if (is_object($stops) && method_exists($stops, 'toArray')) { $stops = $stops->toArray(); }
  if (!is_array($stops)) { $stops = []; }
  $exhibitionObjects = $exhibitionObjects ?? ($exhibition->objects ?? []);
@endphp

<div class="row">
  <div class="col-md-8">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">Exhibitions</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.show', ['id' => $exId]) }}">{{ $exhibition->title ?? '' }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.storylines', ['id' => $exId]) }}">Storylines</a></li>
        <li class="breadcrumb-item active">{{ $storyline->title ?? '' }}</li>
      </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1>{{ $storyline->title ?? '' }}</h1>
        @if(!empty($storyline->type))
          <span class="badge bg-secondary text-capitalize">{{ str_replace('_', ' ', $storyline->type) }}</span>
        @endif
        @if(!empty($storyline->target_audience))
          <span class="badge bg-info text-capitalize">{{ str_replace('_', ' ', $storyline->target_audience) }}</span>
        @endif
      </div>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStopModal">
        <i class="fas fa-plus"></i> Add Stop
      </button>
    </div>

    @if(!empty($storyline->description))
      <div class="card mb-4">
        <div class="card-body">
          <p class="mb-0">{!! nl2br(e($storyline->description)) !!}</p>
        </div>
      </div>
    @endif

    @if(empty($stops))
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-map-signs fa-3x text-muted mb-3"></i>
          <h5>No stops added yet</h5>
          <p class="text-muted">Add stops to create a narrative journey through the exhibition.</p>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStopModal">
            <i class="fas fa-plus"></i> Add First Stop
          </button>
        </div>
      </div>
    @else
      <div class="storyline-timeline mb-4">
        @foreach($stops as $index => $stop)
          @php $st = (object) $stop; @endphp
          <div class="card mb-3 stop-card" data-id="{{ $st->id ?? '' }}">
            <div class="card-body">
              <div class="d-flex">
                <div class="stop-number me-3">
                  <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: bold;">
                    {{ $st->stop_order ?? ($index + 1) }}
                  </div>
                </div>
                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <h5 class="mb-1">{{ $st->title ?? '' }}</h5>
                      @if(!empty($st->object_title))
                        <p class="small text-muted mb-2">
                          <i class="fas fa-archive me-1"></i>
                          @if(!empty($st->object_slug))
                            <a href="{{ url('/'.$st->object_slug) }}">{{ $st->object_title }}</a>
                          @else
                            {{ $st->object_title }}
                          @endif
                        </p>
                      @endif
                    </div>
                    <div class="btn-group btn-group-sm">
                      <button type="button" class="btn btn-outline-secondary"><i class="fas fa-edit"></i></button>
                      <button type="button" class="btn btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </div>
                  </div>

                  @if(!empty($st->narrative_content))
                    <p class="mb-2">{!! nl2br(e(mb_substr($st->narrative_content, 0, 300))) !!}{{ strlen($st->narrative_content) > 300 ? '...' : '' }}</p>
                  @endif

                  <div class="d-flex gap-3 small text-muted">
                    @if(!empty($st->duration_seconds))
                      <span><i class="fas fa-clock me-1"></i> {{ floor($st->duration_seconds / 60) }}:{{ str_pad($st->duration_seconds % 60, 2, '0', STR_PAD_LEFT) }}</span>
                    @endif
                    @if(!empty($st->audio_url))
                      <span><i class="fas fa-headphones me-1"></i> Audio</span>
                    @endif
                    @if(!empty($st->video_url))
                      <span><i class="fas fa-video me-1"></i> Video</span>
                    @endif
                  </div>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">Storyline Info</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="mb-2"><strong>Stops:</strong> {{ count($stops) }}</li>
          @if(!empty($storyline->duration_minutes))
            <li class="mb-2"><strong>Est. Duration:</strong> {{ $storyline->duration_minutes }} min</li>
          @endif
        </ul>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Actions</h5>
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('exhibition.storylines', ['id' => $exId]) }}" class="list-group-item list-group-item-action">
          <i class="fas fa-arrow-left me-2"></i> Back to Storylines
        </a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addStopModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Stop</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="{{ route('exhibition.storyline', ['exhibitionId' => $exId, 'storylineId' => $slId]) }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Stop Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Narrative Content</label>
            <textarea name="narrative_content" class="form-control" rows="5"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Duration (seconds)</label>
              <input type="number" name="duration_seconds" class="form-control" min="0">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Stop Order</label>
              <input type="number" name="stop_order" class="form-control" min="1" value="{{ count($stops) + 1 }}">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Stop</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
