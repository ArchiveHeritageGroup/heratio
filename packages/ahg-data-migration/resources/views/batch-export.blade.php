{{-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems — johan@plansailingisystems
     This file is part of Heratio. Licensed under the GNU Affero General Public License v3+. --}}
@extends('theme::layouts.1col')

@section('title', 'Batch Export - Data Migration')
@section('body-class', 'admin data-migration batch-export')

@section('content')
<div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-download me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
        <h1 class="mb-0">Batch Export</h1>
        <span class="small text-muted">Export existing records to sector-specific CSV format</span>
    </div>
</div>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('data-migration.index') }}">Data Migration</a></li>
        <li class="breadcrumb-item active">Batch Export</li>
    </ol>
</nav>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if (session('notice'))
    <div class="alert alert-info">{{ session('notice') }}</div>
@endif

<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Export existing records</strong> to sector-specific CSV format for backup, reporting, or migration to another system.
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-download me-2"></i>Batch Export Records</h5>
        <a href="{{ route('data-migration.index') }}" class="btn btn-sm atom-btn-outline-light">
            <i class="fas fa-arrow-left me-1"></i>Back to Import
        </a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('data-migration.batch-export') }}" id="batchExportForm">
            @csrf

            <div class="mb-4">
                <h6 class="text-primary"><span class="badge bg-primary me-2">1</span>Export Format</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Sector Format <span class="badge bg-danger ms-1">Required</span></label>
                        <select name="sector" id="sectorSelect" class="form-select" required>
                            @foreach (($sectors ?? []) as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Select the standard format for CSV columns</small>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h6 class="text-primary"><span class="badge bg-primary me-2">2</span>Filter Records (Optional)</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Repository <span class="badge bg-secondary ms-1">Optional</span></label>
                        <select name="repository_id" id="repositorySelect" class="form-select">
                            <option value="">All repositories</option>
                            @foreach (($repositories ?? []) as $repo)
                                <option value="{{ $repo->id }}">{{ $repo->name ?? $repo->authorized_form_of_name ?? ('#' . $repo->id) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Level of Description <span class="badge bg-secondary ms-1">Optional</span></label>
                        <select name="level_ids[]" id="levelSelect" class="form-select" multiple size="4">
                            @foreach (($levels ?? []) as $level)
                                <option value="{{ $level->id }}">{{ $level->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <label class="form-label">Parent Record Slug (Scope) <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="text" name="parent_slug" id="parentSlug" class="form-control" placeholder="e.g. my-fonds-123">
                        <small class="text-muted">Export only children of this record</small>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="include_descendants" id="includeDescendants" class="form-check-input" value="1">
                            <label class="form-check-label" for="includeDescendants">
                                Include all descendants (not just direct children)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h6 class="text-primary"><span class="badge bg-primary me-2">3</span>Export</h6>
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> Exports with more than 500 records will be queued as a background job.
                    You can check progress on the <a href="{{ route('data-migration.jobs') }}">Jobs page</a>.
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="{{ route('data-migration.index') }}" class="btn atom-btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg" id="exportBtn">
                    <i class="fas fa-download me-2"></i>Export CSV
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Format Descriptions</h6>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Archives (ISAD-G)</dt>
            <dd class="col-sm-9">Standard archival description fields following ISAD(G) standard. Best for archives and manuscript collections.</dd>

            <dt class="col-sm-3">Museum (Spectrum)</dt>
            <dd class="col-sm-9">Spectrum 5.1 standard fields for museum objects including production, acquisition, and location data.</dd>

            <dt class="col-sm-3">Library (MARC/RDA)</dt>
            <dd class="col-sm-9">MARC and RDA cataloguing fields for bibliographic records including ISBN, call numbers, and publishing data.</dd>

            <dt class="col-sm-3">Gallery (CCO/VRA)</dt>
            <dd class="col-sm-9">Cataloging Cultural Objects (CCO) and VRA Core fields for artworks and visual resources.</dd>

            <dt class="col-sm-3">Digital Assets</dt>
            <dd class="col-sm-9">Dublin Core and IPTC metadata fields for digital asset management including technical metadata.</dd>
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Record Counts</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach ([
                'informationObject' => 'Information Objects',
                'actor' => 'Actors',
                'repository' => 'Repositories',
                'accession' => 'Accessions',
                'donor' => 'Donors',
                'physicalObject' => 'Physical Objects',
            ] as $key => $label)
                <div class="col-6 col-md-2">
                    <div class="card text-center">
                        <div class="card-body py-2">
                            <div class="fs-4 fw-bold">{{ number_format($counts[$key] ?? 0) }}</div>
                            <div class="small text-muted">{{ $label }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('batchExportForm');
    var exportBtn = document.getElementById('exportBtn');
    if (form && exportBtn) {
        form.addEventListener('submit', function () {
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Exporting...';
        });
    }
    var parentSlug = document.getElementById('parentSlug');
    var includeDescendants = document.getElementById('includeDescendants');
    if (parentSlug && includeDescendants) {
        parentSlug.addEventListener('input', function () {
            includeDescendants.disabled = !this.value.trim();
            if (!this.value.trim()) { includeDescendants.checked = false; }
        });
        includeDescendants.disabled = !parentSlug.value.trim();
    }
});
</script>
@endsection
