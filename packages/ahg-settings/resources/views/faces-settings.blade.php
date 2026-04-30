{{--
  Face Detection — face detection and recognition settings
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
<p class="text-muted">Face detection and recognition settings</p>
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
            <div class="form-text">Detect faces in uploaded images</div>
          </div>
          <div class="col-md-6">
            <label for="face_backend" class="form-label fw-bold">{{ __('Backend') }}</label>
            <select class="form-select" id="face_backend" name="face_backend">
              @php $curBackend = $settings['face_backend'] ?? 'local'; @endphp
              <option value="local" {{ $curBackend === 'local' ? 'selected' : '' }}>{{ __('Local (OpenCV)') }}</option>
              <option value="aws" {{ $curBackend === 'aws' ? 'selected' : '' }}>{{ __('AWS Rekognition') }}</option>
              <option value="azure" {{ $curBackend === 'azure' ? 'selected' : '' }}>{{ __('Azure Face API') }}</option>
            </select>
          </div>
        </div>
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
