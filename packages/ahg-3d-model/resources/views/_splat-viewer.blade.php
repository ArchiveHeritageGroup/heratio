{{-- Partial component --}}
@props(['splatUrl' => '', 'height' => '500px', 'title' => 'Gaussian Splat'])
<div class="splat-viewer-container mb-4">
  <div class="card">
    <div class="card-header bg-dark text-white"><i class="fas fa-atom me-2"></i>{{ $title }} <span class="badge bg-info">Gaussian Splat</span></div>
    <div class="card-body p-0" style="height:{{ $height }};background:#1a1a2e;">
      <div class="d-flex align-items-center justify-content-center h-100 text-light text-center">
        <div><i class="fas fa-atom fa-3x mb-2"></i><p>Gaussian Splat Viewer</p></div>
      </div>
    </div>
  </div>
</div>
