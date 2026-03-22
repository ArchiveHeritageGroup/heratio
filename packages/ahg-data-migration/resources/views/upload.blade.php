@extends('theme::layouts.1col')

@section('title', 'Upload File - Data Migration')
@section('body-class', 'admin data-migration upload')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-upload me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Upload File</h1>
      <span class="small text-muted">Data Migration</span>
    </div>
  </div>

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('data-migration.index') }}">Data Migration</a></li>
      <li class="breadcrumb-item active">Upload</li>
    </ol>
  </nav>

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-file-csv"></i> Select File and Options</h5>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('data-migration.upload') }}" enctype="multipart/form-data">
        @csrf

        <div class="mb-3">
          <label for="file" class="form-label fw-bold">File <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
          <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" accept=".csv,.txt,.xml" required>
          <div class="form-text">Accepted formats: CSV (.csv, .txt), XML (.xml). Maximum size: 100 MB.</div>
          @error('file')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="mb-3">
          <label for="target_type" class="form-label fw-bold">Target Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
          <select class="form-select @error('target_type') is-invalid @enderror" id="target_type" name="target_type" required>
            <option value="">-- Select target type --</option>
            <option value="informationObject" {{ old('target_type') === 'informationObject' ? 'selected' : '' }}>Information Objects</option>
            <option value="actor" {{ old('target_type') === 'actor' ? 'selected' : '' }}>Actors</option>
            <option value="accession" {{ old('target_type') === 'accession' ? 'selected' : '' }}>Accessions</option>
            <option value="repository" {{ old('target_type') === 'repository' ? 'selected' : '' }}>Repositories</option>
          </select>
          @error('target_type')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="mb-3">
          <label for="import_type" class="form-label fw-bold">Import Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
          <select class="form-select @error('import_type') is-invalid @enderror" id="import_type" name="import_type" required>
            <option value="create" {{ old('import_type', 'create') === 'create' ? 'selected' : '' }}>Create new records</option>
            <option value="update" {{ old('import_type') === 'update' ? 'selected' : '' }}>Match and update existing</option>
            <option value="replace" {{ old('import_type') === 'replace' ? 'selected' : '' }}>Delete and replace</option>
          </select>
          <div class="form-text">
            <strong>Create new:</strong> All rows create new records.<br>
            <strong>Match and update:</strong> Match by identifier/name and update existing records.<br>
            <strong>Delete and replace:</strong> Delete matched records and re-create from CSV.
          </div>
          @error('import_type')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn atom-btn-outline-success">
            <i class="fas fa-arrow-right"></i> Upload &amp; Continue
          </button>
          <a href="{{ route('data-migration.index') }}" class="btn atom-btn-white">Cancel</a>
        </div>
      </form>
    </div>
  </div>
@endsection
