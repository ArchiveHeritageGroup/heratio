{{-- Block: Holdings List (migrated from ahgLandingPagePlugin) --}}
@php
$items = $data ?? [];
$title = $config['title'] ?? 'Our Holdings';
$showLevel = $config['show_level'] ?? true;
$showDates = $config['show_dates'] ?? true;
$showExtent = $config['show_extent'] ?? false;
$showHits = $config['show_hits'] ?? false;
@endphp
@if (!empty($title))
  <h2 class="h5 mb-3">{{ e($title) }}</h2>
@endif
@if (empty($items))
  <p class="text-muted">No holdings available.</p>
@else
  <ul class="list-group list-group-flush">
    @foreach ($items as $item)
      <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
        <a href="{{ route('informationobject.show', ['slug' => $item->slug]) }}" class="text-decoration-none text-truncate">
          {{ e($item->title ?? $item->slug) }}
        </a>
        @if ($showHits && isset($item->hits))
          <small class="text-muted text-nowrap ms-2">{{ number_format($item->hits) }} visits</small>
        @endif
      </li>
    @endforeach
  </ul>
@endif
