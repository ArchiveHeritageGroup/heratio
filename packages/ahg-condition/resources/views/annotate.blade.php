{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Annotate Condition Photo')

@section('content')
@php
  $photoTypes = [
    'general' => 'General',
    'detail'  => 'Detail',
    'damage'  => 'Damage',
    'before'  => 'Before Treatment',
    'after'   => 'After Treatment',
    'raking'  => 'Raking Light',
    'uv'      => 'UV Light',
    'ir'      => 'Infrared',
    'xray'    => 'X-Ray',
  ];
  $photo = $photo ?? (object)[];
  $conditionCheck = $conditionCheck ?? (object)[];
  $annotations = $annotations ?? [];
  $canEdit = $canEdit ?? false;
  $imageUrl = $imageUrl ?? '';
  $photoId = $photo->id ?? 0;
@endphp

<div class="condition-check-header">
  <nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
      @if(!empty($conditionCheck->slug))
        <li class="breadcrumb-item"><a href="{{ url('/'.$conditionCheck->slug) }}">{{ $conditionCheck->identifier ?? '' }}</a></li>
      @endif
      @if(!empty($photo->condition_check_id))
        <li class="breadcrumb-item"><a href="{{ url('/condition/check/'.$photo->condition_check_id.'/photos') }}">Condition Photos</a></li>
      @endif
      <li class="breadcrumb-item active">Annotate</li>
    </ol>
  </nav>

  <h1><i class="fas fa-draw-polygon me-2"></i>Annotate Condition Photo</h1>

  <div class="object-info">
    <strong>{{ $conditionCheck->identifier ?? '' }}</strong>
    @if(!empty($conditionCheck->object_title))
      - {{ $conditionCheck->object_title }}
    @endif
  </div>

  <div class="check-meta">
    <div class="meta-item">
      <span class="meta-label">Photo Type</span>
      <span class="meta-value"><span class="photo-type {{ $photo->photo_type ?? '' }}">{{ $photoTypes[$photo->photo_type ?? ''] ?? ($photo->photo_type ?? '') }}</span></span>
    </div>
    <div class="meta-item">
      <span class="meta-label">Caption</span>
      <span class="meta-value">{{ $photo->caption ?? 'No caption' }}</span>
    </div>
    @if(!empty($photo->created_at))
    <div class="meta-item">
      <span class="meta-label">Uploaded</span>
      <span class="meta-value">{{ \Carbon\Carbon::parse($photo->created_at)->format('j M Y H:i') }}</span>
    </div>
    @endif
    <div class="meta-item">
      <span class="meta-label">Annotations</span>
      <span class="meta-value">{{ count($annotations) }}</span>
    </div>
  </div>
</div>

<div class="row mb-3">
  <div class="col">
    @if(!empty($photo->condition_check_id))
      <a href="{{ url('/condition/check/'.$photo->condition_check_id.'/photos') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Photos
      </a>
    @endif
  </div>
</div>

<div class="row">
  <div class="col-lg-9">
    <div id="annotator-container" style="min-height: 500px;">
      @if($imageUrl)
        <img src="{{ $imageUrl }}" alt="{{ $photo->caption ?? '' }}" class="img-fluid">
      @endif
    </div>
  </div>

  <div class="col-lg-3">
    <div class="annotation-list-panel card">
      <div class="card-header panel-header">
        <i class="fas fa-list me-1"></i> Annotations
      </div>
      <div class="card-body" id="annotation-list">
        @if(empty($annotations))
          <div class="p-3 text-muted text-center">No annotations yet</div>
        @else
          @foreach($annotations as $ann)
            <div class="annotation-list-item" data-id="{{ $ann['id'] ?? '' }}">
              <span class="ann-color" style="background: {{ $ann['fabricData']['stroke'] ?? $ann['stroke'] ?? '#FF0000' }};"></span>
              <div class="ann-info">
                <div class="ann-label">
                  {{ $ann['label'] ?? 'Annotation' }}
                  @if(!empty($ann['ai_generated']))
                    <span class="ann-ai">AI</span>
                  @endif
                </div>
                @if(!empty($ann['notes']))
                  <div class="ann-notes">{{ $ann['notes'] }}</div>
                @endif
              </div>
            </div>
          @endforeach
        @endif
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header">
        <i class="fas fa-info-circle me-1"></i> Photo Details
      </div>
      <div class="card-body">
        @if(!empty($photo->original_name))
          <p><strong>Filename:</strong><br>{{ $photo->original_name }}</p>
        @endif
        @if(!empty($photo->file_size))
          <p><strong>Size:</strong><br>{{ number_format($photo->file_size / 1024, 1) }} KB</p>
        @endif
        @if(!empty($photo->mime_type))
          <p><strong>Type:</strong><br>{{ $photo->mime_type }}</p>
        @endif
      </div>
    </div>
  </div>
</div>

<script>
window.AHG_ANNOTATE = {
  photoId: {{ (int) $photoId }},
  imageUrl: @json($imageUrl),
  readonly: {{ $canEdit ? 'false' : 'true' }},
  annotations: @json($annotations),
  saveUrl: @json(route('condition.annotation.save'))
};
</script>
@endsection
