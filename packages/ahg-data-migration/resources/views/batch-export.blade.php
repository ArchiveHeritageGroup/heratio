@extends('theme::layouts.1col')

@section('title', 'Batch Export - Data Migration')
@section('body-class', 'admin data-migration batch-export')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-download me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Batch Export</h1>
      <span class="small text-muted">Export entity records to CSV</span>
    </div>
  </div>

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('data-migration.index') }}">Data Migration</a></li>
      <li class="breadcrumb-item active">Batch Export</li>
    </ol>
  </nav>

  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-filter"></i> Export Options</h5>
    </div>
    <div class="card-body">
      <form method="GET" action="{{ route('data-migration.batch-export') }}">
        <input type="hidden" name="export" value="csv">

        <div class="row mb-3">
          <div class="col-md-4">
            <label for="entity_type" class="form-label fw-bold">Entity Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
            <select class="form-select" id="entity_type" name="entity_type" required>
              <option value="">-- Select entity type --</option>
              <option value="informationObject">Information Objects ({{ number_format($counts['informationObject']) }})</option>
              <option value="actor">Actors ({{ number_format($counts['actor']) }})</option>
              <option value="repository">Repositories ({{ number_format($counts['repository']) }})</option>
              <option value="accession">Accessions ({{ number_format($counts['accession']) }})</option>
              <option value="donor">Donors ({{ number_format($counts['donor']) }})</option>
              <option value="physicalObject">Physical Objects ({{ number_format($counts['physicalObject']) }})</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="date_from" class="form-label fw-bold">Date From <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" class="form-control" id="date_from" name="date_from">
          </div>
          <div class="col-md-3">
            <label for="date_to" class="form-label fw-bold">Date To <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" class="form-control" id="date_to" name="date_to">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn atom-btn-outline-success w-100">
              <i class="fas fa-file-csv"></i> Export CSV
            </button>
          </div>
        </div>

        <div class="form-text">
          Leave date fields empty to export all records. The CSV will include a BOM for Excel compatibility.
        </div>
      </form>
    </div>
  </div>

  {{-- Record counts summary --}}
  <div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-database"></i> Record Counts</h5>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-6 col-md-2">
          <div class="card text-center">
            <div class="card-body py-2">
              <div class="fs-4 fw-bold">{{ number_format($counts['informationObject']) }}</div>
              <div class="small text-muted">Information Objects</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="card text-center">
            <div class="card-body py-2">
              <div class="fs-4 fw-bold">{{ number_format($counts['actor']) }}</div>
              <div class="small text-muted">Actors</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="card text-center">
            <div class="card-body py-2">
              <div class="fs-4 fw-bold">{{ number_format($counts['repository']) }}</div>
              <div class="small text-muted">Repositories</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="card text-center">
            <div class="card-body py-2">
              <div class="fs-4 fw-bold">{{ number_format($counts['accession']) }}</div>
              <div class="small text-muted">Accessions</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="card text-center">
            <div class="card-body py-2">
              <div class="fs-4 fw-bold">{{ number_format($counts['donor']) }}</div>
              <div class="small text-muted">Donors</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="card text-center">
            <div class="card-body py-2">
              <div class="fs-4 fw-bold">{{ number_format($counts['physicalObject']) }}</div>
              <div class="small text-muted">Physical Objects</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3">
    <a href="{{ route('data-migration.index') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
  </div>
@endsection
