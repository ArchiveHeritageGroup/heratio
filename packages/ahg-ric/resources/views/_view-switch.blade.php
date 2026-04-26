{{-- Heratio / RiC View Toggle — only rendered when ahgRicExplorerPlugin is enabled --}}
@if(\AhgCore\Services\MenuService::isPluginEnabled('ahgRicExplorerPlugin'))
  @php
    $viewMode = session('ric_view_mode', config('ric.default_view', 'heratio'));
  @endphp

  <div class="ric-view-switch d-flex align-items-center gap-2 mb-3">
    <span class="small text-muted">View:</span>
    <div class="btn-group btn-group-sm" role="group" aria-label="View mode">
      <form method="POST" action="{{ route('ric.set-view-mode') }}" style="display:inline;">
        @csrf
        <input type="hidden" name="mode" value="heratio">
        <button type="submit" class="btn {{ $viewMode === 'heratio' ? 'btn-primary' : 'btn-outline-secondary' }}">
          <i class="fas fa-list-alt me-1"></i>Heratio
        </button>
      </form>
      <form method="POST" action="{{ route('ric.set-view-mode') }}" style="display:inline;">
        @csrf
        <input type="hidden" name="mode" value="ric">
        <button type="submit" class="btn {{ $viewMode === 'ric' ? 'btn-success' : 'btn-outline-secondary' }}">
          <i class="fas fa-project-diagram me-1"></i>RiC
        </button>
      </form>
    </div>
  </div>
@endif
