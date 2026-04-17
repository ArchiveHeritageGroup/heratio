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

@section('title', 'Bulk Export')

@section('content')
@php
  $format = $format ?? 'dc';
  $formatInfo = $formatInfo ?? ['name' => strtoupper($format)];
  $repositories = $repositories ?? collect();
@endphp

<h1>
  Bulk Export
  <small class="text-muted">- {{ $formatInfo['name'] ?? strtoupper($format) }}</small>
</h1>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('ahgmetadataexport.index') }}">Metadata Export</a></li>
    <li class="breadcrumb-item active" aria-current="page">Bulk Export</li>
  </ol>
</nav>

@if(session('error'))
  <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> {{ session('error') }}</div>
@endif
@if(session('success'))
  <div class="alert alert-success"><i class="fa fa-check-circle"></i> {{ session('success') }}</div>
@endif

<div class="row">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title mb-0">Export Settings</h3>
      </div>
      <div class="card-body">
        <form action="{{ route('ahgmetadataexport.bulk') }}?format={{ $format }}" method="post">
          @csrf

          <input type="hidden" name="format" value="{{ $format }}">

          <div class="mb-3">
            <label for="repository_id" class="form-label">Repository <span class="text-danger">*</span></label>
            <select name="repository_id" id="repository_id" class="form-select" required>
              <option value="">Select repository...</option>
              @foreach($repositories as $repo)
                <option value="{{ $repo->id }}">
                  {{ $repo->authorizedFormOfName ?? $repo->name ?? $repo->id }}
                </option>
              @endforeach
            </select>
            <div class="form-text">Select the repository to export records from.</div>
          </div>

          <hr>

          <h4>Export Options</h4>

          <div class="mb-3">
            <div class="form-check">
              <input type="checkbox" name="include_children" value="1" id="include_children" class="form-check-input" checked>
              <label class="form-check-label" for="include_children">Include child records</label>
              <div class="form-text">Export the full hierarchy including all descendants.</div>
            </div>
          </div>

          <div class="mb-3">
            <div class="form-check">
              <input type="checkbox" name="include_digital_objects" value="1" id="include_digital_objects" class="form-check-input" checked>
              <label class="form-check-label" for="include_digital_objects">Include digital objects</label>
              <div class="form-text">Include references to attached digital objects.</div>
            </div>
          </div>

          <div class="mb-3">
            <div class="form-check">
              <input type="checkbox" name="include_drafts" value="1" id="include_drafts" class="form-check-input">
              <label class="form-check-label" for="include_drafts">Include draft records</label>
              <div class="form-text">Also export records with draft publication status.</div>
            </div>
          </div>

          <div class="mb-3">
            <label for="max_depth" class="form-label">Maximum Depth</label>
            <input type="number" name="max_depth" id="max_depth" class="form-control" value="0" min="0" max="99">
            <div class="form-text">Limit hierarchy depth (0 = unlimited).</div>
          </div>

          <hr>

          <div class="d-flex justify-content-between">
            <a href="{{ route('ahgmetadataexport.index') }}" class="btn btn-secondary">
              <i class="fa fa-arrow-left"></i> Back
            </a>
            <button type="submit" class="btn btn-primary">
              <i class="fa fa-download"></i> Export as ZIP
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title mb-0">Format Information</h3>
      </div>
      <div class="card-body">
        <dl>
          <dt>Format</dt>
          <dd><strong>{{ $formatInfo['name'] ?? strtoupper($format) }}</strong></dd>
          <dt>Code</dt>
          <dd><span class="badge bg-secondary">{{ $format }}</span></dd>
        </dl>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header">
        <h3 class="card-title mb-0">Export Information</h3>
      </div>
      <div class="card-body">
        <p class="small text-muted">
          This will export all top-level records from the selected repository. Each record will be
          exported as a separate file, and all files will be packaged into a ZIP archive for download.
        </p>

        <div class="alert alert-info small">
          <i class="fa fa-info-circle"></i>
          For very large repositories, consider using the CLI command for better performance.
        </div>

        <pre class="bg-light p-2 small"><code>php artisan metadata:export \
  --format={{ $format }} \
  --repository=ID \
  --output=/exports/</code></pre>
      </div>
    </div>
  </div>
</div>
@endsection
