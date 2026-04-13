{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Sections')

@section('content')
@php
  $exhibition = $exhibition ?? (object) [];
  $exId = $exhibition->id ?? 0;
  $sections = $exhibition->sections ?? collect();
  $galleries = $galleries ?? [];
@endphp

<div class="row">
  <div class="col-md-8">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">Exhibitions</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.show', ['id' => $exId]) }}">{{ $exhibition->title ?? '' }}</a></li>
        <li class="breadcrumb-item active">Sections</li>
      </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1>Exhibition Sections</h1>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSectionModal">
        <i class="fas fa-plus"></i> Add Section
      </button>
    </div>

    @if(empty($sections) || (is_object($sections) && method_exists($sections, 'isEmpty') && $sections->isEmpty()))
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-th-large fa-3x text-muted mb-3"></i>
          <h5>No sections created yet</h5>
          <p class="text-muted">Organize your exhibition by creating sections or galleries.</p>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
            <i class="fas fa-plus"></i> Create First Section
          </button>
        </div>
      </div>
    @else
      <div class="row" id="sectionList">
        @foreach($sections as $section)
          @php $sec = (object) $section; @endphp
          <div class="col-md-6 mb-3" data-id="{{ $sec->id ?? '' }}">
            <div class="card h-100">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                  <i class="fas fa-grip-vertical me-2 text-muted drag-handle" style="cursor: move;"></i>
                  {{ $sec->name ?? ($sec->title ?? '') }}
                </h6>
                <span class="badge bg-primary">{{ $sec->sort_order ?? ($sec->display_order ?? 0) }}</span>
              </div>
              <div class="card-body">
                @if(!empty($sec->description))
                  <p class="small text-muted mb-2">{{ $sec->description }}</p>
                @endif
                @if(!empty($sec->gallery_name))
                  <p class="small mb-1"><i class="fas fa-map-marker me-1"></i> <strong>Gallery:</strong> {{ $sec->gallery_name }}</p>
                @endif
                @if(!empty($sec->theme))
                  <p class="small mb-1"><i class="fas fa-tag me-1"></i> <strong>Theme:</strong> {{ $sec->theme }}</p>
                @endif
                <p class="small mb-0"><i class="fas fa-archive me-1"></i> <strong>Objects:</strong> {{ $sec->object_count ?? 0 }}</p>
              </div>
              <div class="card-footer bg-transparent">
                <div class="btn-group btn-group-sm w-100">
                  <a href="{{ route('exhibition.objects', ['id' => $exId]) }}?section={{ $sec->id ?? '' }}" class="btn btn-outline-primary">
                    <i class="fas fa-archive"></i> Objects
                  </a>
                  <button type="button" class="btn btn-outline-secondary"><i class="fas fa-edit"></i> Edit</button>
                  <button type="button" class="btn btn-outline-danger"><i class="fas fa-trash"></i></button>
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
        <h5 class="mb-0">Exhibition Info</h5>
      </div>
      <div class="card-body">
        <h6>{{ $exhibition->title ?? '' }}</h6>
        <p class="small text-muted mb-2">
          <span class="badge bg-secondary">{{ $exhibition->status ?? '' }}</span>
        </p>
        <p class="small mb-0"><strong>{{ is_countable($sections) ? count($sections) : 0 }}</strong> sections</p>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Tips</h5>
      </div>
      <div class="card-body">
        <p class="small text-muted mb-2"><strong>Sections</strong> help organize your exhibition into logical groupings or physical spaces.</p>
        <ul class="small text-muted mb-0">
          <li>Drag sections to reorder them</li>
          <li>Assign objects to sections for better organization</li>
          <li>Use themes to create narrative flow</li>
          <li>Link to physical galleries if applicable</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addSectionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Section</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="{{ route('exhibition.sections', ['id' => $exId]) }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Section Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Gallery Name</label>
            <input type="text" name="gallery_name" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Theme</label>
            <input type="text" name="theme" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Display Order</label>
            <input type="number" name="display_order" class="form-control" min="0" value="{{ (is_countable($sections) ? count($sections) : 0) * 10 + 10 }}">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Section</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
