{{-- Heratio / RiC View Toggle --}}
@php
  $viewMode = session('ric_view_mode', config('ric.default_view', 'heratio'));
@endphp

<div class="ric-view-switch d-flex align-items-center gap-2 mb-3">
  <span class="small text-muted">View:</span>
  <div class="btn-group btn-group-sm" role="group" aria-label="View mode">
    <button type="button"
            class="btn {{ $viewMode === 'heratio' ? 'btn-primary' : 'btn-outline-secondary' }}"
            data-view-mode="heratio"
            onclick="switchViewMode('heratio')">
      <i class="fas fa-list-alt me-1"></i>Heratio
    </button>
    <button type="button"
            class="btn {{ $viewMode === 'ric' ? 'btn-success' : 'btn-outline-secondary' }}"
            data-view-mode="ric"
            onclick="switchViewMode('ric')">
      <i class="fas fa-project-diagram me-1"></i>RiC
    </button>
  </div>
</div>

<script>
function switchViewMode(mode) {
  fetch('/ric-api/view-mode', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
    },
    body: JSON.stringify({ mode: mode })
  }).then(function() {
    location.reload();
  });
}
</script>
