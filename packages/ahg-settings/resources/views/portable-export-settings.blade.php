{{--
  Portable Export — standalone portable catalogue viewer settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('portable_export')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Portable Export')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-compact-disc me-2"></i>Portable Export</h1>
<p class="text-muted">Standalone portable catalogue viewer for offline access</p>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.portable_export') }}">
    @csrf

    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-compact-disc me-2"></i>Portable Export Configuration</h5>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">Configure defaults for standalone portable catalogue exports (CD/USB/ZIP distribution).</p>

        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="portable_export_enabled"
                     name="settings[portable_export_enabled]" value="true"
                     {{ ($settings['portable_export_enabled'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="portable_export_enabled"><strong>Enable Portable Export</strong></label>
            </div>
            <div class="form-text">Allow creation of offline portable catalogues from Admin UI.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Retention (days)</label>
            <input type="number" class="form-control" name="settings[portable_export_retention_days]"
                   value="{{ $settings['portable_export_retention_days'] ?? '30' }}" min="1" max="365">
            <div class="form-text">Completed exports are auto-deleted after this many days.</div>
          </div>
        </div>

        <hr>
        <h6 class="mb-3">Default Content Options</h6>
        <div class="row g-3">
          <div class="col-md-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="portable_export_include_objects"
                     name="settings[portable_export_include_objects]" value="true"
                     {{ ($settings['portable_export_include_objects'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="portable_export_include_objects">Digital Objects</label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="portable_export_include_thumbnails"
                     name="settings[portable_export_include_thumbnails]" value="true"
                     {{ ($settings['portable_export_include_thumbnails'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="portable_export_include_thumbnails">Thumbnails</label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="portable_export_include_references"
                     name="settings[portable_export_include_references]" value="true"
                     {{ ($settings['portable_export_include_references'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="portable_export_include_references">Reference Images</label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="portable_export_include_masters"
                     name="settings[portable_export_include_masters]" value="true"
                     {{ ($settings['portable_export_include_masters'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="portable_export_include_masters">Master Files</label>
            </div>
          </div>
        </div>

        <hr>
        <h6 class="mb-3">Default Settings</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Default Viewer Mode</label>
            <select class="form-select" name="settings[portable_export_default_mode]">
              <option value="read_only" {{ ($settings['portable_export_default_mode'] ?? 'read_only') === 'read_only' ? 'selected' : '' }}>Read Only</option>
              <option value="editable" {{ ($settings['portable_export_default_mode'] ?? '') === 'editable' ? 'selected' : '' }}>Editable</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Default Language</label>
            <select class="form-select" name="settings[portable_export_default_culture]">
              <option value="en" {{ ($settings['portable_export_default_culture'] ?? 'en') === 'en' ? 'selected' : '' }}>English</option>
              <option value="fr" {{ ($settings['portable_export_default_culture'] ?? '') === 'fr' ? 'selected' : '' }}>French</option>
              <option value="af" {{ ($settings['portable_export_default_culture'] ?? '') === 'af' ? 'selected' : '' }}>Afrikaans</option>
              <option value="pt" {{ ($settings['portable_export_default_culture'] ?? '') === 'pt' ? 'selected' : '' }}>Portuguese</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Max Export Size (MB)</label>
            <input type="number" class="form-control" name="settings[portable_export_max_size_mb]"
                   value="{{ $settings['portable_export_max_size_mb'] ?? '2048' }}" min="100" max="10240">
          </div>
        </div>

        <hr>
        <h6 class="mb-3">Integration</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="portable_export_description_button"
                     name="settings[portable_export_description_button]" value="true"
                     {{ ($settings['portable_export_description_button'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="portable_export_description_button">Show export button on description pages</label>
            </div>
            <div class="form-text">Adds "Portable Viewer" to the Export section on archival description pages.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="portable_export_clipboard_button"
                     name="settings[portable_export_clipboard_button]" value="true"
                     {{ ($settings['portable_export_clipboard_button'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="portable_export_clipboard_button">Show export button on clipboard page</label>
            </div>
            <div class="form-text">Adds "Portable Catalogue" option to the clipboard export page.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Settings
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>Save
      </button>
    </div>
  </form>
@endsection
