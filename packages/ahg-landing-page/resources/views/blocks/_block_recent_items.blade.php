{{-- Block: Recent Items (migrated from ahgLandingPagePlugin) --}}
@php
$items = $data ?? [];
$title = $config['title'] ?? 'Recent Additions';
$showDate = $config['show_date'] ?? true;
$showThumbnail = $config['show_thumbnail'] ?? true;
$layout = $config['layout'] ?? 'scroll';
$columns = $config['columns'] ?? 3;
$scrollable = ($layout === 'scroll') || ($config['scrollable'] ?? false);
$colClass = 'col-md-' . (12 / $columns);
@endphp

@if (!empty($title))
  <h2 class="h4 mb-4">{{ e($title) }}</h2>
@endif

@if (empty($items))
  <p class="text-muted">No recent items found.</p>
@elseif ($scrollable)
  <div class="recent-items-scroll d-flex overflow-auto pb-3" style="gap: 1rem; scroll-snap-type: x mandatory;">
    @foreach ($items as $item)
      @php
      $itemSlug = is_object($item) ? ($item->slug ?? '') : ($item['slug'] ?? '');
      $itemTitle = is_object($item) ? ($item->title ?? $itemSlug) : ($item['title'] ?? $itemSlug);
      $itemDate = is_object($item) ? ($item->created_at ?? '') : ($item['created_at'] ?? '');
      $thumbnailUrl = is_object($item) ? ($item->thumbnail_url ?? null) : ($item['thumbnail_url'] ?? null);
      @endphp
      <div class="flex-shrink-0" style="width: 220px; scroll-snap-align: start;">
        <div class="card h-100 border-0 shadow-sm">
          @if ($showThumbnail)
            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 150px; overflow: hidden;">
              @if ($thumbnailUrl)
                <img src="{{ e($thumbnailUrl) }}"
                     class="w-100 h-100"
                     style="object-fit: cover;"
                     alt="{{ e($itemTitle) }}"
                     onerror="this.parentElement.innerHTML='<div class=\'text-center text-muted\'><i class=\'bi bi-image fs-1\'></i></div>'">
              @else
                <div class="text-center text-muted">
                  <i class="bi bi-file-earmark fs-1"></i>
                </div>
              @endif
            </div>
          @endif
          <div class="card-body p-2">
            <h6 class="card-title mb-1 small">
              <a href="{{ route('informationobject.show', ['slug' => $itemSlug]) }}"
                 class="text-decoration-none stretched-link">
                {{ e($itemTitle) }}
              </a>
            </h6>
            @if ($showDate && !empty($itemDate))
              <small class="text-muted">
                {{ \Carbon\Carbon::parse($itemDate)->format('M j, Y') }}
              </small>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>
@elseif ($layout === 'grid')
  <div class="row g-4">
    @foreach ($items as $item)
      @php
      $itemSlug = is_object($item) ? ($item->slug ?? '') : ($item['slug'] ?? '');
      $itemTitle = is_object($item) ? ($item->title ?? $itemSlug) : ($item['title'] ?? $itemSlug);
      $itemDate = is_object($item) ? ($item->created_at ?? '') : ($item['created_at'] ?? '');
      $thumbnailUrl = is_object($item) ? ($item->thumbnail_url ?? null) : ($item['thumbnail_url'] ?? null);
      @endphp
      <div class="{{ $colClass }}">
        <div class="card h-100 border-0 shadow-sm">
          @if ($showThumbnail)
            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 150px; overflow: hidden;">
              @if ($thumbnailUrl)
                <img src="{{ e($thumbnailUrl) }}"
                     class="w-100 h-100"
                     style="object-fit: cover;"
                     alt="{{ e($itemTitle) }}"
                     onerror="this.parentElement.innerHTML='<div class=\'text-center text-muted\'><i class=\'bi bi-image fs-1\'></i></div>'">
              @else
                <div class="text-center text-muted">
                  <i class="bi bi-file-earmark fs-1"></i>
                </div>
              @endif
            </div>
          @endif
          <div class="card-body">
            <h6 class="card-title mb-1">
              <a href="{{ route('informationobject.show', ['slug' => $itemSlug]) }}"
                 class="text-decoration-none stretched-link">
                {{ e($itemTitle) }}
              </a>
            </h6>
            @if ($showDate && !empty($itemDate))
              <small class="text-muted">
                {{ \Carbon\Carbon::parse($itemDate)->format('M j, Y') }}
              </small>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>
@elseif ($layout === 'list')
  <ul class="list-group list-group-flush">
    @foreach ($items as $item)
      @php
      $itemSlug = is_object($item) ? ($item->slug ?? '') : ($item['slug'] ?? '');
      $itemTitle = is_object($item) ? ($item->title ?? $itemSlug) : ($item['title'] ?? $itemSlug);
      $itemDate = is_object($item) ? ($item->created_at ?? '') : ($item['created_at'] ?? '');
      @endphp
      <li class="list-group-item d-flex justify-content-between align-items-center px-0">
        <a href="{{ route('informationobject.show', ['slug' => $itemSlug]) }}"
           class="text-decoration-none">
          {{ e($itemTitle) }}
        </a>
        @if ($showDate && !empty($itemDate))
          <small class="text-muted">{{ \Carbon\Carbon::parse($itemDate)->format('M j, Y') }}</small>
        @endif
      </li>
    @endforeach
  </ul>
@endif
