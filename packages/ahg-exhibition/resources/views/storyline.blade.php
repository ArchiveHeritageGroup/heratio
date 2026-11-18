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
    <nav aria-label="{{ __('breadcrumb') }}">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">{{ __('Exhibitions') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.show', ['id' => $exId]) }}">{{ $exhibition->title ?? '' }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.storylines', ['id' => $exId]) }}">{{ __('Storylines') }}</a></li>
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
        <i class="fas fa-plus"></i> {{ __('Add Stop') }}
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
          <h5>{{ __('No stops added yet') }}</h5>
          <p class="text-muted">{{ __('Add stops to create a narrative journey through the exhibition.') }}</p>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStopModal">
            <i class="fas fa-plus"></i> {{ __('Add First Stop') }}
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
                    {{ $st->sequence_order ?? ($index + 1) }}
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
                      <button type="button" class="btn btn-outline-secondary" title="{{ __('Edit') }}"
                              data-bs-toggle="modal" data-bs-target="#editStopModal"
                              data-action="{{ route('exhibition.storyline.stops.update', ['exhibitionId' => $exId, 'storylineId' => $slId, 'stopId' => $st->id]) }}"
                              data-title="{{ $st->title ?? '' }}"
                              data-object="{{ $st->exhibition_object_id ?? '' }}"
                              data-narrative="{{ $st->narrative_text ?? '' }}"
                              data-duration="{{ $st->audio_duration_seconds ?? '' }}"
                              data-order="{{ $st->sequence_order ?? '' }}">
                        <i class="fas fa-edit"></i>
                      </button>
                      <form method="post" action="{{ route('exhibition.storyline.stops.delete', ['exhibitionId' => $exId, 'storylineId' => $slId, 'stopId' => $st->id]) }}" class="d-inline"
                            data-confirm="{{ __('Delete stop ":title"?', ['title' => $st->title ?? '']) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                      </form>
                    </div>
                  </div>

                  @if(!empty($st->narrative_text))
                    <p class="mb-2">{!! nl2br(e(mb_substr($st->narrative_text, 0, 300))) !!}{{ mb_strlen($st->narrative_text) > 300 ? '...' : '' }}</p>
                  @endif

                  <div class="d-flex gap-3 small text-muted">
                    @if(!empty($st->audio_duration_seconds))
                      <span><i class="fas fa-clock me-1"></i> {{ floor($st->audio_duration_seconds / 60) }}:{{ str_pad($st->audio_duration_seconds % 60, 2, '0', STR_PAD_LEFT) }}</span>
                    @endif
                    @if(!empty($st->audio_url))
                      <span><i class="fas fa-headphones me-1"></i> {{ __('Audio') }}</span>
                    @endif
                    @if(!empty($st->video_url))
                      <span><i class="fas fa-video me-1"></i> {{ __('Video') }}</span>
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
        <h5 class="mb-0">{{ __('Storyline Info') }}</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="mb-2"><strong>{{ __('Stops:') }}</strong> {{ count($stops) }}</li>
          @if(!empty($storyline->duration_minutes))
            <li class="mb-2"><strong>{{ __('Est. Duration:') }}</strong> {{ __(':count min', ['count' => $storyline->duration_minutes]) }}</li>
          @endif
        </ul>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Actions') }}</h5>
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('exhibition.storylines', ['id' => $exId]) }}" class="list-group-item list-group-item-action">
          <i class="fas fa-arrow-left me-2"></i> {{ __('Back to Storylines') }}
        </a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addStopModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Add Stop') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      {{-- #1481: this posted to the GET storyline route and raised a 405, and
           its field names matched no column. Both fixed; the names below are
           exactly the exhibition_storyline_stop columns. --}}
      <form method="post" action="{{ route('exhibition.storyline.stops.store', ['exhibitionId' => $exId, 'storylineId' => $slId]) }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            {{-- A stop is a stop AT an object: exhibition_storyline_stop
                 .exhibition_object_id is NOT NULL with a foreign key, so the
                 form has to choose one. It never used to, which is the other
                 reason nothing could be saved. --}}
            <label class="form-label">{{ __('Object') }} <span class="text-danger">*</span></label>
            <select name="exhibition_object_id" class="form-select" required>
              <option value="">{{ __('Choose an object in this exhibition') }}</option>
              @foreach($objects ?? [] as $obj)
                <option value="{{ $obj->id }}">{{ $obj->object_title ?? ('#' . $obj->id) }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Stop Title') }} <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Narrative Content') }}</label>
            <textarea name="narrative_text" class="form-control" rows="5"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">{{ __('Duration (seconds)') }}</label>
              <input type="number" name="audio_duration_seconds" class="form-control" min="0">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">{{ __('Stop Order') }}</label>
              <input type="number" name="sequence_order" class="form-control" min="1" value="{{ count($stops) + 1 }}">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Add Stop') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
<div class="modal fade" id="editStopModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Edit Stop') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="" id="editStopForm">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="editStopObject">{{ __('Object') }} <span class="text-danger">*</span></label>
            <select name="exhibition_object_id" id="editStopObject" class="form-select" required>
              @foreach($objects ?? [] as $obj)
                <option value="{{ $obj->id }}">{{ $obj->object_title ?? ('#' . $obj->id) }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editStopTitle">{{ __('Stop Title') }} <span class="text-danger">*</span></label>
            <input type="text" name="title" id="editStopTitle" class="form-control" maxlength="255" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editStopNarrative">{{ __('Narrative Content') }}</label>
            <textarea name="narrative_text" id="editStopNarrative" class="form-control" rows="5"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="editStopDuration">{{ __('Duration (seconds)') }}</label>
              <input type="number" name="audio_duration_seconds" id="editStopDuration" class="form-control" min="0">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="editStopOrder">{{ __('Stop Order') }}</label>
              <input type="number" name="sequence_order" id="editStopOrder" class="form-control" min="1">
            </div>
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
  document.getElementById('editStopModal').addEventListener('show.bs.modal', function (event) {
    var d = event.relatedTarget.dataset;
    document.getElementById('editStopForm').action = d.action;
    document.getElementById('editStopObject').value = d.object || '';
    document.getElementById('editStopTitle').value = d.title || '';
    document.getElementById('editStopNarrative').value = d.narrative || '';
    document.getElementById('editStopDuration').value = d.duration || '';
    document.getElementById('editStopOrder').value = d.order || '';
  });
});
</script>
@endpush

