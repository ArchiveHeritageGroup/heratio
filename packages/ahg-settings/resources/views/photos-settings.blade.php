{{--
  Condition Photos — photo upload and thumbnail settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('photos')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Condition Photos')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-camera me-2"></i>Condition Photos</h1>
<p class="text-muted">Condition photo thumbnails and EXIF settings</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.photos') }}">
    @csrf

    {{-- Photo Upload Settings --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-camera me-2"></i>Photo Upload Settings</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="photo_upload_path">Upload Path</label>
            <input type="text" class="form-control" id="photo_upload_path" name="settings[photo_upload_path]"
                   value="{{ e($settings['photo_upload_path'] ?? config('heratio.uploads_path', base_path('uploads')) . '/condition_photos') }}">
            <div class="form-text">Absolute path for condition photo storage</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="photo_max_upload_size">Max Upload Size</label>
            <select class="form-select" id="photo_max_upload_size" name="settings[photo_max_upload_size]">
              <option value="5242880" {{ ($settings['photo_max_upload_size'] ?? '10485760') == '5242880' ? 'selected' : '' }}>5 MB</option>
              <option value="10485760" {{ ($settings['photo_max_upload_size'] ?? '10485760') == '10485760' ? 'selected' : '' }}>10 MB</option>
              <option value="20971520" {{ ($settings['photo_max_upload_size'] ?? '10485760') == '20971520' ? 'selected' : '' }}>20 MB</option>
              <option value="52428800" {{ ($settings['photo_max_upload_size'] ?? '10485760') == '52428800' ? 'selected' : '' }}>50 MB</option>
            </select>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="photo_create_thumbnails"
                     name="settings[photo_create_thumbnails]" value="true"
                     {{ ($settings['photo_create_thumbnails'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="photo_create_thumbnails">Create Thumbnails</label>
            </div>
            <div class="form-text">Auto-create thumbnails on upload</div>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-12">
            <label class="form-label">Thumbnail Sizes</label>
            <div class="row">
              <div class="col-4">
                <label class="small">Small</label>
                <input type="number" class="form-control" name="settings[photo_thumbnail_small]"
                       value="{{ $settings['photo_thumbnail_small'] ?? 150 }}" min="50" max="300">
              </div>
              <div class="col-4">
                <label class="small">Medium</label>
                <input type="number" class="form-control" name="settings[photo_thumbnail_medium]"
                       value="{{ $settings['photo_thumbnail_medium'] ?? 300 }}" min="100" max="600">
              </div>
              <div class="col-4">
                <label class="small">Large</label>
                <input type="number" class="form-control" name="settings[photo_thumbnail_large]"
                       value="{{ $settings['photo_thumbnail_large'] ?? 600 }}" min="300" max="1200">
              </div>
            </div>
            <div class="form-text">Maximum dimension in pixels</div>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <label class="form-label" for="photo_jpeg_quality">JPEG Quality</label>
            <input type="range" class="form-range" id="photo_jpeg_quality" name="settings[photo_jpeg_quality]"
                   min="60" max="100" value="{{ $settings['photo_jpeg_quality'] ?? 85 }}">
            <div class="form-text">Quality for JPEG thumbnails (60-100)</div>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="photo_extract_exif"
                     name="settings[photo_extract_exif]" value="true"
                     {{ ($settings['photo_extract_exif'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="photo_extract_exif">Extract EXIF</label>
            </div>
            <div class="form-text">Extract camera info from EXIF data</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="photo_auto_rotate"
                     name="settings[photo_auto_rotate]" value="true"
                     {{ ($settings['photo_auto_rotate'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="photo_auto_rotate">Auto-rotate</label>
            </div>
            <div class="form-text">Auto-rotate based on EXIF orientation</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Save --}}
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
