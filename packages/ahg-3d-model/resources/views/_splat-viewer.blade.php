{{--
  Gaussian Splat Viewer Partial
  Usage: @include('ahg-3d-model::_splat-viewer', ['splatUrl' => '/uploads/...', 'height' => '500px', 'title' => 'My Splat'])
--}}
@props(['splatUrl' => '', 'height' => '500px', 'title' => 'Gaussian Splat'])

@php $viewerId = 'splat-viewer-' . uniqid(); @endphp

<div class="splat-viewer-container" id="{{ $viewerId }}-container">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="badge bg-info">
      <i class="fas fa-cloud me-1"></i>{{ e($title) }} (Gaussian Splat)
    </span>
    <div class="btn-group btn-group-sm">
      <button type="button" class="btn atom-btn-white" id="{{ $viewerId }}-fullscreen" title="Fullscreen">
        <i class="fas fa-expand"></i>
      </button>
    </div>
  </div>

  <div id="{{ $viewerId }}"
       style="width:100%; height:{{ $height }}; background:linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); border-radius:8px; position:relative;">
    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-white" id="{{ $viewerId }}-loading">
      <div class="spinner-border text-primary mb-3" role="status"></div>
      <span>Loading Gaussian Splat...</span>
    </div>
  </div>

  <small class="text-muted mt-2 d-block">
    <i class="fas fa-mouse me-1"></i>Drag to rotate |
    <i class="fas fa-search-plus me-1"></i>Scroll to zoom
  </small>
</div>

{{-- Load GaussianSplats3D library --}}
<script src="/vendor/ahg-3d-model/gaussian-splats-3d.umd.js"></script>

<script>
(function() {
    var container = document.getElementById('{{ $viewerId }}');
    var loading = document.getElementById('{{ $viewerId }}-loading');
    var fullscreenBtn = document.getElementById('{{ $viewerId }}-fullscreen');

    if (!container) return;

    // Initialize Gaussian Splat viewer if library loaded
    if (typeof GaussianSplats3D !== 'undefined') {
        try {
            var viewer = new GaussianSplats3D.Viewer({
                cameraUp: [0, -1, 0],
                initialCameraPosition: [0, 0, 5],
                initialCameraLookAt: [0, 0, 0]
            });
            viewer.addSplatScene('{{ e($splatUrl) }}')
                .then(function() {
                    if (loading) loading.style.display = 'none';
                    viewer.start();
                })
                .catch(function(err) {
                    if (loading) {
                        loading.innerHTML = '<i class="fas fa-exclamation-triangle fa-2x mb-2" style="color:#fff;"></i><br><span style="color:#fff;">Error loading splat model</span>';
                    }
                });
        } catch (e) {
            if (loading) {
                loading.innerHTML = '<i class="fas fa-exclamation-triangle fa-2x mb-2" style="color:#fff;"></i><br><span style="color:#fff;">Splat viewer not available</span>';
            }
        }
    } else {
        if (loading) {
            loading.innerHTML = '<i class="fas fa-exclamation-triangle fa-2x mb-2" style="color:#fff;"></i><br><span style="color:#fff;">Gaussian Splat library not loaded</span>';
        }
    }

    // Fullscreen toggle
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', function() {
            var containerEl = document.getElementById('{{ $viewerId }}-container');
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                containerEl.requestFullscreen();
            }
        });
    }
})();
</script>
