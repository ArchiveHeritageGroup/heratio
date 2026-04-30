{{--
  EAD 2002 Export View - Heratio

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Heratio is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU Affero General Public License for more details.
--}}
@extends('theme::layouts.1col')

@section('title')
  <h1>{{ __('EAD 2002 Export') }}</h1>
@endsection

@section('content')

<div class="card">
  <div class="card-header">
    <h5 class="mb-0">{{ __('Export to Encoded Archival Description (EAD)') }}</h5>
  </div>
  <div class="card-body">

    <div class="alert alert-info">
      <i class="bi bi-info-circle me-2"></i>
      Export archival descriptions in EAD 2002 XML format. Select a top-level record (fonds/collection) to export with its hierarchy.
    </div>

    <form action="{{ route('export.ead') }}" method="post">
      @csrf

      <div class="mb-3">
        <label class="form-label">{{ __('Select Record to Export') }}</label>
        <select name="object_id" class="form-select" required>
          <option value="">-- Select a fonds or collection --</option>
          @foreach ($fonds as $f)
            <option value="{{ $f->id }}">
              {{ ($f->identifier ? $f->identifier . ' - ' : '') . $f->title }}
            </option>
          @endforeach
        </select>
        <small class="text-muted">Showing top-level archival descriptions.</small>
      </div>

      <div class="mb-3 form-check">
        <input type="checkbox" name="include_descendants" value="1" class="form-check-input" id="includeDescendants" checked>
        <label class="form-check-label" for="includeDescendants">
          Include all descendants (series, files, items)
        </label>
      </div>

      <hr>

      <h6>{{ __('EAD Export includes:') }}</h6>
      <ul class="small text-muted">
        <li>Descriptive identification (unitid, unittitle, unitdate)</li>
        <li>Scope and content</li>
        <li>Arrangement</li>
        <li>Access and use restrictions</li>
        <li>Custodial history</li>
        <li>Subject access points</li>
        <li>Hierarchical component structure (dsc/c)</li>
      </ul>

      <hr>

      <div class="d-flex justify-content-between">
        <a href="{{ route('export.index') }}" class="btn btn-secondary">
          <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-download me-1"></i>Export EAD XML
        </button>
      </div>

    </form>

  </div>
</div>

@endsection
