@extends('theme::layouts.1col')

@section('title', 'Image Animation Settings')
@section('body-class', 'settings image-animate')

@php
  function get_anim_setting($settings, $key, $default = '') {
      return isset($settings[$key]) ? ($settings[$key]->setting_value ?? $default) : $default;
  }
  function is_anim_enabled($settings, $key) {
      return isset($settings[$key]) && ($settings[$key]->setting_value ?? '0') === '1';
  }
@endphp

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1><i class="fas fa-film me-2"></i>Image Animation Settings</h1>
      <p class="text-muted mb-0">Ken Burns / 2.5D motion clips generated from still images via local ffmpeg.</p>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <div class="display-6" style="color:var(--ahg-primary);">{{ number_format($stats['total']) }}</div>
          <small class="text-muted">Animations stored</small>
        </div>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.image-animate.settings') }}">
    @csrf

    <div class="card mb-3">
      <div class="card-header fw-bold">Feature toggles</div>
      <div class="card-body">
        <div class="form-check form-switch mb-2">
          <input type="hidden" name="animate_enabled" value="0">
          <input class="form-check-input" type="checkbox" id="animate_enabled" name="animate_enabled" value="1"
                 {{ is_anim_enabled($settings, 'animate_enabled') ? 'checked' : '' }}>
          <label class="form-check-label" for="animate_enabled"><strong>Enable image animation</strong></label>
          <div class="form-text">Master toggle. When off, the user button is hidden and the route returns an error.</div>
        </div>
        <div class="form-check form-switch">
          <input type="hidden" name="animate_user_button" value="0">
          <input class="form-check-input" type="checkbox" id="animate_user_button" name="animate_user_button" value="1"
                 {{ is_anim_enabled($settings, 'animate_user_button') ? 'checked' : '' }}>
          <label class="form-check-label" for="animate_user_button">Show <em>Animate Image</em> button on IO show pages</label>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header fw-bold">Render defaults</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Default motion preset</label>
            <select class="form-select" name="animate_default_motion">
              @foreach(['zoom_in' => 'Zoom in (centred)',
                        'zoom_out' => 'Zoom out (centred)',
                        'pan_lr' => 'Pan left → right',
                        'pan_rl' => 'Pan right → left',
                        'ken_burns_diagonal' => 'Diagonal pan + zoom (classic Ken Burns)'] as $k => $label)
                <option value="{{ $k }}" {{ get_anim_setting($settings, 'animate_default_motion', 'zoom_in') === $k ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Duration (s)</label>
            <input type="number" step="0.5" min="2" max="20" class="form-control" name="animate_duration_secs"
                   value="{{ get_anim_setting($settings, 'animate_duration_secs', '5') }}">
          </div>
          <div class="col-md-2">
            <label class="form-label">FPS</label>
            <input type="number" min="12" max="60" class="form-control" name="animate_fps"
                   value="{{ get_anim_setting($settings, 'animate_fps', '25') }}">
          </div>
          <div class="col-md-2">
            <label class="form-label">Width</label>
            <input type="number" min="320" max="3840" class="form-control" name="animate_width"
                   value="{{ get_anim_setting($settings, 'animate_width', '1280') }}">
          </div>
          <div class="col-md-2">
            <label class="form-label">Height</label>
            <input type="number" min="240" max="2160" class="form-control" name="animate_height"
                   value="{{ get_anim_setting($settings, 'animate_height', '720') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Zoom strength</label>
            <input type="number" step="0.05" min="1.05" max="2.0" class="form-control" name="animate_zoom_strength"
                   value="{{ get_anim_setting($settings, 'animate_zoom_strength', '1.30') }}">
            <div class="form-text">Final zoom factor (1.0 = no zoom, 1.5 = 50% closer).</div>
          </div>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">
      <i class="fas fa-save me-1"></i>Save settings
    </button>
  </form>

  @if($stats['recent']->count())
    <h5 class="mt-4">Recent animations</h5>
    <table class="table table-sm">
      <thead>
        <tr><th>#</th><th>IO</th><th>Motion</th><th>Duration</th><th>Size</th><th>Created</th></tr>
      </thead>
      <tbody>
        @foreach($stats['recent'] as $r)
          <tr>
            <td>{{ $r->id }}</td>
            <td>{{ $r->object_id }}</td>
            <td><code>{{ $r->motion }}</code></td>
            <td>{{ $r->duration_secs }}s</td>
            <td>{{ number_format(($r->file_size ?? 0) / 1024, 0) }} KB</td>
            <td>{{ $r->created_at }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
@endsection
