@extends('theme::layouts.1col')

@section('title', 'Audit settings')
@section('body-class', 'admin audit settings')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cog me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Audit settings</h1>
      <span class="small text-muted">Configure audit logging behaviour</span>
    </div>
  </div>

  <form method="POST" action="{{ route('audit.settings') }}">
    @csrf

    {{-- General --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">General</h5>
      </div>
      <div class="card-body">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="audit_enabled" name="settings[audit_enabled]"
                 value="1" @checked($settings['audit_enabled'] === '1')>
          <label class="form-check-label" for="audit_enabled">Enable Audit Logging <span class="badge bg-secondary ms-1">Recommended</span></label>
        </div>
      </div>
    </div>

    {{-- What to Log --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">What to Log</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="audit_views" name="settings[audit_views]"
                     value="1" @checked($settings['audit_views'] === '1')>
              <label class="form-check-label" for="audit_views">Log View Actions <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="audit_searches" name="settings[audit_searches]"
                     value="1" @checked($settings['audit_searches'] === '1')>
              <label class="form-check-label" for="audit_searches">Log Search Queries <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="audit_downloads" name="settings[audit_downloads]"
                     value="1" @checked($settings['audit_downloads'] === '1')>
              <label class="form-check-label" for="audit_downloads">Log File Downloads <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="audit_api_requests" name="settings[audit_api_requests]"
                     value="1" @checked($settings['audit_api_requests'] === '1')>
              <label class="form-check-label" for="audit_api_requests">Log API Requests <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="audit_authentication" name="settings[audit_authentication]"
                     value="1" @checked($settings['audit_authentication'] === '1')>
              <label class="form-check-label" for="audit_authentication">Log Authentication Events <span class="badge bg-secondary ms-1">Recommended</span></label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="audit_sensitive_access" name="settings[audit_sensitive_access]"
                     value="1" @checked($settings['audit_sensitive_access'] === '1')>
              <label class="form-check-label" for="audit_sensitive_access">Log Classified Access <span class="badge bg-secondary ms-1">Recommended</span></label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Privacy Settings --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">Privacy Settings</h5>
      </div>
      <div class="card-body">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="audit_mask_sensitive" name="settings[audit_mask_sensitive]"
                 value="1" @checked($settings['audit_mask_sensitive'] === '1')>
          <label class="form-check-label" for="audit_mask_sensitive">Mask Sensitive Data <span class="badge bg-secondary ms-1">Recommended</span></label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="audit_ip_anonymize" name="settings[audit_ip_anonymize]"
                 value="1" @checked($settings['audit_ip_anonymize'] === '1')>
          <label class="form-check-label" for="audit_ip_anonymize">Anonymize IP Addresses (POPIA) <span class="badge bg-secondary ms-1">Recommended</span></label>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn atom-btn-outline-success">
        <i class="fas fa-save me-1"></i> Save Settings
      </button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white">
        <i class="fas fa-arrow-left me-1"></i> Back to Settings
      </a>
    </div>
  </form>
@endsection
