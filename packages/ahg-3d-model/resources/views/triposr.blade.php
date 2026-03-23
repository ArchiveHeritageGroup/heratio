@extends('theme::layouts.1col')

@section('title', 'TripoSR Settings')
@section('body-class', 'settings triposr')

@php
  function getTripoSetting3d($settings, $key, $default = '') {
      return isset($settings[$key]) ? ($settings[$key]->setting_value ?? $default) : $default;
  }
  function isTripoSettingEnabled3d($settings, $key) {
      return isset($settings[$key]) && ($settings[$key]->setting_value ?? '0') === '1';
  }
  $isOnline = ($health['status'] ?? '') === 'ok';
@endphp

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1><i class="fas fa-magic me-2"></i>TripoSR Settings</h1>
      <p class="text-muted mb-0">Generate 3D models from 2D images using AI</p>
    </div>
    <div>
      <a href="{{ route('admin.3d-models.settings') }}" class="btn atom-btn-white me-2">
        <i class="fas fa-arrow-left me-1"></i>Back to 3D Settings
      </a>
      <a href="{{ route('admin.3d-models.index') }}" class="btn atom-btn-white">
        <i class="fas fa-cubes me-1"></i>View Models
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Status Banner --}}
  <div class="alert {{ $isOnline ? 'alert-success' : 'alert-danger' }} mb-4">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <i class="fas {{ $isOnline ? 'fa-check-circle' : 'fa-times-circle' }} me-2"></i>
        <strong>TripoSR Service:</strong>
        @if($isOnline)
          Online - {{ $health['device'] ?? 'unknown' }} ({{ $health['mode'] ?? 'local' }} mode)
          @if($health['cuda_available'] ?? false)
            <span class="badge bg-success ms-2">CUDA Available</span>
          @endif
          @if($health['model_loaded'] ?? false)
            <span class="badge bg-info ms-2">Model Loaded</span>
          @endif
        @else
          Offline - {{ $health['message'] ?? 'Service not responding' }}
        @endif
      </div>
      @if($isOnline && ($health['remote_configured'] ?? false))
        <span class="badge bg-primary">Remote GPU Configured</span>
      @endif
    </div>
  </div>

  {{-- Statistics --}}
  <div class="row">
    <div class="col-md-3">
      <div class="card text-center mb-4">
        <div class="card-body">
          <div class="display-6" style="color:var(--ahg-primary);">{{ number_format($stats['total_jobs'] ?? 0) }}</div>
          <small class="text-muted">Total Jobs</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center mb-4">
        <div class="card-body">
          <div class="display-6 text-success">{{ number_format($stats['completed'] ?? 0) }}</div>
          <small class="text-muted">Completed</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center mb-4">
        <div class="card-body">
          <div class="display-6 text-danger">{{ number_format($stats['failed'] ?? 0) }}</div>
          <small class="text-muted">Failed</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center mb-4">
        <div class="card-body">
          <div class="display-6 text-warning">{{ number_format($stats['pending'] ?? 0) }}</div>
          <small class="text-muted">Pending</small>
        </div>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.3d-models.triposr') }}">
    @csrf
    <div class="row">
      <div class="col-md-8">
        {{-- Service Configuration --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-cog me-2"></i>Service Configuration
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="triposr_enabled" name="triposr_enabled" value="1"
                         {{ isTripoSettingEnabled3d($settings, 'triposr_enabled') ? 'checked' : '' }}>
                  <label class="form-check-label" for="triposr_enabled">
                    <strong>Enable TripoSR</strong> <span class="badge bg-secondary ms-1">Optional</span>
                    <br><small class="text-muted">Allow image-to-3D generation</small>
                  </label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Local API URL <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" class="form-control" name="triposr_api_url"
                         value="{{ e(getTripoSetting3d($settings, 'triposr_api_url', 'http://127.0.0.1:5050')) }}">
                  <div class="form-text">Default: http://127.0.0.1:5050</div>
                </div>
              </div>
            </div>

            <hr>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Processing Mode <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select class="form-select" name="triposr_mode" id="triposr_mode">
                    <option value="local" {{ getTripoSetting3d($settings, 'triposr_mode', 'local') == 'local' ? 'selected' : '' }}>
                      Local Processing (CPU/GPU)
                    </option>
                    <option value="remote" {{ getTripoSetting3d($settings, 'triposr_mode') == 'remote' ? 'selected' : '' }}>
                      Remote GPU Server
                    </option>
                  </select>
                  <div class="form-text">Local auto-detects GPU. Remote sends to GPU server.</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Timeout (seconds) <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" class="form-control" name="triposr_timeout"
                         value="{{ getTripoSetting3d($settings, 'triposr_timeout', '300') }}"
                         min="60" max="600">
                  <div class="form-text">Max wait time for generation (60-600s)</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Remote GPU Configuration --}}
        <div class="card mb-4" id="remote_config_card"
             style="display: {{ getTripoSetting3d($settings, 'triposr_mode') == 'remote' ? 'block' : 'none' }};">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-server me-2"></i>Remote GPU Server
          </div>
          <div class="card-body">
            <div class="alert alert-info small mb-3">
              <i class="fas fa-info-circle me-1"></i>
              Configure a remote server with GPU for faster processing. The system will automatically fall back to local CPU processing if the remote server is unavailable.
            </div>
            <div class="row">
              <div class="col-md-8">
                <div class="mb-3">
                  <label class="form-label">Remote Server URL <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="url" class="form-control" name="triposr_remote_url"
                         value="{{ e(getTripoSetting3d($settings, 'triposr_remote_url')) }}"
                         placeholder="https://gpu-server.example.com:5050">
                  <div class="form-text">Full URL including port (e.g., https://gpu.example.com:5050)</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">API Key <span class="badge bg-secondary ms-1">Optional</span></label>
                  @php $apiKey = getTripoSetting3d($settings, 'triposr_remote_api_key'); @endphp
                  <input type="password" class="form-control" name="triposr_remote_api_key"
                         value="{{ $apiKey ? '***' : '' }}" placeholder="Optional API key">
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Generation Defaults --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-sliders-h me-2"></i>Default Generation Options
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4">
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="triposr_remove_bg" name="triposr_remove_bg" value="1"
                         {{ getTripoSetting3d($settings, 'triposr_remove_bg', '1') === '1' ? 'checked' : '' }}>
                  <label class="form-check-label" for="triposr_remove_bg">
                    <strong>Remove Background</strong> <span class="badge bg-secondary ms-1">Optional</span>
                    <br><small class="text-muted">Auto-remove image background</small>
                  </label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Foreground Ratio <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" class="form-control" name="triposr_foreground_ratio"
                         value="{{ getTripoSetting3d($settings, 'triposr_foreground_ratio', '0.85') }}"
                         min="0.5" max="1" step="0.05">
                  <div class="form-text">Object size ratio (0.5-1.0)</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Mesh Resolution <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select class="form-select" name="triposr_mc_resolution">
                    <option value="128" {{ getTripoSetting3d($settings, 'triposr_mc_resolution', '256') == '128' ? 'selected' : '' }}>
                      128 - Fast (lower quality)
                    </option>
                    <option value="256" {{ getTripoSetting3d($settings, 'triposr_mc_resolution', '256') == '256' ? 'selected' : '' }}>
                      256 - Balanced (recommended)
                    </option>
                    <option value="512" {{ getTripoSetting3d($settings, 'triposr_mc_resolution', '256') == '512' ? 'selected' : '' }}>
                      512 - High Quality (slower)
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="triposr_bake_texture" name="triposr_bake_texture" value="1"
                     {{ isTripoSettingEnabled3d($settings, 'triposr_bake_texture') ? 'checked' : '' }}>
              <label class="form-check-label" for="triposr_bake_texture">
                <strong>Bake Texture</strong> <span class="badge bg-secondary ms-1">Optional</span>
                <br><small class="text-muted">Export as OBJ with texture map instead of GLB with vertex colors</small>
              </label>
            </div>
          </div>
        </div>
      </div>

      {{-- Sidebar --}}
      <div class="col-md-4">
        {{-- Recent Jobs --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-history me-2"></i>Recent Jobs
          </div>
          <div class="card-body p-0">
            @if(count($recentJobs ?? []) === 0)
              <div class="p-3 text-muted text-center">No jobs yet</div>
            @else
              <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                  <thead>
                    <tr><th>ID</th><th>Status</th><th>Time</th></tr>
                  </thead>
                  <tbody>
                    @foreach($recentJobs as $job)
                      <tr>
                        <td>{{ $job->id }}</td>
                        <td>
                          @php
                            $statusClass = match($job->status ?? 'pending') {
                                'completed' => 'success',
                                'failed' => 'danger',
                                'processing' => 'warning',
                                default => 'secondary'
                            };
                          @endphp
                          <span class="badge bg-{{ $statusClass }}">{{ $job->status ?? 'pending' }}</span>
                        </td>
                        <td>{{ !empty($job->processing_time) ? round($job->processing_time, 1) . 's' : '-' }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif
          </div>
        </div>

        {{-- CLI Commands --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-terminal me-2"></i>CLI Commands
          </div>
          <div class="card-body">
            <div class="small">
              <p class="mb-2"><strong>Generate model:</strong></p>
              <code class="d-block mb-3">php artisan triposr:generate --image=/path/image.jpg</code>

              <p class="mb-2"><strong>With object link:</strong></p>
              <code class="d-block mb-3">php artisan triposr:generate --image=/path/image.jpg --object-id=123 --import</code>

              <p class="mb-2"><strong>Check health:</strong></p>
              <code class="d-block mb-3">php artisan triposr:health</code>

              <p class="mb-2"><strong>View statistics:</strong></p>
              <code class="d-block">php artisan triposr:generate --stats</code>
            </div>
          </div>
        </div>

        {{-- Quick Links --}}
        <div class="card">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-link me-2"></i>Resources
          </div>
          <div class="list-group list-group-flush">
            <a href="https://github.com/VAST-AI-Research/TripoSR" target="_blank" class="list-group-item list-group-item-action">
              <i class="fas fa-external-link-alt me-2"></i>TripoSR GitHub
            </a>
            <a href="https://huggingface.co/stabilityai/TripoSR" target="_blank" class="list-group-item list-group-item-action">
              <i class="fas fa-external-link-alt me-2"></i>Model on HuggingFace
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modeSelect = document.getElementById('triposr_mode');
    var remoteCard = document.getElementById('remote_config_card');
    if (modeSelect && remoteCard) {
        modeSelect.addEventListener('change', function() {
            remoteCard.style.display = this.value === 'remote' ? 'block' : 'none';
        });
    }
});
</script>
@endpush
