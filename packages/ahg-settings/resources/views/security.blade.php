@extends('theme::layouts.1col')
@section('title', 'Security Settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Security Settings</h1>
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Incorrect security settings can result in the web UI becoming inaccessible.</div>

    <form method="post" action="{{ route('settings.security') }}">
      @csrf
      <div class="accordion" id="settingsAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSecurity">
              Security settings
            </button>
          </h2>
          <div id="collapseSecurity" class="accordion-collapse collapse show" data-bs-parent="#settingsAccordion">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Limit admin IP addresses <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="limit_admin_ip" class="form-control" value="{{ e($settings['limit_admin_ip']) }}">
                <small class="text-muted">Comma-separated list of IPs allowed to access admin pages. Leave blank to allow all.</small>
              </div>
              <div class="mb-3">
                <label class="form-label">Require SSL for admin <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="require_ssl_admin" class="form-select">
                  <option value="0" {{ $settings['require_ssl_admin'] == '0' ? 'selected' : '' }}>No</option>
                  <option value="1" {{ $settings['require_ssl_admin'] == '1' ? 'selected' : '' }}>Yes</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Require strong passwords <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="require_strong_passwords" class="form-select">
                  <option value="0" {{ $settings['require_strong_passwords'] == '0' ? 'selected' : '' }}>No</option>
                  <option value="1" {{ $settings['require_strong_passwords'] == '1' ? 'selected' : '' }}>Yes</option>
                </select>
                <small class="text-muted">Minimum 8 characters with upper, lower, digit, and symbol</small>
              </div>
            </div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn atom-btn-outline-success mt-3"><i class="fas fa-save me-1"></i>Save</button>
    </form>
  </div>
</div>
@endsection
