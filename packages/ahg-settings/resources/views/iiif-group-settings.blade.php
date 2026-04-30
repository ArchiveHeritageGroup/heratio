{{--
  IIIF Viewer — image viewer and annotation settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('iiif')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'IIIF Viewer')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-images me-2"></i>IIIF Viewer</h1>
<p class="text-muted">IIIF image viewer and annotation settings</p>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.iiif') }}">
    @csrf

    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-images me-2"></i>IIIF Image Viewer</h5>
      </div>
      <div class="card-body">
        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Enable IIIF') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="iiif_enabled"
                     name="settings[iiif_enabled]" value="true"
                     {{ ($settings['iiif_enabled'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="iiif_enabled">{{ __('Enable IIIF viewer') }}</label>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="iiif_viewer">{{ __('Viewer Library') }}</label>
          <div class="col-sm-9">
            <select class="form-select" id="iiif_viewer" name="settings[iiif_viewer]">
              <option value="openseadragon" {{ ($settings['iiif_viewer'] ?? 'openseadragon') === 'openseadragon' ? 'selected' : '' }}>{{ __('OpenSeadragon') }}</option>
              <option value="mirador" {{ ($settings['iiif_viewer'] ?? 'openseadragon') === 'mirador' ? 'selected' : '' }}>{{ __('Mirador') }}</option>
              <option value="leaflet" {{ ($settings['iiif_viewer'] ?? 'openseadragon') === 'leaflet' ? 'selected' : '' }}>{{ __('Leaflet-IIIF') }}</option>
            </select>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="iiif_server_url">{{ __('IIIF Server URL') }}</label>
          <div class="col-sm-9">
            <input type="url" class="form-control" id="iiif_server_url" name="settings[iiif_server_url]"
                   value="{{ e($settings['iiif_server_url'] ?? '') }}" placeholder="{{ __('https://iiif.example.com') }}">
            <div class="form-text">External IIIF server URL (leave blank to use built-in)</div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Show Navigator') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="iiif_show_navigator"
                     name="settings[iiif_show_navigator]" value="true"
                     {{ ($settings['iiif_show_navigator'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="iiif_show_navigator">{{ __('Show mini-map navigator') }}</label>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Enable Rotation') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="iiif_show_rotation"
                     name="settings[iiif_show_rotation]" value="true"
                     {{ ($settings['iiif_show_rotation'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="iiif_show_rotation">{{ __('Allow image rotation') }}</label>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Enable Fullscreen') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="iiif_show_fullscreen"
                     name="settings[iiif_show_fullscreen]" value="true"
                     {{ ($settings['iiif_show_fullscreen'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="iiif_show_fullscreen">{{ __('Allow fullscreen mode') }}</label>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="iiif_max_zoom">{{ __('Max Zoom Level') }}</label>
          <div class="col-sm-9">
            <input type="number" class="form-control" id="iiif_max_zoom" name="settings[iiif_max_zoom]"
                   value="{{ $settings['iiif_max_zoom'] ?? 10 }}" min="1" max="20" style="max-width:200px">
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="iiif_default_zoom">{{ __('Default Zoom') }}</label>
          <div class="col-sm-9">
            <input type="number" class="form-control" id="iiif_default_zoom" name="settings[iiif_default_zoom]"
                   value="{{ $settings['iiif_default_zoom'] ?? 1 }}" min="0" max="10" style="max-width:200px">
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Enable Annotations') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="iiif_enable_annotations"
                     name="settings[iiif_enable_annotations]" value="true"
                     {{ ($settings['iiif_enable_annotations'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="iiif_enable_annotations">{{ __('Enable IIIF annotations (W3C model)') }}</label>
            </div>
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
