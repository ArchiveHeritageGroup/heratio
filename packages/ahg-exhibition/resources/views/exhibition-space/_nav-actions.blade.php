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
  try {
      $___rooms = app(\AhgExhibition\Services\ExhibitionSpaceService::class)->buildingRoomsOrdered($space);
  } catch (\Throwable $e) {
      $___rooms = [];
  }
  $mainSlug = (!empty($___rooms) && isset($___rooms[0]->slug)) ? $___rooms[0]->slug : $space->slug;
  $navBtns = [
      ['key' => 'browse',      'url' => route('exhibition-space.browse'),                                  'icon' => 'fa-th-list',          'label' => __('All spaces'),    'style' => 'btn-outline-secondary'],
      ['key' => 'builder',     'url' => route('exhibition-space.builder',     ['slug' => $space->slug]),    'icon' => 'fa-pen-ruler',        'label' => __('Builder'),       'style' => 'btn-outline-primary'],
      ['key' => 'plan',        'url' => route('exhibition-space.plan',        ['slug' => $space->slug]),    'icon' => 'fa-drafting-compass', 'label' => __('Building Plan'), 'style' => 'btn-outline-primary'],
      ['key' => 'walkthrough', 'url' => route('exhibition-space.walkthrough', ['slug' => $mainSlug]),       'icon' => 'fa-vr-cardboard',     'label' => __('Walkthrough'),   'style' => 'btn-outline-primary'],
      ['key' => 'analytics',   'url' => route('exhibition-space.analytics',   ['slug' => $space->slug]),    'icon' => 'fa-chart-line',       'label' => __('Analytics'),     'style' => 'btn-outline-primary'],
      ['key' => 'forecast',    'url' => route('exhibition-space.forecast',    ['slug' => $space->slug]),    'icon' => 'fa-chart-area',       'label' => __('Forecast'),      'style' => 'btn-outline-primary'],
      ['key' => 'show',        'url' => route('exhibition-space.show',        ['slug' => $space->slug]),    'icon' => 'fa-eye',              'label' => __('Open'),          'style' => 'btn-outline-primary'],
  ];
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
