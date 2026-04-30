@extends('theme::layouts.1col')
@section('title', 'Export Records - Data Migration')
@section('body-class', 'admin data-migration export')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-export me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Export Records') }}</h1>
      <span class="small text-muted">{{ __('Data Migration') }}</span>
    </div>
  </div>
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('data-migration.index') }}">Data Migration</a></li>
      <li class="breadcrumb-item active">Export</li>
    </ol>
  </nav>
  <div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-cog"></i> {{ __('Export Options') }}</h5>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('data-migration.export') }}">
        @csrf
        <div class="mb-3">
          <label for="export_type" class="form-label fw-bold">
            Export Format <span class="badge bg-danger ms-1">{{ __('Required') }}</span>
          </label>
          <select class="form-select" id="export_type" name="export_type" required>
            <option value="">-- Select format --</option>
            <option value="csv">CSV</option>
            <option value="xml">{{ __('EAD XML') }}</option>
            <option value="dc">{{ __('Dublin Core XML') }}</option>
            <option value="mods">{{ __('MODS XML') }}</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="target_type" class="form-label fw-bold">
            Record Type <span class="badge bg-danger ms-1">{{ __('Required') }}</span>
          </label>
          <select class="form-select" id="target_type" name="target_type" required>
            <option value="">-- Select record type --</option>
            <option value="informationObject">{{ __('Information Objects') }}</option>
            <option value="actor">{{ __('Authority Records') }}</option>
            <option value="repository">{{ __('Repositories') }}</option>
            <option value="accession">{{ __('Accessions') }}</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="repository_id" class="form-label fw-bold">
            Limit to Repository <span class="badge bg-warning text-dark ms-1">{{ __('Recommended') }}</span>
          </label>
          <select class="form-select" id="repository_id" name="repository_id">
            <option value="">-- All repositories --</option>
            @foreach($repositories ?? [] as $repo)
              <option value="{{ $repo->id }}">{{ $repo->authorized_form_of_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label for="date_from" class="form-label fw-bold">
            Created From <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span>
          </label>
          <input type="date" class="form-control" id="date_from" name="date_from"
                 value="{{ old('date_from') }}">
        </div>
        <div class="mb-3">
          <label for="date_to" class="form-label fw-bold">
            Created To <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span>
          </label>
          <input type="date" class="form-control" id="date_to" name="date_to"
                 value="{{ old('date_to') }}">
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="{{ $btnSave ?? 'atom-btn-outline-success' }}">
            <i class="fas fa-download"></i> {{ __('Export') }}
          </button>
          <a href="{{ route('data-migration.index') }}" class="atom-btn-white">Cancel</a>
        </div>
      </form>
    </div>
  </div>
@endsection
