{{--
  Media Player - playback and display settings
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
<p class="text-muted">{{ __('Media player behaviour and display options') }}</p>
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
            @php
              // #106: five-tier dispatch. Treat legacy 'basic' / 'enhanced'
              // values as 'heratio' for the dropdown's "selected" state.
              $__cur = $settings['media_player_type'] ?? 'heratio';
              if ($__cur === 'basic' || $__cur === 'enhanced') $__cur = 'heratio';
            @endphp
            <select class="form-select" id="media_player_type" name="settings[media_player_type]">
              <option value="heratio"          {{ $__cur === 'heratio'         ? 'selected' : '' }}>{{ __('Heratio (Recommended)') }}</option>
              <option value="heratio-minimal"  {{ $__cur === 'heratio-minimal' ? 'selected' : '' }}>{{ __('Heratio-minimal') }}</option>
              <option value="plyr"             {{ $__cur === 'plyr'            ? 'selected' : '' }}>{{ __('Plyr') }}</option>
              <option value="videojs"          {{ $__cur === 'videojs'         ? 'selected' : '' }}>{{ __('Video.js') }}</option>
              <option value="native"           {{ $__cur === 'native'          ? 'selected' : '' }}>{{ __('Native HTML5') }}</option>
            </select>
            <div class="form-text">
              {{ __('Heratio: branded rich UI on every audio + video surface. Heratio-minimal: small native UI for low-bandwidth or accessibility-first installs (also rendered automatically when the rich player JS errors out). Plyr / Video.js: third-party chrome. Native: bare browser controls.') }}
            </div>
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
            <div class="form-text">{{ __('Note: Most browsers block autoplay with sound') }}</div>
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
            <div class="form-text">{{ __('Default volume level (0-100%)') }}</div>
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

    {{-- heratio#1752-#1755: playback copies made at upload. The codec list is
         what this server's ffmpeg can encode, so nothing unavailable is offered. --}}
    @php
      $__codecs = \AhgCore\Services\MediaDerivativeService::videoCodecs();
      $__audioCodecs = \AhgCore\Services\MediaDerivativeService::audioCodecs();
      $__vc = $settings['media_video_codec'] ?? 'h264';
      $__ac = $settings['media_audio_codec'] ?? 'mp3';
      $__vh = (int) ($settings['media_video_height'] ?? 480);
      $__codecLabels = ['h264' => __('H.264 (plays in every browser)'), 'h265' => __('H.265 / HEVC (smaller files; not every browser plays it)'), 'av1' => __('AV1 (smallest files; slow to encode, recent browsers only)')];
      $__audioLabels = ['mp3' => __('MP3'), 'aac' => __('AAC (M4A)')];
    @endphp
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-film me-2"></i>{{ __('Playback Copies') }}</h5>
      </div>
      <div class="card-body">
        <p class="text-muted small">{{ __('Every audio and video file gets a copy made for playing in the browser when it is uploaded. These settings apply to files uploaded or reprocessed after you save.') }}</p>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="media_video_codec">{{ __('Video codec') }}</label>
          <div class="col-sm-9">
            <select class="form-select" id="media_video_codec" name="settings[media_video_codec]">
              @foreach(\AhgCore\Services\MediaDerivativeService::VIDEO_CODECS as $__code => $__enc)
                <option value="{{ $__code }}" {{ $__vc === $__code ? 'selected' : '' }} {{ isset($__codecs[$__code]) ? '' : 'disabled' }}>
                  {{ $__codecLabels[$__code] ?? $__code }}{{ isset($__codecs[$__code]) ? '' : ' - '.__('not available on this server') }}
                </option>
              @endforeach
            </select>
            <div class="form-text">{{ __('Only the codecs this server\'s ffmpeg can encode can be chosen. H.264 is the safe choice for public viewing.') }}</div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="media_video_height">{{ __('Video height') }}</label>
          <div class="col-sm-9">
            <select class="form-select" id="media_video_height" name="settings[media_video_height]">
              @foreach(array_keys(\AhgCore\Services\MediaDerivativeService::LADDER) as $__h)
                <option value="{{ $__h }}" {{ $__vh === $__h ? 'selected' : '' }}>{{ $__h }}p</option>
              @endforeach
            </select>
            <div class="form-text">{{ __('The largest frame height of the playback copy. A smaller original keeps its own size; nothing is upscaled.') }}</div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="media_audio_codec">{{ __('Audio codec') }}</label>
          <div class="col-sm-9">
            <select class="form-select" id="media_audio_codec" name="settings[media_audio_codec]">
              @foreach(\AhgCore\Services\MediaDerivativeService::AUDIO_CODECS as $__code => $__c)
                <option value="{{ $__code }}" {{ $__ac === $__code ? 'selected' : '' }} {{ in_array($__code, $__audioCodecs, true) ? '' : 'disabled' }}>
                  {{ $__audioLabels[$__code] ?? $__code }}{{ in_array($__code, $__audioCodecs, true) ? '' : ' - '.__('not available on this server') }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="media_audio_bitrate">{{ __('Audio bitrate (kbps)') }}</label>
          <div class="col-sm-9">
            <input type="number" class="form-control" id="media_audio_bitrate" name="settings[media_audio_bitrate]"
                   min="32" max="512" step="1" value="{{ $settings['media_audio_bitrate'] ?? '128' }}">
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Adaptive streaming (HLS)') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="media_hls_enabled"
                     name="settings[media_hls_enabled]" value="true"
                     {{ ($settings['media_hls_enabled'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="media_hls_enabled">{{ __('Also make an HLS stream with one rendition per quality level') }}</label>
            </div>
            <div class="form-text">{{ __('The player then switches quality to suit the connection, and viewers can pick a quality themselves. Takes more disk space and encoding time.') }}</div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="media_rendition_ladder">{{ __('Quality levels') }}</label>
          <div class="col-sm-9">
            <input type="text" class="form-control" id="media_rendition_ladder" name="settings[media_rendition_ladder]"
                   value="{{ $settings['media_rendition_ladder'] ?? '480,720,1080' }}">
            <div class="form-text">{{ __('Frame heights for the HLS renditions, separated by commas. Choose from :levels. Levels above the original are skipped.', ['levels' => implode(', ', array_keys(\AhgCore\Services\MediaDerivativeService::LADDER))]) }}</div>
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
