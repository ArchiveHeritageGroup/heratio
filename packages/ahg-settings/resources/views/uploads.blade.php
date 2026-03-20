@extends('theme::layouts.1col')
@section('title', 'Uploads settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Uploads settings</h1>

    <form method="post" action="{{ route('settings.uploads') }}">
      @csrf

      <div class="accordion mb-3" id="uploadsAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="settings-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#settings-collapse" aria-expanded="false" aria-controls="settings-collapse">
              Upload settings
            </button>
          </h2>
          <div id="settings-collapse" class="accordion-collapse collapse" aria-labelledby="settings-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Total space available for uploads</label>
                <input type="text" name="settings[upload_quota]" class="form-control" value="{{ e($settings['upload_quota']) }}">
                <small class="text-muted">A value of "-1" allows unlimited uploads. This value is typically set via the server configuration file.</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Repository upload limits</label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[enable_repository_quotas]" id="repo_quota_disabled" value="0" {{ $settings['enable_repository_quotas'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="repo_quota_disabled">Disabled</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[enable_repository_quotas]" id="repo_quota_enabled" value="1" {{ $settings['enable_repository_quotas'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="repo_quota_enabled">Enabled</label>
                  </div>
                </div>
                <small class="text-muted">When enabled, an "Upload limit" meter is displayed for authenticated users on the repository view page, and administrators can limit the disk space each repository is allowed for digital object uploads</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Default repository upload limit (GB)</label>
                <input type="number" name="settings[repository_quota]" class="form-control" value="{{ e($settings['repository_quota']) }}" min="-1" step="0.01">
                <small class="text-muted">Default digital object upload limit for a new repository. A value of "0" (zero) disables file upload. A value of "-1" allows unlimited uploads</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Upload multi-page files as multiple descriptions</label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[explode_multipage_files]" id="explode_no" value="0" {{ $settings['explode_multipage_files'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="explode_no">No</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[explode_multipage_files]" id="explode_yes" value="1" {{ $settings['explode_multipage_files'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="explode_yes">Yes</label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="actions mb-3" style="background:#495057 !important;border-radius:.375rem;padding:1rem;display:block;">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </div>

    </form>
  </div>
</div>
@endsection
