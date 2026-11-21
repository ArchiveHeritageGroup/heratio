{{--
  Face Detection - face detection and recognition settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('faces')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Face Detection')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-user-circle me-2"></i>{{ __('Face Detection') }}</h1>
<p class="text-muted">{{ __('Face detection and recognition settings') }}</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.faces') }}">
    @csrf

    {{-- Card 1: Face Detection --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>{{ __('Face Detection') }}</h5>
      </div>
      <div class="card-body">
        <div class="alert alert-warning mb-3">
          <i class="fas fa-exclamation-triangle me-1"></i> {{ __('Experimental feature.') }}
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="face_enabled"
                     name="face_enabled" value="1"
                     {{ ($settings['face_enabled'] ?? 'false') === 'true' || ($settings['face_enabled'] ?? '') === '1' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="face_enabled">{{ __('Enable Face Detection') }}</label>
            </div>
            <div class="form-text">{{ __('Detect faces in uploaded images and video. Off switches face processing off entirely: no detection, naming, matching or search.') }}</div>
          </div>
          <div class="col-md-6">
            <label for="face_backend" class="form-label fw-bold">{{ __('Backend') }}</label>
            <select class="form-select" id="face_backend" name="face_backend">
              @php $curBackend = $settings['face_backend'] ?? 'gateway'; @endphp
              <option value="gateway" {{ $curBackend === 'gateway' ? 'selected' : '' }}>{{ __('AI gateway (recommended)') }}</option>
              <option value="azure" {{ $curBackend === 'azure' ? 'selected' : '' }}>{{ __('Azure Face API') }}</option>
              <option value="noop" {{ $curBackend === 'noop' ? 'selected' : '' }}>{{ __('None (log only)') }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 2: Face recognition (heratio#1764) --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-search me-2"></i>{{ __('Face recognition') }}</h5>
      </div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="face_gateway_path" class="form-label fw-bold">{{ __('Gateway face route') }}</label>
            <input class="form-control" id="face_gateway_path" name="face_gateway_path" maxlength="255"
                   value="{{ $settings['face_gateway_path'] ?? '/ai/v1/vision/faces' }}">
            <div class="form-text">{{ __('Path on the AI gateway host. Default /ai/v1/vision/faces.') }}</div>
          </div>
          <div class="col-md-6">
            <label for="face_video_interval" class="form-label fw-bold">{{ __('Video frame interval (seconds)') }}</label>
            <input class="form-control" type="number" min="1" max="3600" step="1" id="face_video_interval" name="face_video_interval"
                   value="{{ $settings['face_video_interval'] ?? '10' }}">
            <div class="form-text">{{ __('One frame is checked for faces every this many seconds of video.') }}</div>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="face_match_threshold" class="form-label fw-bold">{{ __('Match threshold (approx.)') }}</label>
            <input class="form-control" type="number" min="0.1" max="1" step="0.01" id="face_match_threshold" name="face_match_threshold"
                   value="{{ $settings['face_match_threshold'] ?? '0.6' }}">
            <div class="form-text">{{ __('Cosine similarity at or above which a face is suggested as the same person. 0.6 is a starting point; the right value depends on the face model.') }}</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="face_search_public" name="face_search_public" value="1"
                     {{ ($settings['face_search_public'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="face_search_public">{{ __('Public person pages') }}</label>
            </div>
            <div class="form-text">{{ __('Off (default): face search is for staff only. Faces are personal (biometric) information. On: visitors can open a person page and see confirmed appearances on published records; possible matches and search by photo stay staff-only.') }}</div>
          </div>
        </div>
        <a href="{{ route('faces.index') }}">{{ __('Open face recognition') }}</a>
      </div>
    </div>

    {{-- Save --}}
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
