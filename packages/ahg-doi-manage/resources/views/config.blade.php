@extends('theme::layouts.1col')

@section('title', 'DOI Configuration')
@section('body-class', 'admin doi config')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cog me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">DOI Configuration</h1>
      <span class="small text-muted">DataCite Integration Settings</span>
    </div>
    <div class="ms-auto">
      <a href="{{ route('doi.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
      </a>
    </div>
  </div>

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

  <form action="{{ route('doi.configSave') }}" method="POST">
    @csrf

    <div class="card mb-4">
      <div class="card-header fw-bold">DataCite Connection</div>
      <div class="card-body">
        <div class="row mb-3">
          <label for="datacite_prefix" class="col-sm-3 col-form-label">DataCite Prefix</label>
          <div class="col-sm-9">
            <input type="text" class="form-control" id="datacite_prefix" name="datacite_prefix"
                   value="{{ old('datacite_prefix', $settings['datacite_prefix'] ?? '') }}"
                   placeholder="e.g. 10.12345">
            <div class="form-text">Your DataCite DOI prefix (e.g. 10.12345).</div>
          </div>
        </div>

        <div class="row mb-3">
          <label for="datacite_repository_id" class="col-sm-3 col-form-label">Repository ID</label>
          <div class="col-sm-9">
            <input type="text" class="form-control" id="datacite_repository_id" name="datacite_repository_id"
                   value="{{ old('datacite_repository_id', $settings['datacite_repository_id'] ?? '') }}"
                   placeholder="e.g. XXXX.XXXXX">
            <div class="form-text">Your DataCite repository ID.</div>
          </div>
        </div>

        <div class="row mb-3">
          <label for="datacite_password" class="col-sm-3 col-form-label">Password</label>
          <div class="col-sm-9">
            <input type="password" class="form-control" id="datacite_password" name="datacite_password"
                   value="{{ old('datacite_password', $settings['datacite_password'] ?? '') }}">
            <div class="form-text">Your DataCite repository password.</div>
          </div>
        </div>

        <div class="row mb-3">
          <label for="datacite_url" class="col-sm-3 col-form-label">DataCite URL</label>
          <div class="col-sm-9">
            <input type="url" class="form-control" id="datacite_url" name="datacite_url"
                   value="{{ old('datacite_url', $settings['datacite_url'] ?? '') }}"
                   placeholder="https://api.datacite.org">
            <div class="form-text">DataCite API endpoint URL.</div>
          </div>
        </div>

        <div class="row mb-3">
          <label for="datacite_environment" class="col-sm-3 col-form-label">Environment</label>
          <div class="col-sm-9">
            <select class="form-select" id="datacite_environment" name="datacite_environment">
              <option value="test" {{ (old('datacite_environment', $settings['datacite_environment'] ?? '') === 'test') ? 'selected' : '' }}>
                Test
              </option>
              <option value="production" {{ (old('datacite_environment', $settings['datacite_environment'] ?? '') === 'production') ? 'selected' : '' }}>
                Production
              </option>
            </select>
            <div class="form-text">Select test for the DataCite test API, production for live.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header fw-bold">Defaults</div>
      <div class="card-body">
        <div class="row mb-3">
          <label for="auto_mint" class="col-sm-3 col-form-label">Auto-mint DOIs</label>
          <div class="col-sm-9">
            <select class="form-select" id="auto_mint" name="auto_mint">
              <option value="0" {{ (old('auto_mint', $settings['auto_mint'] ?? '0') === '0') ? 'selected' : '' }}>
                No
              </option>
              <option value="1" {{ (old('auto_mint', $settings['auto_mint'] ?? '0') === '1') ? 'selected' : '' }}>
                Yes
              </option>
            </select>
            <div class="form-text">Automatically mint DOIs when records are published.</div>
          </div>
        </div>

        <div class="row mb-3">
          <label for="default_publisher" class="col-sm-3 col-form-label">Default Publisher</label>
          <div class="col-sm-9">
            <input type="text" class="form-control" id="default_publisher" name="default_publisher"
                   value="{{ old('default_publisher', $settings['default_publisher'] ?? '') }}"
                   placeholder="e.g. My Archive">
            <div class="form-text">Default publisher name for DataCite metadata.</div>
          </div>
        </div>

        <div class="row mb-3">
          <label for="default_resource_type" class="col-sm-3 col-form-label">Default Resource Type</label>
          <div class="col-sm-9">
            <input type="text" class="form-control" id="default_resource_type" name="default_resource_type"
                   value="{{ old('default_resource_type', $settings['default_resource_type'] ?? '') }}"
                   placeholder="e.g. Dataset">
            <div class="form-text">Default resource type for DataCite metadata (e.g. Dataset, Collection, Text).</div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end mb-4">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Save Configuration
      </button>
    </div>
  </form>
@endsection
