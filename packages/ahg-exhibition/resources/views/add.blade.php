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

@section('title', 'New Exhibition')

@section('content')
@php
  $types = $types ?? [];
  $statuses = $statuses ?? [];
  $data = (object) ($formData ?? []);
@endphp

<div class="row">
  <div class="col-md-8">
    <h1>New Exhibition</h1>

    <form method="post" action="{{ route('exhibition.add') }}">
      @csrf
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Basic Information</h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required value="{{ $data->title ?? '' }}">
          </div>

          <div class="mb-3">
            <label class="form-label">Subtitle</label>
            <input type="text" name="subtitle" class="form-control" value="{{ $data->subtitle ?? '' }}">
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Exhibition Type</label>
              <select name="exhibition_type" class="form-select">
                @foreach($types as $key => $label)
                  <option value="{{ $key }}" {{ (($data->exhibition_type ?? 'temporary') == $key) ? 'selected' : '' }}>
                    {{ is_array($label) ? ($label['label'] ?? $key) : $label }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Project Code</label>
              <input type="text" name="project_code" class="form-control" value="{{ $data->project_code ?? '' }}">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Theme</label>
            <input type="text" name="theme" class="form-control" value="{{ $data->theme ?? '' }}" placeholder="e.g., African Art, Modern Sculpture, Industrial Heritage">
          </div>

          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4">{{ $data->description ?? '' }}</textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Target Audience</label>
            <input type="text" name="target_audience" class="form-control" value="{{ $data->target_audience ?? '' }}">
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Dates</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Start Date</label>
              <input type="date" name="start_date" class="form-control" value="{{ $data->start_date ?? '' }}">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">End Date</label>
              <input type="date" name="end_date" class="form-control" value="{{ $data->end_date ?? '' }}">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Venue &amp; Team</h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Venue</label>
            <input type="text" name="venue" class="form-control" value="{{ $data->venue ?? '' }}">
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Curator</label>
              <input type="text" name="curator" class="form-control" value="{{ $data->curator ?? '' }}">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Designer</label>
              <input type="text" name="designer" class="form-control" value="{{ $data->designer ?? '' }}">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Status &amp; Budget</h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              @foreach($statuses as $key => $label)
                <option value="{{ $key }}" {{ (($data->status ?? '') == $key) ? 'selected' : '' }}>
                  {{ is_array($label) ? ($label['label'] ?? $key) : $label }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="row">
            <div class="col-md-8 mb-3">
              <label class="form-label">Budget</label>
              <input type="number" name="budget" class="form-control" step="0.01" value="{{ $data->budget ?? '' }}">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Currency</label>
              <select name="budget_currency" class="form-select">
                @foreach(['USD','EUR','GBP','ZAR','JPY','AUD','CAD'] as $ccy)
                  <option value="{{ $ccy }}" {{ (($data->budget_currency ?? 'USD') == $ccy) ? 'selected' : '' }}>{{ $ccy }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between">
        <a href="{{ route('exhibition.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Create Exhibition</button>
      </div>
    </form>
  </div>

  <div class="col-md-4">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Help</h5>
      </div>
      <div class="card-body">
        <h6>Exhibition Types</h6>
        <ul class="small">
          <li><strong>Permanent</strong> - Long-term display, rarely changes</li>
          <li><strong>Temporary</strong> - Fixed duration, typically 3-12 months</li>
          <li><strong>Traveling</strong> - Moves between venues</li>
          <li><strong>Online</strong> - Virtual/digital exhibition</li>
          <li><strong>Pop-up</strong> - Short-term, often &lt; 1 month</li>
        </ul>

        <h6 class="mt-3">After Creating</h6>
        <p class="small text-muted">After creating the exhibition, you can:</p>
        <ul class="small text-muted">
          <li>Add sections/galleries</li>
          <li>Add objects from the collection</li>
          <li>Create storylines and narratives</li>
          <li>Schedule events</li>
          <li>Generate checklists</li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
