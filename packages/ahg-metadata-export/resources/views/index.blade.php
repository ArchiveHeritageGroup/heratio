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

@section('title', 'Metadata Export')

@section('content')
<h1>Metadata Export</h1>

<div class="alert alert-info">
  <i class="fa fa-info-circle"></i>
  Export archival descriptions to various international metadata standards.
</div>

@if(session('notice'))
  <div class="alert alert-success">{{ session('notice') }}</div>
@endif

<div class="row">
  <div class="col-md-8">
    <h2>Select Export Format</h2>
    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title mb-0">Supported Formats
          <small class="text-muted">({{ count($formats ?? []) }} formats)</small>
        </h3>
      </div>
      <div class="card-body">
        <div class="row">
          @foreach(($formats ?? []) as $code => $format)
            <div class="col-md-4 mb-3">
              <div class="card h-100">
                <div class="card-body">
                  <h5 class="card-title">
                    <i class="bi {{ $format['icon'] ?? 'bi-file-earmark-code' }}"></i>
                    {{ $format['name'] ?? $code }}
                  </h5>
                  <p class="card-text">
                    <span class="badge bg-secondary">{{ strtoupper($code) }}</span>
                  </p>
                </div>
                <div class="card-footer bg-transparent border-0">
                  <div class="btn-group btn-group-sm w-100" role="group">
                    <a href="{{ route('ahgmetadataexport.bulk') }}?format={{ $code }}" class="btn btn-outline-primary">
                      <i class="fa fa-download"></i> Bulk Export
                    </a>
                  </div>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title mb-0">Quick Export</h3>
      </div>
      <div class="card-body">
        <form action="{{ route('ahgmetadataexport.preview') }}" method="get">
          <div class="mb-3">
            <label for="format" class="form-label">Format</label>
            <select name="format" id="format" class="form-select" required>
              <option value="">Select format...</option>
              @foreach(($formats ?? []) as $code => $format)
                <option value="{{ $code }}">{{ $format['name'] ?? $code }}</option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label for="slug" class="form-label">Record Slug</label>
            <input type="text" name="slug" id="slug" class="form-control" required
                   placeholder="e.g., my-fonds">
            <div class="form-text">Enter the slug of the record to export.</div>
          </div>

          <div class="mb-3">
            <div class="form-check">
              <input type="checkbox" name="include_children" value="1" id="include_children" class="form-check-input" checked>
              <label class="form-check-label" for="include_children">Include children</label>
            </div>
            <div class="form-check">
              <input type="checkbox" name="include_digital_objects" value="1" id="include_digital_objects" class="form-check-input" checked>
              <label class="form-check-label" for="include_digital_objects">Include digital objects</label>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="fa fa-eye"></i> Preview
          </button>
        </form>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header">
        <h3 class="card-title mb-0">CLI Usage</h3>
      </div>
      <div class="card-body">
        <p class="small text-muted">For bulk exports, use the command line:</p>
        <pre class="bg-light p-2 small"><code>php artisan metadata:export --format=ead3 --slug=my-fonds --output=/exports/</code></pre>
        <pre class="bg-light p-2 small"><code>php artisan metadata:export --list</code></pre>
      </div>
    </div>
  </div>
</div>
@endsection
