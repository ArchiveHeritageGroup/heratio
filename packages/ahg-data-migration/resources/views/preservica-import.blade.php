@extends('theme::layouts.1col')
@section('title', 'Preservica Import - Data Migration')
@section('body-class', 'admin data-migration preservica-import')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cloud-upload-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Preservica Import</h1>
      <span class="small text-muted">Data Migration — Preservica</span>
    </div>
  </div>
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('data-migration.index') }}">Data Migration</a></li>
      <li class="breadcrumb-item active">Preservica Import</li>
    </ol>
  </nav>
  <div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-cog"></i> Preservica Connection</h5>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('data-migration.preservica-import') }}">
        @csrf
        <div class="mb-3">
          <label for="preservica_url" class="form-label fw-bold">
            Preservica URL <span class="badge bg-danger ms-1">Required</span>
          </label>
          <input type="url" class="form-control" id="preservica_url" name="preservica_url"
                 placeholder="https://your-institution.preservica.com"
                 value="{{ old('preservica_url') }}" required>
        </div>
        <div class="mb-3">
          <label for="preservica_user" class="form-label fw-bold">
            Username <span class="badge bg-danger ms-1">Required</span>
          </label>
          <input type="text" class="form-control" id="preservica_user" name="preservica_user"
                 value="{{ old('preservica_user') }}" required>
        </div>
        <div class="mb-3">
          <label for="preservica_pass" class="form-label fw-bold">
            Password <span class="badge bg-danger ms-1">Required</span>
          </label>
          <input type="password" class="form-control" id="preservica_pass" name="preservica_pass" required>
        </div>
        <div class="mb-3">
          <label for="collection_ref" class="form-label fw-bold">
            Collection Reference <span class="badge bg-warning text-dark ms-1">Recommended</span>
          </label>
          <input type="text" class="form-control" id="collection_ref" name="collection_ref"
                 placeholder="e.g. /a/b/c or leave blank for root"
                 value="{{ old('collection_ref') }}">
          <div class="form-text">Leave blank to import from the root structural object.</div>
        </div>
        <div class="mb-3">
          <label for="target_repository" class="form-label fw-bold">
            Target Repository <span class="badge bg-warning text-dark ms-1">Recommended</span>
          </label>
          <select class="form-select" id="target_repository" name="target_repository">
            <option value="">-- Select repository --</option>
            @foreach($repositories ?? [] as $repo)
              <option value="{{ $repo->id }}">{{ $repo->authorized_form_of_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="atom-btn-outline-success">
            <i class="fas fa-sync"></i> Start Import
          </button>
          <a href="{{ route('data-migration.index') }}" class="atom-btn-white">Cancel</a>
        </div>
      </form>
    </div>
  </div>
@endsection
