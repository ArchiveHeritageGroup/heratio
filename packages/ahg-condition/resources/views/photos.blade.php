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

@section('title', 'Condition Photos')

@section('content')
@php
  $photoTypes = [
    'overall' => 'Overall View',
    'detail'  => 'Detail',
    'damage'  => 'Damage',
    'before'  => 'Before Treatment',
    'after'   => 'After Treatment',
    'general' => 'General',
    'other'   => 'Other',
  ];
  $photos = $photos ?? collect();
  $conditionCheck = $conditionCheck ?? (object)[];
  $canEdit = $canEdit ?? false;
  $stats = $stats ?? [];
  $checkId = $conditionCheck->id ?? 0;
  $objectSlug = $conditionCheck->slug ?? '';
  $objectTitle = $conditionCheck->object_title ?? ($conditionCheck->title ?? 'Object');
@endphp

<div class="condition-photos-page">
  <div class="row mb-4">
    <div class="col-md-12">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          @if($objectSlug)
            <li class="breadcrumb-item"><a href="{{ url('/'.$objectSlug) }}">{{ $objectTitle }}</a></li>
          @endif
          <li class="breadcrumb-item">Condition</li>
          <li class="breadcrumb-item active">Photos</li>
        </ol>
      </nav>

      <h1 class="h3 mb-3">
        <i class="fas fa-images me-2"></i>
        Condition Photos
      </h1>

      <div class="mb-3">
        @if($objectSlug)
          <a href="{{ url('/'.$objectSlug) }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
          </a>
        @endif
      </div>
    </div>
  </div>

  @if($canEdit)
  <div class="card mb-4">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Upload Photos</h5>
    </div>
    <div class="card-body">
      <form id="upload-form" action="{{ route('condition.photo.upload') }}" method="post" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="condition_check_id" value="{{ $checkId }}">
        <input type="hidden" name="id" value="{{ $checkId }}">

        <div class="row">
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Photo Type</label>
              <select name="photo_type" id="photo_type" class="form-select">
                @foreach($photoTypes as $value => $label)
                  <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Caption</label>
              <input type="text" name="caption" id="caption" class="form-control"
                     placeholder="Brief description of the photo">
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Select Photo</label>
              <input type="file" name="photo" id="photo-file" class="form-control" accept="image/*" required>
            </div>
          </div>
        </div>

        <div class="dropzone-area text-center p-4 border border-dashed rounded mb-3" id="dropzone">
          <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
          <p class="mb-0">Drag &amp; drop a photo here, or click to select</p>
        </div>

        <button type="submit" class="btn btn-success" id="upload-btn">
          <i class="fas fa-upload me-1"></i> Upload Photo
        </button>
      </form>
    </div>
  </div>
  @endif

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="fas fa-th me-2"></i>Photos</h5>
      <span class="badge bg-secondary">{{ count($photos) }}</span>
    </div>
    <div class="card-body">
      @if(count($photos) === 0)
        <div class="text-center py-5">
          <i class="fas fa-camera fa-4x text-muted mb-3"></i>
          <p class="text-muted">No photos uploaded yet.</p>
        </div>
      @else
        <div class="row">
          @foreach($photos as $photo)
            <div class="col-md-3 col-sm-6 mb-4">
              <div class="card h-100 photo-card">
                <div class="photo-image position-relative">
                  <img
                    src="/uploads/condition_photos/{{ $photo->filename ?? '' }}"
                    class="card-img-top condition-photo-thumb"
                    alt="{{ $photo->caption ?? '' }}"
                    data-action="annotate"
                    data-photo-id="{{ $photo->id }}"
                    data-image-src="/uploads/condition_photos/{{ $photo->filename ?? '' }}"
                  >
                  <span class="badge bg-info position-absolute top-0 end-0 m-2">
                    {{ $photoTypes[$photo->photo_type ?? 'other'] ?? 'Other' }}
                  </span>
                </div>

                <div class="card-body p-2">
                  @if(!empty($photo->caption))
                    <p class="card-text small mb-1">{{ $photo->caption }}</p>
                  @endif
                  @if(!empty($photo->created_at))
                    <small class="text-muted">
                      {{ \Carbon\Carbon::parse($photo->created_at)->format('d M Y') }}
                    </small>
                  @endif
                </div>

                @if($canEdit)
                <div class="card-footer p-2">
                  <div class="btn-group btn-group-sm w-100">
                    <a class="btn btn-outline-info"
                       href="{{ route('condition.annotate', $photo->id) }}"
                       title="Annotate">
                      <i class="fas fa-draw-polygon"></i>
                    </a>
                    <form action="{{ route('condition.photo.delete', $photo->id) }}" method="post" class="d-inline"
                          onsubmit="return confirm('Delete this photo?');">
                      @csrf
                      <button type="submit" class="btn btn-outline-danger" title="Delete">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </div>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
