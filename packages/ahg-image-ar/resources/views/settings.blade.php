@extends('theme::layouts.1col')

@section('title', 'Image Animation Settings')
@section('body-class', 'settings image-animate')

@php
  function get_ar_setting($settings, $key, $default = '') {
      return isset($settings[$key]) ? ($settings[$key]->setting_value ?? $default) : $default;
  }
  function is_ar_on($settings, $key) {
      return isset($settings[$key]) && ($settings[$key]->setting_value ?? '0') === '1';
  }
@endphp

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1><i class="fas fa-magic me-2"></i>Image Animation Settings</h1>
      <p class="text-muted mb-0">AI image-to-video. Default: Stable Video Diffusion (works on the 8&nbsp;GB card with CPU offload). Swap to CogVideoX/WAN once the 24&nbsp;GB card is in.</p>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- AI server health -------------------------------------------------- --}}
  <div class="card mb-3 border-{{ $health ? 'success' : 'danger' }}">
    <div class="card-header fw-bold">
      <i class="fas fa-heartbeat me-1"></i>AI server health
      <span class="badge bg-{{ $health ? 'success' : 'danger' }} float-end">
        {{ $health ? 'reachable' : 'unreachable' }}
      </span>
    </div>
    <div class="card-body small">
      @if($health)
        <div class="row">
          <div class="col-md-3"><strong>Default model:</strong> <code>{{ $health['default_model'] ?? '—' }}</code></div>
          <div class="col-md-3"><strong>Loaded:</strong> <code>{{ implode(', ', $health['loaded_models'] ?? []) ?: 'none' }}</code></div>
          <div class="col-md-3"><strong>CUDA:</strong> {{ ($health['cuda'] ?? false) ? 'yes' : 'no' }}</div>
          <div class="col-md-3"><strong>Low-VRAM mode:</strong> {{ ($health['low_vram_mode'] ?? false) ? 'yes' : 'no' }}</div>
        </div>
        @if(!empty($health['device']))
          <div class="mt-1">
            <strong>GPU:</strong> {{ $health['device'] }}
            @if(!empty($health['vram_total_gb']))
              &middot; {{ $health['vram_free_gb'] }} GB free / {{ $health['vram_total_gb'] }} GB total
            @endif
          </div>
        @endif
      @else
        <div class="text-danger">
          Could not reach <code>{{ get_ar_setting($settings, 'ar_server_url', 'http://192.168.0.78:5052') }}</code>.<br>
          Check the server is running: <code>sudo systemctl status heratio-video-server</code> on the AI host.
          Install steps in <code>packages/ahg-image-ar/tools/video-server/INSTALL.md</code>.
        </div>
      @endif
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <div class="display-6" style="color:var(--ahg-primary);">{{ number_format($stats['total']) }}</div>
          <small class="text-muted">Animations generated</small>
        </div>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.image-ar.settings') }}">
    @csrf

    <div class="card mb-3">
      <div class="card-header fw-bold">Feature toggles</div>
      <div class="card-body">
        <div class="form-check form-switch mb-2">
          <input type="hidden" name="ar_enabled" value="0">
          <input class="form-check-input" type="checkbox" id="ar_enabled" name="ar_enabled" value="1"
                 {{ is_ar_on($settings, 'ar_enabled') ? 'checked' : '' }}>
          <label class="form-check-label" for="ar_enabled"><strong>Enable image animation</strong></label>
        </div>
        <div class="form-check form-switch">
          <input type="hidden" name="ar_user_button" value="0">
          <input class="form-check-input" type="checkbox" id="ar_user_button" name="ar_user_button" value="1"
                 {{ is_ar_on($settings, 'ar_user_button') ? 'checked' : '' }}>
          <label class="form-check-label" for="ar_user_button">Show <em>Animate image (AI)</em> button on IO show pages</label>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header fw-bold">AI server</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Server URL</label>
            <input type="url" class="form-control" name="ar_server_url"
                   value="{{ get_ar_setting($settings, 'ar_server_url', 'http://192.168.0.78:5052') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Model</label>
            <select class="form-select" name="ar_model">
              @foreach(['svd' => 'SVD (8 GB OK)',
                        'svd-xt' => 'SVD-XT (25 frames; tighter on 8 GB)',
                        'cogvideox-2b' => 'CogVideoX-2B (24 GB; prompt-aware)',
                        'wan-2.1' => 'WAN 2.1 I2V (24 GB; prompt-aware)'] as $k => $label)
                <option value="{{ $k }}" {{ get_ar_setting($settings, 'ar_model', 'svd') === $k ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Request timeout (s)</label>
            <input type="number" class="form-control" min="60" max="3600" name="ar_request_timeout"
                   value="{{ get_ar_setting($settings, 'ar_request_timeout', '900') }}">
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header fw-bold">Generation defaults</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-2">
            <label class="form-label">Frames</label>
            <input type="number" class="form-control" min="8" max="49" name="ar_num_frames"
                   value="{{ get_ar_setting($settings, 'ar_num_frames', '14') }}">
            <div class="form-text">SVD: 14 / 25 · CogVideoX: 49</div>
          </div>
          <div class="col-md-2">
            <label class="form-label">FPS</label>
            <input type="number" class="form-control" min="4" max="30" name="ar_fps"
                   value="{{ get_ar_setting($settings, 'ar_fps', '7') }}">
            <div class="form-text">SVD canonical = 7</div>
          </div>
          <div class="col-md-2">
            <label class="form-label">Motion bucket</label>
            <input type="number" class="form-control" min="1" max="255" name="ar_motion_bucket_id"
                   value="{{ get_ar_setting($settings, 'ar_motion_bucket_id', '127') }}">
            <div class="form-text">SVD only · 1=still · 255=wild</div>
          </div>
          <div class="col-md-2">
            <label class="form-label">Seed</label>
            <input type="number" class="form-control" min="0" name="ar_seed"
                   value="{{ get_ar_setting($settings, 'ar_seed', '0') }}">
            <div class="form-text">0 = random per call</div>
          </div>
          <div class="col-md-12">
            <label class="form-label">Default prompt (CogVideoX/WAN only — SVD ignores it)</label>
            <textarea class="form-control" rows="2" name="ar_default_prompt" placeholder="e.g. cinematic, soft motion, painterly camera">{{ get_ar_setting($settings, 'ar_default_prompt', '') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save settings</button>
  </form>

  @if($stats['recent']->count())
    <h5 class="mt-4">Recent generations</h5>
    <table class="table table-sm">
      <thead><tr><th>#</th><th>IO</th><th>Model</th><th>Prompt</th><th>MP4</th><th>Took</th><th>Created</th></tr></thead>
      <tbody>
        @foreach($stats['recent'] as $r)
          <tr>
            <td>{{ $r->id }}</td>
            <td>{{ $r->object_id }}</td>
            <td><code>{{ $r->ai_model ?: '—' }}</code></td>
            <td class="text-truncate small fst-italic" style="max-width:280px;">{{ $r->ai_prompt ?: '(none)' }}</td>
            <td>{{ number_format(($r->mp4_size ?? 0) / 1024, 0) }} KB</td>
            <td>{{ $r->generation_secs ? (int) $r->generation_secs . 's' : '—' }}</td>
            <td>{{ $r->created_at }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
@endsection
