{{--
  Standard exhibition nav action bar - the SAME buttons in the SAME order on
  every exhibition page (and per row on browse). Walkthrough opens at the
  building's MAIN room (first in Room order).

  Params:
    $space   - space/row object (needs ->slug, ->building_id)
    $current - active page key to highlight/disable (browse|builder|plan|walkthrough|analytics|forecast|show)
    $compact - true for tight icon-only buttons (browse rows)
--}}
@php
  $current = $current ?? null;
  $compact = $compact ?? false;
  // Walkthrough enters at THIS space (the walkthrough spawns at the entered room
  // via is_current), so launching from a room's page/row starts you in that room.
  $navBtns = [
      ['key' => 'browse',      'url' => route('exhibition-space.browse'),                                  'icon' => 'fa-th-list',          'label' => __('All spaces'),    'style' => 'btn-outline-secondary'],
      ['key' => 'builder',     'url' => route('exhibition-space.builder',     ['slug' => $space->slug]),    'icon' => 'fa-pen-ruler',        'label' => __('Builder'),       'style' => 'btn-outline-primary'],
      ['key' => 'plan',        'url' => route('exhibition-space.plan',        ['slug' => $space->slug]),    'icon' => 'fa-drafting-compass', 'label' => __('Building Plan'), 'style' => 'btn-outline-primary'],
      ['key' => 'wayfinding',  'url' => route('exhibition-space.wayfinding',  ['slug' => $space->slug]),    'icon' => 'fa-map-location-dot', 'label' => __('Wayfinding'),   'style' => 'btn-outline-primary'],
      ['key' => 'walkthrough', 'url' => route('exhibition-space.walkthrough', ['slug' => $space->slug]),    'icon' => 'fa-vr-cardboard',     'label' => __('Walkthrough'),   'style' => 'btn-outline-primary'],
      ['key' => 'accessible',  'url' => route('exhibition-space.accessible-tour', ['slug' => $space->slug]),'icon' => 'fa-universal-access', 'label' => __('Accessible tour'), 'style' => 'btn-outline-primary'],
      ['key' => 'analytics',   'url' => route('exhibition-space.analytics',   ['slug' => $space->slug]),    'icon' => 'fa-chart-line',       'label' => __('Analytics'),     'style' => 'btn-outline-primary'],
      ['key' => 'forecast',    'url' => route('exhibition-space.forecast',    ['slug' => $space->slug]),    'icon' => 'fa-chart-area',       'label' => __('Forecast'),      'style' => 'btn-outline-primary'],
      ['key' => 'show',        'url' => route('exhibition-space.show',        ['slug' => $space->slug]),    'icon' => 'fa-eye',              'label' => __('Open'),          'style' => 'btn-outline-primary'],
  ];
  // heratio#1192 - live virtual openings: admin-only page, so only surface for staff.
  if (auth()->check()) {
      array_splice($navBtns, 7, 0, [[
          'key' => 'openings', 'url' => route('exhibition-space.openings', ['slug' => $space->slug]),
          'icon' => 'fa-calendar-day', 'label' => __('Live openings'), 'style' => 'btn-outline-primary',
      ]]);
  }
@endphp
<div class="d-flex flex-wrap gap-1 exhibition-nav-actions {{ $compact ? 'justify-content-end' : '' }}">
  @foreach($navBtns as $b)
    @if($current === $b['key'])
      <span class="btn btn-sm {{ $b['style'] }} active disabled" aria-current="page"><i class="fas {{ $b['icon'] }} @if(!$compact) me-1 @endif"></i>@unless($compact){{ $b['label'] }}@endunless</span>
    @else
      <a href="{{ $b['url'] }}" class="btn btn-sm {{ $b['style'] }}" title="{{ $b['label'] }}"><i class="fas {{ $b['icon'] }} @if(!$compact) me-1 @endif"></i>@unless($compact){{ $b['label'] }}@endunless</a>
    @endif
  @endforeach
</div>
