@extends('theme::layouts.2col')
@section('title', 'Uploads settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>{{ __('Uploads settings') }}</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.uploads') }}">
      @csrf

      <div class="accordion mb-3" id="uploadsAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="settings-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#settings-collapse" aria-expanded="false" aria-controls="settings-collapse">
              {{ __('Upload settings') }}
            </button>
          </h2>
          <div id="settings-collapse" class="accordion-collapse collapse" aria-labelledby="settings-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Total space available for uploads <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="settings[upload_quota]" class="form-control" value="{{ e($settings['upload_quota']) }}">
                <small class="text-muted">{{ __('A value of "-1" allows unlimited uploads. This value is typically set via the server configuration file.') }}</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Repository upload limits <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[enable_repository_quotas]" id="repo_quota_disabled" value="0" {{ $settings['enable_repository_quotas'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="repo_quota_disabled">Disabled <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[enable_repository_quotas]" id="repo_quota_enabled" value="1" {{ $settings['enable_repository_quotas'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="repo_quota_enabled">Enabled <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  </div>
                </div>
                <small class="text-muted">When enabled, an "Upload limit" meter is displayed for authenticated users on the repository view page, and administrators can limit the disk space each repository is allowed for digital object uploads</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Default repository upload limit (GB) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="number" name="settings[repository_quota]" class="form-control" value="{{ e($settings['repository_quota']) }}" min="-1" step="0.01">
                <small class="text-muted">{{ __('Default digital object upload limit for a new repository. A value of "0" (zero) disables file upload. A value of "-1" allows unlimited uploads') }}</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Upload multi-page files as multiple descriptions <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[explode_multipage_files]" id="explode_no" value="0" {{ $settings['explode_multipage_files'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="explode_no">No <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[explode_multipage_files]" id="explode_yes" value="1" {{ $settings['explode_multipage_files'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="explode_yes">Yes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <section class="actions mb-3">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>

    </form>
@endsection
