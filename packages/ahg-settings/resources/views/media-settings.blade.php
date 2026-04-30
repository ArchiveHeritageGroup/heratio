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
<h1><i class="fas fa-play-circle me-2"></i>{{ __('Media Player') }}</h1>
<p class="text-muted">Media player behaviour and display options</p>
@endsection

@section('content')
  @if(session('notice') || session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') ?? session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.media') }}">
    @csrf

    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-play-circle me-2"></i>{{ __('Media Player Configuration') }}</h5>
      </div>
      <div class="card-body">
        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="media_player_type">{{ __('Player Type') }}</label>
          <div class="col-sm-9">
            <select class="form-select" id="media_player_type" name="settings[media_player_type]">
              <option value="basic" {{ ($settings['media_player_type'] ?? 'enhanced') === 'basic' ? 'selected' : '' }}>{{ __('Basic HTML5 Player') }}</option>
              <option value="enhanced" {{ ($settings['media_player_type'] ?? 'enhanced') === 'enhanced' ? 'selected' : '' }}>{{ __('Enhanced Player (Recommended)') }}</option>
            </select>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Auto-play') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="media_autoplay"
                     name="settings[media_autoplay]" value="true"
                     {{ ($settings['media_autoplay'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="media_autoplay">{{ __('Auto-play media on load') }}</label>
            </div>
            <div class="form-text">Note: Most browsers block autoplay with sound</div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Show Controls') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="media_show_controls"
                     name="settings[media_show_controls]" value="true"
                     {{ ($settings['media_show_controls'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="media_show_controls">{{ __('Display player controls') }}</label>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Loop Playback') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="media_loop"
                     name="settings[media_loop]" value="true"
                     {{ ($settings['media_loop'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="media_loop">{{ __('Loop media automatically') }}</label>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="media_default_volume">{{ __('Default Volume') }}</label>
          <div class="col-sm-9">
            <input type="range" class="form-range" id="media_default_volume" name="settings[media_default_volume]"
                   min="0" max="1" step="0.1" value="{{ $settings['media_default_volume'] ?? '0.8' }}">
            <div class="form-text">Default volume level (0-100%)</div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Show Download') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="media_show_download"
                     name="settings[media_show_download]" value="true"
                     {{ ($settings['media_show_download'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="media_show_download">{{ __('Show download button') }}</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>{{ __('Save') }}
      </button>
    </div>
  </form>
@endsection
