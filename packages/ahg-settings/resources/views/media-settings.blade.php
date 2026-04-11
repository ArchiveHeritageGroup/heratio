{{--
  Media Player — playback and display settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('media')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Media Player')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-play-circle me-2"></i>Media Player</h1>
<p class="text-muted">Media player behaviour and display options</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.media') }}">
    @csrf

    {{-- Media Player Configuration --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-play-circle me-2"></i>Media Player Configuration</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="media_player_type">Player Type</label>
            <select class="form-select" id="media_player_type" name="settings[media_player_type]">
              <option value="basic" {{ ($settings['media_player_type'] ?? 'enhanced') === 'basic' ? 'selected' : '' }}>Basic HTML5 Player</option>
              <option value="enhanced" {{ ($settings['media_player_type'] ?? 'enhanced') === 'enhanced' ? 'selected' : '' }}>Enhanced Player (Recommended)</option>
            </select>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="media_autoplay"
                     name="settings[media_autoplay]" value="true"
                     {{ ($settings['media_autoplay'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="media_autoplay">Auto-play</label>
            </div>
            <div class="form-text">Auto-play media on load. Note: Most browsers block autoplay with sound.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="media_show_controls"
                     name="settings[media_show_controls]" value="true"
                     {{ ($settings['media_show_controls'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="media_show_controls">Show Controls</label>
            </div>
            <div class="form-text">Display player controls</div>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="media_loop"
                     name="settings[media_loop]" value="true"
                     {{ ($settings['media_loop'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="media_loop">Loop Playback</label>
            </div>
            <div class="form-text">Loop media automatically</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="media_show_download"
                     name="settings[media_show_download]" value="true"
                     {{ ($settings['media_show_download'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="media_show_download">Show Download</label>
            </div>
            <div class="form-text">Show download button</div>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <label class="form-label" for="media_default_volume">Default Volume</label>
            <input type="range" class="form-range" id="media_default_volume" name="settings[media_default_volume]"
                   min="0" max="1" step="0.1" value="{{ $settings['media_default_volume'] ?? '0.8' }}">
            <div class="form-text">Default volume level (0-100%)</div>
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
