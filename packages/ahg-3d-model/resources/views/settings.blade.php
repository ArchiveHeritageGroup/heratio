@extends('theme::layouts.1col')

@section('title', '3D Viewer Settings')
@section('body-class', 'settings model3d')

@php
  // Helper to read settings from keyed object collection
  function getSetting3d($settings, $key, $default = '') {
      return isset($settings[$key]) ? ($settings[$key]->setting_value ?? $default) : $default;
  }
  function isSettingEnabled3d($settings, $key) {
      return isset($settings[$key]) && ($settings[$key]->setting_value ?? '0') === '1';
  }
@endphp

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1><i class="fas fa-cog me-2"></i>3D Viewer Settings</h1>
      <p class="text-muted mb-0">Configure global settings for 3D model viewing</p>
    </div>
    <a href="{{ route('admin.3d-models.index') }}" class="btn atom-btn-white">
      <i class="fas fa-cubes me-1"></i>View All Models
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Statistics --}}
  <div class="row mb-4">
    <div class="col-md-2">
      <div class="card text-center">
        <div class="card-body">
          <div class="display-6" style="color:var(--ahg-primary);">{{ number_format($stats['total_models'] ?? 0) }}</div>
          <small class="text-muted">Total Models</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card text-center">
        <div class="card-body">
          <div class="display-6 text-success">{{ number_format($stats['ar_enabled_models'] ?? 0) }}</div>
          <small class="text-muted">AR Enabled</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card text-center">
        <div class="card-body">
          <div class="display-6 text-info">{{ number_format($stats['total_hotspots'] ?? 0) }}</div>
          <small class="text-muted">Hotspots</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card text-center">
        <div class="card-body">
          <div class="display-6 text-secondary">{{ number_format($stats['total_views'] ?? 0) }}</div>
          <small class="text-muted">Views</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card text-center">
        <div class="card-body">
          <div class="display-6 text-warning">{{ number_format($stats['total_ar_views'] ?? 0) }}</div>
          <small class="text-muted">AR Views</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card text-center">
        <div class="card-body">
          <div class="display-6 text-dark">{{ number_format(($stats['storage_used'] ?? 0) / 1048576, 1) }}</div>
          <small class="text-muted">MB Used</small>
        </div>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.3d-models.settings') }}">
    @csrf
    <div class="row">
      <div class="col-md-8">
        {{-- Viewer Settings --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-eye me-2"></i>Viewer Settings
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Default Viewer <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select class="form-select" name="default_viewer">
                    <option value="model-viewer" {{ getSetting3d($settings, 'default_viewer') == 'model-viewer' ? 'selected' : '' }}>
                      Model Viewer (Google WebXR)
                    </option>
                    <option value="threejs" {{ getSetting3d($settings, 'default_viewer') == 'threejs' ? 'selected' : '' }}>
                      Three.js
                    </option>
                  </select>
                  <div class="form-text">Model Viewer provides AR support on mobile devices</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Default Background Color <span class="badge bg-secondary ms-1">Optional</span></label>
                  <div class="input-group">
                    <input type="color" class="form-control form-control-color" id="bg_picker"
                           value="{{ getSetting3d($settings, 'default_background', '#f5f5f5') }}"
                           onchange="document.getElementById('default_background').value=this.value;">
                    <input type="text" class="form-control" id="default_background" name="default_background"
                           value="{{ getSetting3d($settings, 'default_background', '#f5f5f5') }}">
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Default Exposure <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" class="form-control" name="default_exposure"
                         value="{{ getSetting3d($settings, 'default_exposure', '1.0') }}"
                         min="0" max="2" step="0.1">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Default Shadow Intensity <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" class="form-control" name="default_shadow_intensity"
                         value="{{ getSetting3d($settings, 'default_shadow_intensity', '1.0') }}"
                         min="0" max="2" step="0.1">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Rotation Speed (deg/sec) <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" class="form-control" name="rotation_speed"
                         value="{{ getSetting3d($settings, 'rotation_speed', '30') }}"
                         min="0" max="360">
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" id="enable_auto_rotate" name="enable_auto_rotate" value="1"
                         {{ isSettingEnabled3d($settings, 'enable_auto_rotate') ? 'checked' : '' }}>
                  <label class="form-check-label" for="enable_auto_rotate">Enable Auto-Rotate by Default <span class="badge bg-secondary ms-1">Optional</span></label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" id="enable_fullscreen" name="enable_fullscreen" value="1"
                         {{ isSettingEnabled3d($settings, 'enable_fullscreen') ? 'checked' : '' }}>
                  <label class="form-check-label" for="enable_fullscreen">Enable Fullscreen Button <span class="badge bg-secondary ms-1">Optional</span></label>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- AR Settings --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-mobile-alt me-2"></i>Augmented Reality
          </div>
          <div class="card-body">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="enable_ar" name="enable_ar" value="1"
                     {{ isSettingEnabled3d($settings, 'enable_ar') ? 'checked' : '' }}>
              <label class="form-check-label" for="enable_ar">
                <strong>Enable AR Viewing</strong> <span class="badge bg-secondary ms-1">Optional</span>
                <br><small class="text-muted">Allow users to view 3D models in augmented reality on supported devices (iOS Safari, Chrome for Android)</small>
              </label>
            </div>
            <div class="alert alert-info small mb-0">
              <i class="fas fa-info-circle me-1"></i>
              AR requires HTTPS and is supported on:
              <ul class="mb-0 mt-1">
                <li>iOS 12+ (Safari with Quick Look)</li>
                <li>Android 7+ (Chrome with Scene Viewer)</li>
                <li>WebXR-capable browsers</li>
              </ul>
            </div>
          </div>
        </div>

        {{-- Upload Settings --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-upload me-2"></i>Upload Settings
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Maximum File Size (MB) <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" class="form-control" name="max_file_size_mb"
                         value="{{ getSetting3d($settings, 'max_file_size_mb', '100') }}"
                         min="1" max="500">
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Allowed Formats <span class="badge bg-secondary ms-1">Optional</span></label>
              @php
                $allowedFormats = json_decode(getSetting3d($settings, 'allowed_formats', '["glb","gltf","usdz"]'), true) ?: [];
                $allFormats = ['glb', 'gltf', 'usdz', 'obj', 'stl', 'ply'];
              @endphp
              <div class="row">
                @foreach($allFormats as $fmt)
                  <div class="col-md-3">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="allowed_formats[]"
                             value="{{ $fmt }}" id="fmt_{{ $fmt }}"
                             {{ in_array($fmt, $allowedFormats) ? 'checked' : '' }}>
                      <label class="form-check-label" for="fmt_{{ $fmt }}">{{ strtoupper($fmt) }}</label>
                    </div>
                  </div>
                @endforeach
              </div>
              <div class="form-text">GLB and GLTF are recommended for web viewing</div>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="enable_download" name="enable_download" value="1"
                     {{ isSettingEnabled3d($settings, 'enable_download') ? 'checked' : '' }}>
              <label class="form-check-label" for="enable_download">
                Allow Model Downloads <span class="badge bg-secondary ms-1">Optional</span>
                <br><small class="text-muted">Let users download 3D model files</small>
              </label>
            </div>
          </div>
        </div>

        {{-- Annotations --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-map-marker-alt me-2"></i>Annotations & Hotspots
          </div>
          <div class="card-body">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="enable_annotations" name="enable_annotations" value="1"
                     {{ isSettingEnabled3d($settings, 'enable_annotations') ? 'checked' : '' }}>
              <label class="form-check-label" for="enable_annotations">
                <strong>Enable 3D Hotspots</strong> <span class="badge bg-secondary ms-1">Optional</span>
                <br><small class="text-muted">Allow adding clickable annotation points on 3D models</small>
              </label>
            </div>
          </div>
        </div>

        {{-- Watermark --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-stamp me-2"></i>Watermark
          </div>
          <div class="card-body">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="watermark_enabled" name="watermark_enabled" value="1"
                     {{ isSettingEnabled3d($settings, 'watermark_enabled') ? 'checked' : '' }}>
              <label class="form-check-label" for="watermark_enabled"><strong>Enable Watermark</strong> <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
            <div class="mb-3">
              <label class="form-label">Watermark Text <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" name="watermark_text"
                     value="{{ e(getSetting3d($settings, 'watermark_text', 'The Archive and Heritage Group')) }}">
            </div>
          </div>
        </div>

        {{-- TripoSR --}}
        @php
          $triposrOnline = ($triposrHealth['status'] ?? '') === 'ok';
        @endphp
        <div class="card mb-4">
          <div class="card-header fw-semibold d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff;">
            <span><i class="fas fa-magic me-2"></i>TripoSR - Image to 3D</span>
            <span class="badge {{ $triposrOnline ? 'bg-success' : 'bg-danger' }}">
              {{ $triposrOnline ? 'Online' : 'Offline' }}
            </span>
          </div>
          <div class="card-body">
            <p class="text-muted small mb-3">
              Generate 3D models from 2D images using AI. Supports local CPU processing or remote GPU server.
            </p>

            @if($triposrOnline)
              <div class="alert alert-success small mb-3">
                <i class="fas fa-check-circle me-1"></i>
                <strong>API Status:</strong> Online |
                <strong>Device:</strong> {{ $triposrHealth['device'] ?? 'unknown' }} |
                <strong>Mode:</strong> {{ $triposrHealth['mode'] ?? 'unknown' }}
                @if($triposrHealth['cuda_available'] ?? false)
                  | <strong>CUDA:</strong> Available
                @endif
              </div>
            @else
              <div class="alert alert-warning small mb-3">
                <i class="fas fa-exclamation-triangle me-1"></i>
                TripoSR service not responding. Check if the service is running.
              </div>
            @endif

            <div class="row">
              <div class="col-md-6">
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="triposr_enabled" name="triposr_enabled" value="1"
                         {{ isSettingEnabled3d($settings, 'triposr_enabled') ? 'checked' : '' }}>
                  <label class="form-check-label" for="triposr_enabled"><strong>Enable TripoSR</strong> <span class="badge bg-secondary ms-1">Optional</span></label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Processing Mode <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select class="form-select" name="triposr_mode" id="triposr_mode">
                    <option value="local" {{ getSetting3d($settings, 'triposr_mode', 'local') == 'local' ? 'selected' : '' }}>
                      Local (CPU/GPU)
                    </option>
                    <option value="remote" {{ getSetting3d($settings, 'triposr_mode') == 'remote' ? 'selected' : '' }}>
                      Remote GPU Server
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <div id="triposr_remote_config" style="display: {{ getSetting3d($settings, 'triposr_mode') == 'remote' ? 'block' : 'none' }};">
              <div class="alert alert-info small mb-3">
                <i class="fas fa-info-circle me-1"></i>
                Configure remote GPU server for faster processing. The local server will auto-fallback if remote fails.
              </div>
              <div class="row">
                <div class="col-md-8">
                  <div class="mb-3">
                    <label class="form-label">Remote GPU Server URL <span class="badge bg-secondary ms-1">Optional</span></label>
                    <input type="url" class="form-control" name="triposr_remote_url"
                           value="{{ e(getSetting3d($settings, 'triposr_remote_url')) }}"
                           placeholder="https://gpu-server.example.com:5050">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">API Key <span class="badge bg-secondary ms-1">Optional</span></label>
                    @php $apiKey = getSetting3d($settings, 'triposr_remote_api_key'); @endphp
                    <input type="password" class="form-control" name="triposr_remote_api_key"
                           value="{{ $apiKey ? '***' : '' }}" placeholder="API key">
                  </div>
                </div>
              </div>
            </div>

            <hr>
            <h6 class="text-muted mb-3">Default Generation Options</h6>

            <div class="row">
              <div class="col-md-4">
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="triposr_remove_bg" name="triposr_remove_bg" value="1"
                         {{ getSetting3d($settings, 'triposr_remove_bg', '1') === '1' ? 'checked' : '' }}>
                  <label class="form-check-label" for="triposr_remove_bg">Remove Background <span class="badge bg-secondary ms-1">Optional</span></label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Foreground Ratio <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" class="form-control" name="triposr_foreground_ratio"
                         value="{{ getSetting3d($settings, 'triposr_foreground_ratio', '0.85') }}"
                         min="0.5" max="1" step="0.05">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Resolution <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select class="form-select" name="triposr_mc_resolution">
                    <option value="128" {{ getSetting3d($settings, 'triposr_mc_resolution', '256') == '128' ? 'selected' : '' }}>128 (Fast)</option>
                    <option value="256" {{ getSetting3d($settings, 'triposr_mc_resolution', '256') == '256' ? 'selected' : '' }}>256 (Balanced)</option>
                    <option value="512" {{ getSetting3d($settings, 'triposr_mc_resolution', '256') == '512' ? 'selected' : '' }}>512 (High Quality)</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4">
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="triposr_bake_texture" name="triposr_bake_texture" value="1"
                         {{ isSettingEnabled3d($settings, 'triposr_bake_texture') ? 'checked' : '' }}>
                  <label class="form-check-label" for="triposr_bake_texture">Bake Texture (OBJ output) <span class="badge bg-secondary ms-1">Optional</span></label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Timeout (seconds) <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" class="form-control" name="triposr_timeout"
                         value="{{ getSetting3d($settings, 'triposr_timeout', '300') }}"
                         min="60" max="600">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Local API URL <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" class="form-control" name="triposr_api_url"
                         value="{{ e(getSetting3d($settings, 'triposr_api_url', 'http://127.0.0.1:5050')) }}">
                </div>
              </div>
            </div>

            <div class="text-muted small">
              <strong>CLI:</strong> <code>php artisan triposr:generate --image=/path/to/image.jpg</code>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        {{-- Format Distribution --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-chart-pie me-2"></i>Format Distribution
          </div>
          <div class="card-body">
            @if(empty($formatStats))
              <p class="text-muted mb-0">No models uploaded yet.</p>
            @else
              <canvas id="formatChart" height="200"></canvas>
            @endif
          </div>
        </div>

        {{-- Quick Links --}}
        <div class="card">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-link me-2"></i>Quick Links
          </div>
          <div class="list-group list-group-flush">
            <a href="{{ route('admin.3d-models.index') }}" class="list-group-item list-group-item-action">
              <i class="fas fa-cubes me-2"></i>View All 3D Models
            </a>
            <a href="{{ route('admin.3d-models.triposr') }}" class="list-group-item list-group-item-action">
              <i class="fas fa-magic me-2"></i>TripoSR Settings
            </a>
            <a href="https://modelviewer.dev/" target="_blank" class="list-group-item list-group-item-action">
              <i class="fas fa-external-link-alt me-2"></i>Model Viewer Documentation
            </a>
            <a href="https://iiif.io/api/3d/" target="_blank" class="list-group-item list-group-item-action">
              <i class="fas fa-external-link-alt me-2"></i>IIIF 3D Specification
            </a>
          </div>
        </div>
      </div>
    </div>

    <hr>

    <div class="d-flex justify-content-end">
      <button type="submit" class="btn atom-btn-white">
        <i class="fas fa-save me-1"></i>Save Settings
      </button>
    </div>
  </form>
@endsection

@push('scripts')
@if(!empty($formatStats))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('formatChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: {!! json_encode(array_map('strtoupper', array_keys($formatStats))) !!},
            datasets: [{
                data: {!! json_encode(array_values($formatStats)) !!},
                backgroundColor: ['#1a73e8', '#34a853', '#fbbc04', '#ea4335', '#673ab7', '#00bcd4', '#ff5722']
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
</script>
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modeSelect = document.getElementById('triposr_mode');
    var remoteConfig = document.getElementById('triposr_remote_config');
    if (modeSelect && remoteConfig) {
        modeSelect.addEventListener('change', function() {
            remoteConfig.style.display = this.value === 'remote' ? 'block' : 'none';
        });
    }
});
</script>
@endpush
