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
    <nav aria-label="{{ __('breadcrumb') }}">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">{{ __('Exhibitions') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.show', ['id' => $exId]) }}">{{ $exhibition->title ?? '' }}</a></li>
        <li class="breadcrumb-item active">{{ __('Sections') }}</li>
      </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1>{{ __('Exhibition Sections') }}</h1>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSectionModal">
        <i class="fas fa-plus"></i> {{ __('Add Section') }}
      </button>
    </div>

    @if(empty($sections) || (is_object($sections) && method_exists($sections, 'isEmpty') && $sections->isEmpty()))
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-th-large fa-3x text-muted mb-3"></i>
          <h5>{{ __('No sections created yet') }}</h5>
          <p class="text-muted">{{ __('Organize your exhibition by creating sections or galleries.') }}</p>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
            <i class="fas fa-plus"></i> {{ __('Create First Section') }}
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
                  {{ $sec->title ?? '' }}
                </h6>
                <span class="badge bg-primary order-badge">{{ $sec->sequence_order ?? 0 }}</span>
              </div>
              <div class="card-body">
                @if(!empty($sec->subtitle))
                  <p class="small fw-semibold mb-1">{{ $sec->subtitle }}</p>
                @endif
                @if(!empty($sec->description))
                  <p class="small text-muted mb-2">{{ $sec->description }}</p>
                @endif
                @if(!empty($sec->section_type))
                  <p class="small mb-1"><i class="fas fa-shapes me-1"></i> <strong>{{ __('Type:') }}</strong> {{ $sectionTypes[$sec->section_type] ?? $sec->section_type }}</p>
                @endif
                @if(!empty($sec->gallery_name))
                  <p class="small mb-1"><i class="fas fa-map-marker me-1"></i> <strong>{{ __('Gallery:') }}</strong> {{ $sec->gallery_name }}</p>
                @endif
                @if(!empty($sec->theme))
                  <p class="small mb-1"><i class="fas fa-tag me-1"></i> <strong>{{ __('Theme:') }}</strong> {{ $sec->theme }}</p>
                @endif
                <p class="small mb-0"><i class="fas fa-archive me-1"></i> <strong>{{ __('Objects:') }}</strong> {{ $sec->object_count ?? 0 }}</p>
              </div>
              <div class="card-footer bg-transparent">
                <div class="btn-group btn-group-sm w-100">
                  <a href="{{ route('exhibition.objects', ['id' => $exId]) }}?section={{ $sec->id ?? '' }}" class="btn btn-outline-primary">
                    <i class="fas fa-archive"></i> {{ __('Objects') }}
                  </a>
                  <button type="button" class="btn btn-outline-secondary"
                          data-bs-toggle="modal" data-bs-target="#editSectionModal"
                          data-action="{{ route('exhibition.sections.update', ['id' => $exId, 'sectionId' => $sec->id]) }}"
                          data-title="{{ $sec->title ?? '' }}"
                          data-subtitle="{{ $sec->subtitle ?? '' }}"
                          data-description="{{ $sec->description ?? '' }}"
                          data-section-type="{{ $sec->section_type ?? '' }}"
                          data-gallery="{{ $sec->gallery_name ?? '' }}"
                          data-floor="{{ $sec->floor_level ?? '' }}"
                          data-theme="{{ $sec->theme ?? '' }}"
                          data-order="{{ $sec->sequence_order ?? 0 }}">
                    <i class="fas fa-edit"></i> {{ __('Edit') }}
                  </button>
                  <form method="post" action="{{ route('exhibition.sections.delete', ['id' => $exId, 'sectionId' => $sec->id]) }}" class="d-inline"
                        data-confirm="{{ __('Delete section ":name"? Objects will be unassigned but not removed from the exhibition.', ['name' => $sec->title ?? '']) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-0 rounded-end" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                  </form>
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
        <h5 class="mb-0">{{ __('Exhibition Info') }}</h5>
      </div>
      <div class="card-body">
        <h6>{{ $exhibition->title ?? '' }}</h6>
        <p class="small text-muted mb-2">
          <span class="badge bg-secondary">{{ $exhibition->status ?? '' }}</span>
        </p>
        <p class="small mb-0">{{ __(':count sections', ['count' => is_countable($sections) ? count($sections) : 0]) }}</p>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Tips') }}</h5>
      </div>
      <div class="card-body">
        <p class="small text-muted mb-2">{{ __('Sections help organize your exhibition into logical groupings or physical spaces.') }}</p>
        <ul class="small text-muted mb-0">
          <li>{{ __('Drag sections to reorder them') }}</li>
          <li>{{ __('Assign objects to sections for better organization') }}</li>
          <li>{{ __('Use themes to create narrative flow') }}</li>
          <li>{{ __('Link to physical galleries if applicable') }}</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addSectionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Add Section') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="{{ route('exhibition.sections.store', ['id' => $exId]) }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="addTitle">{{ __('Section Title') }} <span class="text-danger">*</span></label>
            <input type="text" name="title" id="addTitle" class="form-control" maxlength="255" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="addSubtitle">{{ __('Subtitle') }}</label>
            <input type="text" name="subtitle" id="addSubtitle" class="form-control" maxlength="255">
          </div>
          <div class="mb-3">
            <label class="form-label" for="addDescription">{{ __('Description') }}</label>
            <textarea name="description" id="addDescription" class="form-control" rows="2"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="addSectionType">{{ __('Section Type') }}</label>
              <select name="section_type" id="addSectionType" class="form-select">
                @foreach(($sectionTypes ?? []) as $code => $label)
                  <option value="{{ $code }}" @selected($code === 'gallery')>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="addFloor">{{ __('Floor Level') }}</label>
              <input type="text" name="floor_level" id="addFloor" class="form-control" maxlength="20">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="addGallery">{{ __('Gallery Name') }}</label>
            <input type="text" name="gallery_name" id="addGallery" class="form-control" maxlength="100" placeholder="{{ __('Physical location name') }}">
          </div>
          <div class="mb-3">
            <label class="form-label" for="addTheme">{{ __('Theme') }}</label>
            <input type="text" name="theme" id="addTheme" class="form-control" maxlength="255">
          </div>
          <div class="mb-3">
            <label class="form-label" for="addOrder">{{ __('Display Order') }}</label>
            <input type="number" name="sequence_order" id="addOrder" class="form-control" min="0" value="{{ (is_countable($sections) ? count($sections) : 0) + 1 }}">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Add Section') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editSectionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Edit Section') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="" id="editSectionForm">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="editTitle">{{ __('Section Title') }} <span class="text-danger">*</span></label>
            <input type="text" name="title" id="editTitle" class="form-control" maxlength="255" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editSubtitle">{{ __('Subtitle') }}</label>
            <input type="text" name="subtitle" id="editSubtitle" class="form-control" maxlength="255">
          </div>
          <div class="mb-3">
            <label class="form-label" for="editDescription">{{ __('Description') }}</label>
            <textarea name="description" id="editDescription" class="form-control" rows="2"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="editSectionType">{{ __('Section Type') }}</label>
              <select name="section_type" id="editSectionType" class="form-select">
                @foreach(($sectionTypes ?? []) as $code => $label)
                  <option value="{{ $code }}" @selected($code === 'gallery')>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="editFloor">{{ __('Floor Level') }}</label>
              <input type="text" name="floor_level" id="editFloor" class="form-control" maxlength="20">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editGallery">{{ __('Gallery Name') }}</label>
            <input type="text" name="gallery_name" id="editGallery" class="form-control" maxlength="100" placeholder="{{ __('Physical location name') }}">
          </div>
          <div class="mb-3">
            <label class="form-label" for="editTheme">{{ __('Theme') }}</label>
            <input type="text" name="theme" id="editTheme" class="form-control" maxlength="255">
          </div>
          <div class="mb-3">
            <label class="form-label" for="editOrder">{{ __('Display Order') }}</label>
            <input type="number" name="sequence_order" id="editOrder" class="form-control" min="0">
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
<script src="{{ asset('vendor/ahg-theme-b5/js/sortable.min.js') }}"></script>
<script nonce="{{ csp_nonce() }}">
document.addEventListener('DOMContentLoaded', function () {
  var csrf = document.querySelector('meta[name="csrf-token"]');
  csrf = csrf ? csrf.getAttribute('content') : '';

  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) { e.preventDefault(); } });
  });

  document.getElementById('editSectionModal').addEventListener('show.bs.modal', function (event) {
    var b = event.relatedTarget;
    document.getElementById('editSectionForm').action = b.dataset.action;
    document.getElementById('editTitle').value = b.dataset.title || '';
    document.getElementById('editSubtitle').value = b.dataset.subtitle || '';
    document.getElementById('editDescription').value = b.dataset.description || '';
    document.getElementById('editSectionType').value = b.dataset.sectionType || 'gallery';
    document.getElementById('editGallery').value = b.dataset.gallery || '';
    document.getElementById('editFloor').value = b.dataset.floor || '';
    document.getElementById('editTheme').value = b.dataset.theme || '';
    document.getElementById('editOrder').value = b.dataset.order || '0';
  });

  var list = document.getElementById('sectionList');
  if (list && window.Sortable) {
    new Sortable(list, {
      handle: '.drag-handle',
      animation: 150,
      onEnd: function () {
        var order = [];
        list.querySelectorAll('[data-id]').forEach(function (card, i) {
          order.push(parseInt(card.dataset.id, 10));
          var badge = card.querySelector('.order-badge');
          if (badge) { badge.textContent = i + 1; }
        });
        fetch(@json(route('exhibition.sections.reorder', ['id' => $exId])), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
          body: JSON.stringify({ order: order })
        }).then(function (r) { if (!r.ok) { throw new Error(); } })
          .catch(function () { alert(@json(__('The new order could not be saved.'))); });
      }
    });
  }
});
</script>
@endpush
