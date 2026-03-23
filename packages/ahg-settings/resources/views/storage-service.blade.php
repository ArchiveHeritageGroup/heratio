@extends('theme::layouts.1col')
@section('title', 'Storage service')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Storage service</h1>

    <form method="post" action="{{ route('settings.storage-service') }}">
      @csrf

      <div class="accordion mb-3" id="storageAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="storage-config-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#storage-config-collapse" aria-expanded="false" aria-controls="storage-config-collapse">
              Storage service configuration
            </button>
          </h2>
          <div id="storage-config-collapse" class="accordion-collapse collapse" aria-labelledby="storage-config-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Enable storage service <span class="badge bg-secondary ms-1">Optional</span></label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[storage_service_enabled]" id="ss_enabled_no" value="0" {{ ($settings['storage_service_enabled'] ?? '') != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="ss_enabled_no">No <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[storage_service_enabled]" id="ss_enabled_yes" value="1" {{ ($settings['storage_service_enabled'] ?? '') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="ss_enabled_yes">Yes <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Storage service type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="settings[storage_service_type]" class="form-select">
                  <option value="">-- Select --</option>
                  <option value="archivematica" {{ ($settings['storage_service_type'] ?? '') == 'archivematica' ? 'selected' : '' }}>Archivematica</option>
                  <option value="preservica" {{ ($settings['storage_service_type'] ?? '') == 'preservica' ? 'selected' : '' }}>Preservica</option>
                  <option value="dspace" {{ ($settings['storage_service_type'] ?? '') == 'dspace' ? 'selected' : '' }}>DSpace</option>
                  <option value="custom" {{ ($settings['storage_service_type'] ?? '') == 'custom' ? 'selected' : '' }}>Custom</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Storage service URL <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="url" name="settings[storage_service_url]" class="form-control" value="{{ e($settings['storage_service_url']) }}" placeholder="https://storage.example.com">
                <small class="text-muted">Full URL of the storage service API endpoint</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Storage service username <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="settings[storage_service_username]" class="form-control" value="{{ e($settings['storage_service_username']) }}">
              </div>

              <div class="mb-3">
                <label class="form-label">Storage service API key <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="password" name="settings[storage_service_api_key]" class="form-control" value="{{ e($settings['storage_service_api_key']) }}">
                <small class="text-muted">The API key or password used to authenticate with the storage service</small>
              </div>

              <div class="d-flex gap-2">
                <button type="button" class="btn atom-btn-white" id="btnTestConnection">
                  <i class="fas fa-plug me-1"></i>Test Connection
                </button>
                <span id="connectionResult" class="align-self-center small"></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <section class="actions mb-3">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>

    </form>
  </div>
</div>
@endsection
