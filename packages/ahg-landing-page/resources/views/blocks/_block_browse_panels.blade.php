{{-- Block: Browse Panels (migrated from ahgLandingPagePlugin) --}}
@php
$panels = $data ?? $config['panels'] ?? [];
$title = $config['title'] ?? '';
$style = $config['style'] ?? 'list';
$columns = $config['columns'] ?? 1;
$showCounts = $config['show_counts'] ?? true;
$colClass = 'col-md-' . (12 / max(1, (int)$columns));
@endphp
<div class="browse-panels-container border rounded">
  @if (!empty($title))
    <div class="bg-light border-bottom px-3 py-2">
      <h5 class="mb-0 fw-bold">{{ e($title) }}</h5>
    </div>
  @endif

  @if ($style === 'cards')
    <div class="p-3">
      <div class="row g-3">
        @foreach ($panels as $panel)
          <div class="{{ $colClass }}">
            <a href="{{ e($panel['url'] ?? '#') }}"
               class="card h-100 text-decoration-none border-0 shadow-sm hover-lift">
              <div class="card-body text-center py-4">
                @if (!empty($panel['icon']))
                  <i class="{{ str_starts_with($panel['icon'] ?? '', 'fa-') ? 'fas ' . $panel['icon'] : 'bi bi-' . ($panel['icon'] ?? 'folder') }} display-4 text-primary mb-3"></i>
                @endif
                <h5 class="card-title mb-2">{{ e($panel['label'] ?? $panel['title'] ?? '') }}</h5>
                @if ($showCounts && isset($panel['count']))
                  <p class="card-text text-muted mb-0">{{ number_format($panel['count']) }} records</p>
                @endif
              </div>
            </a>
          </div>
        @endforeach
      </div>
    </div>
  @else
    <div class="browse-panels-list">
      @foreach ($panels as $panel)
        <a href="{{ e($panel['url'] ?? '#') }}"
           class="d-block text-decoration-none py-2 px-3 border-bottom browse-panel-item">
          {{ e($panel['label'] ?? $panel['title'] ?? '') }}
          @if ($showCounts && isset($panel['count']))
            <span class="text-muted">({{ number_format($panel['count']) }})</span>
          @endif
        </a>
      @endforeach
    </div>
  @endif
</div>

<style>
.browse-panel-item {
  color: #176442;
  transition: background-color 0.15s ease;
}
.browse-panel-item:hover {
  background-color: #f8f9fa;
  color: #134e32;
}
.browse-panels-list a:last-child {
  border-bottom: none !important;
}
</style>
