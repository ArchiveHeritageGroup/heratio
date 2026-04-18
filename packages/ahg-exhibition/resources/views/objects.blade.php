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

@section('title', 'Exhibition Objects')

@section('content')
@php
  $exhibition = $exhibition ?? (object) [];
  $exId = $exhibition->id ?? 0;
  $objects = $exhibition->objects ?? collect();
  $sections = $exhibition->sections ?? collect();
  $currentSection = $currentSection ?? request('section', '');
@endphp

<div class="row">
  <div class="col-md-8">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">Exhibitions</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.show', ['id' => $exId]) }}">{{ $exhibition->title ?? '' }}</a></li>
        <li class="breadcrumb-item active">Objects</li>
      </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1>Exhibition Objects</h1>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addObjectModal">
        <i class="fas fa-plus"></i> Add Object
      </button>
    </div>

    @if(empty($objects) || (is_object($objects) && method_exists($objects, 'isEmpty') && $objects->isEmpty()))
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-archive fa-3x text-muted mb-3"></i>
          <h5>No objects added yet</h5>
          <p class="text-muted">Add objects from the collection to this exhibition.</p>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addObjectModal">
            <i class="fas fa-plus"></i> Add First Object
          </button>
        </div>
      </div>
    @else
      @if(!empty($sections) && (!is_object($sections) || !method_exists($sections, 'isEmpty') || !$sections->isEmpty()))
        <div class="card mb-3">
          <div class="card-body py-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="small text-muted">Filter by section:</span>
              <a href="?" class="btn btn-sm {{ empty($currentSection) ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
              @foreach($sections as $section)
                @php $sec = (object) $section; @endphp
                <a href="?section={{ $sec->id ?? '' }}" class="btn btn-sm {{ $currentSection == ($sec->id ?? '') ? 'btn-primary' : 'btn-outline-secondary' }}">
                  {{ $sec->title ?? ($sec->name ?? '') }}
                </a>
              @endforeach
            </div>
          </div>
        </div>
      @endif

      <div class="card">
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th style="width: 60px;"></th>
                <th>Object</th>
                <th>Section</th>
                <th>Location</th>
                <th>Loan</th>
                <th>Display Order</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="objectList">
              @foreach($objects as $object)
                @php $obj = (object) $object; @endphp
                <tr data-id="{{ $obj->id ?? '' }}">
                  <td class="text-center">
                    <i class="fas fa-grip-vertical text-muted drag-handle" style="cursor: move;"></i>
                  </td>
                  <td>
                    @if(!empty($obj->slug))
                      <a href="{{ url('/'.$obj->slug) }}">
                        <strong>{{ $obj->object_title ?? ($obj->identifier ?? '') }}</strong>
                      </a>
                    @else
                      <strong>{{ $obj->object_title ?? ($obj->identifier ?? '') }}</strong>
                    @endif
                    @if(!empty($obj->identifier))
                      <br><small class="text-muted">{{ $obj->identifier }}</small>
                    @endif
                  </td>
                  <td>
                    @if(!empty($obj->section_title))
                      {{ $obj->section_title }}
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td>
                    @if(!empty($obj->display_position))
                      {{ $obj->display_position }}
                    @else
                      <span class="text-muted">Not assigned</span>
                    @endif
                  </td>
                  <td>
                    @if(!empty($obj->requires_loan))
                      <span class="badge bg-warning text-dark">Loan required</span>
                      @if(!empty($obj->lender_institution))
                        <br><small class="text-muted">{{ $obj->lender_institution }}</small>
                      @endif
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td>
                    <span class="badge bg-secondary">{{ $obj->sort_order ?? ($obj->sequence_order ?? '-') }}</span>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <button type="button" class="btn btn-outline-primary" title="Edit">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button type="button" class="btn btn-outline-danger" title="Remove">
                        <i class="fas fa-times"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="card-footer">
          <span class="text-muted">{{ is_countable($objects) ? count($objects) : 0 }} objects in exhibition</span>
        </div>
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
        @if(!empty($exhibition->start_date))
          <p class="small mb-1">
            <i class="fas fa-calendar me-1"></i>
            {{ $exhibition->start_date }}
            @if(!empty($exhibition->end_date)) - {{ $exhibition->end_date }} @endif
          </p>
        @endif
      </div>
    </div>

    @if(!empty($sections) && (!is_object($sections) || !method_exists($sections, 'isEmpty') || !$sections->isEmpty()))
      <div class="card mb-3">
        <div class="card-header">
          <h5 class="mb-0">Sections</h5>
        </div>
        <ul class="list-group list-group-flush">
          @foreach($sections as $section)
            @php $sec = (object) $section; @endphp
            <li class="list-group-item d-flex justify-content-between align-items-center">
              {{ $sec->title ?? ($sec->name ?? '') }}
              <span class="badge bg-primary rounded-pill">{{ $sec->object_count ?? 0 }}</span>
            </li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Actions</h5>
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('exhibition.objectList', ['id' => $exId]) }}" class="list-group-item list-group-item-action">
          <i class="fas fa-file-text me-2"></i> Generate Object List
        </a>
        <a href="{{ route('exhibition.sections', ['id' => $exId]) }}" class="list-group-item list-group-item-action">
          <i class="fas fa-th-large me-2"></i> Manage Sections
        </a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addObjectModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Object to Exhibition</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="{{ route('exhibition.objects', ['id' => $exId]) }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Search Objects</label>
            <input type="text" id="objectSearch" class="form-control" placeholder="Search by title, number, or description...">
            <input type="hidden" name="museum_object_id" id="selectedObjectId">
          </div>

          <div class="mb-3">
            <label class="form-label">Display Location</label>
            <input type="text" name="display_location" class="form-control" placeholder="e.g., Gallery A, Case 3">
          </div>

          <div class="mb-3">
            <label class="form-label">Display Notes</label>
            <textarea name="display_notes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Object</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
