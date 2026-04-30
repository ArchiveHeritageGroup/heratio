{{--
  Standalone "Active: <Type>" badge bar for the per-type browse pages
  (/dam/browse, /gallery/browse, /library/browse, /museum/browse, etc.).

  Mirrors the Active-filters bar in `display::_browse_content.blade.php` but
  reduced to a single pill — there's only ever one filter on these pages
  (the type) and "Clear all" goes to /glam/browse (no type filter).

  Usage:
      @include('ahg-display::partials._active-type-bar', ['type' => 'dam'])
--}}
@php
  $__typeConfig = [
      'archive' => ['icon' => 'fa-archive',  'color' => 'success', 'label' => 'Archive'],
      'museum'  => ['icon' => 'fa-landmark', 'color' => 'warning', 'label' => 'Museum'],
      'gallery' => ['icon' => 'fa-palette',  'color' => 'info',    'label' => 'Gallery'],
      'library' => ['icon' => 'fa-book',     'color' => 'primary', 'label' => 'Library'],
      'dam'     => ['icon' => 'fa-images',   'color' => 'danger',  'label' => 'Photo/DAM'],
  ];
  $__t = $__typeConfig[$type ?? ''] ?? null;
@endphp
@if($__t)
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3 p-2 bg-light border rounded">
    <span class="small fw-bold text-muted me-1">
      <i class="fas fa-filter me-1"></i> {{ __('Active:') }}
    </span>
    <a href="{{ route('glam.browse') }}"
       class="badge bg-{{ $__t['color'] }} text-decoration-none"
       title="{{ __('Remove this type filter') }}">
      <i class="fas {{ $__t['icon'] }} me-1"></i> {{ __($__t['label']) }} <i class="fas fa-times ms-1"></i>
    </a>
    <a href="{{ route('glam.browse') }}"
       class="badge bg-outline-secondary border text-dark text-decoration-none ms-1"
       title="{{ __('Clear all filters') }}">
      <i class="fas fa-times-circle me-1"></i> {{ __('Clear all') }}
    </a>
  </div>
@endif
