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

@section('title', 'Validation Report')

@section('content')
@php
    $session = $session ?? null;
    $stats = $stats ?? [];
    // The controller passes validation errors as $errors (array), but Laravel also
    // auto-shares a ViewErrorBag under the same key via ShareErrorsFromSession.
    // Alias the array before any theme partial touches $errors, then restore the bag.
    $validationErrors = (isset($errors) && is_array($errors)) ? $errors : [];
    if (isset($errors) && is_array($errors)) {
        $errors = new \Illuminate\Support\ViewErrorBag();
    }
    $rowCount = $rowCount ?? 0;
    $validCount = $stats['valid'] ?? 0;
@endphp

<h1>Validation Report</h1>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('ingest.index') }}">Ingestion Manager</a></li>
        <li class="breadcrumb-item">{{ $session->title ?? ('Session #' . ($session->id ?? '')) }}</li>
        <li class="breadcrumb-item active" aria-current="page">Validate</li>
    </ol>
</nav>

{{-- Wizard Progress --}}
<div class="mb-4">
    <div class="d-flex justify-content-between text-center">
        <div class="flex-fill"><span class="badge bg-success rounded-pill">1</span><br><small class="text-muted">Configure</small></div>
        <div class="flex-fill"><span class="badge bg-success rounded-pill">2</span><br><small class="text-muted">Upload</small></div>
        <div class="flex-fill"><span class="badge bg-success rounded-pill">3</span><br><small class="text-muted">Map</small></div>
        <div class="flex-fill"><span class="badge bg-primary rounded-pill">4</span><br><small class="fw-bold">Validate</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">5</span><br><small class="text-muted">Preview</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">6</span><br><small class="text-muted">Commit</small></div>
    </div>
    <div class="progress mt-2" style="height: 4px;">
        <div class="progress-bar" style="width: 58%"></div>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="mb-0">{{ $stats['total'] ?? 0 }}</h3>
                <small class="text-muted">Total Rows</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-success">
            <div class="card-body">
                <h3 class="mb-0 text-success">{{ $stats['valid'] ?? 0 }}</h3>
                <small class="text-muted">Valid</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body">
                <h3 class="mb-0 text-warning">{{ $stats['warnings'] ?? 0 }}</h3>
                <small class="text-muted">Warnings</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-danger">
            <div class="card-body">
                <h3 class="mb-0 text-danger">{{ $stats['errors'] ?? 0 }}</h3>
                <small class="text-muted">Errors</small>
            </div>
        </div>
    </div>
</div>

@if(!empty($validationErrors))
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Issues</h5>
        <span class="badge bg-secondary">{{ count($validationErrors) }} issues</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 8%">Row</th>
                        <th style="width: 10%">Severity</th>
                        <th style="width: 15%">Field</th>
                        <th>Message</th>
                        <th style="width: 20%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($validationErrors as $err)
                        <tr>
                            <td><strong>#{{ $err->row_number ?? '' }}</strong></td>
                            <td>
                                @if(($err->severity ?? '') === 'error')
                                    <span class="badge bg-danger">Error</span>
                                @elseif(($err->severity ?? '') === 'warning')
                                    <span class="badge bg-warning text-dark">Warning</span>
                                @else
                                    <span class="badge bg-info">Info</span>
                                @endif
                            </td>
                            <td><code>{{ $err->field_name ?? '' }}</code></td>
                            <td>{{ $err->message ?? '' }}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @if(!empty($err->field_name))
                                        <button type="button" class="btn btn-outline-primary btn-fix"
                                                data-row="{{ $err->row_number ?? '' }}"
                                                data-field="{{ $err->field_name }}">
                                            <i class="fas fa-edit"></i> Fix
                                        </button>
                                    @endif
                                    <form method="post" action="{{ route('ingest.validate', ['id' => $session->id ?? 0]) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="form_action" value="exclude">
                                        <input type="hidden" name="row_number" value="{{ $err->row_number ?? '' }}">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-times"></i> Exclude
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@else
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>All rows passed validation.
    </div>
@endif

{{-- Inline Fix Modal --}}
<div class="modal fade" id="fixModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('ingest.validate', ['id' => $session->id ?? 0]) }}">
                @csrf
                <input type="hidden" name="form_action" value="fix">
                <input type="hidden" name="row_number" id="fix_row_number">
                <input type="hidden" name="field_name" id="fix_field_name">
                <div class="modal-header">
                    <h5 class="modal-title">Fix Field Value</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Row: <strong id="fix_row_label"></strong></label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Field: <code id="fix_field_label"></code></label>
                    </div>
                    <div class="mb-3">
                        <label for="fix_value" class="form-label">New value</label>
                        <input type="text" class="form-control" id="fix_value" name="field_value" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Fix</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between">
    <a href="{{ route('ingest.map', ['id' => $session->id ?? 0]) }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Mapping
    </a>
    <div>
        <form method="post" action="{{ route('ingest.validate', ['id' => $session->id ?? 0]) }}" class="d-inline">
            @csrf
            <input type="hidden" name="form_action" value="validate">
            <button type="submit" class="btn btn-outline-secondary me-2">
                <i class="fas fa-sync me-1"></i>Re-validate
            </button>
        </form>
        <form method="post" action="{{ route('ingest.validate', ['id' => $session->id ?? 0]) }}" class="d-inline">
            @csrf
            <input type="hidden" name="form_action" value="proceed">
            <button type="submit" class="btn btn-primary" {{ $validCount === 0 ? 'disabled' : '' }}>
                <i class="fas fa-eye me-1"></i>Preview ({{ $validCount }} valid rows)
                <i class="fas fa-arrow-right ms-1"></i>
            </button>
        </form>
        <form method="post" action="{{ route('ingest.validate', ['id' => $session->id ?? 0]) }}" class="d-inline ms-2">
            @csrf
            <input type="hidden" name="form_action" value="commit">
            <button type="submit" class="btn btn-success" {{ $validCount === 0 ? 'disabled' : '' }}>
                <i class="fas fa-check me-1"></i>Commit ({{ $validCount }} rows)
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-fix').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('fix_row_number').value = this.dataset.row;
            document.getElementById('fix_field_name').value = this.dataset.field;
            document.getElementById('fix_row_label').textContent = '#' + this.dataset.row;
            document.getElementById('fix_field_label').textContent = this.dataset.field;
            document.getElementById('fix_value').value = '';
            if (typeof bootstrap !== 'undefined') {
                new bootstrap.Modal(document.getElementById('fixModal')).show();
            }
        });
    });
});
</script>
@endsection
