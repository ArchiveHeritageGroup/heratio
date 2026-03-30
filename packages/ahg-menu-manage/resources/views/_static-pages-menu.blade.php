@php
  // Match AtoM: show children of the "staticPagesMenu" menu item (id 61)
  $culture = app()->getLocale();
  $staticPagesMenu = \Illuminate\Support\Facades\DB::table('menu')
      ->join('menu_i18n', 'menu.id', '=', 'menu_i18n.id')
      ->where('menu.name', 'staticPagesMenu')
      ->where('menu_i18n.culture', $culture)
      ->first();

  $menuItems = collect();
  if ($staticPagesMenu) {
      $menuItems = \Illuminate\Support\Facades\DB::table('menu')
          ->join('menu_i18n', 'menu.id', '=', 'menu_i18n.id')
          ->where('menu.parent_id', $staticPagesMenu->id)
          ->where('menu_i18n.culture', $culture)
          ->orderBy('menu.lft')
          ->select('menu.id', 'menu_i18n.label', 'menu.path')
          ->get();
  }
@endphp

@if($menuItems->count())
  <section class="card mb-3">
    <h2 class="h5 p-3 mb-0">
      {{ $staticPagesMenu->label ?? __('Static pages') }}
    </h2>
    <div class="list-group list-group-flush">
      @foreach($menuItems as $item)
        <a
          class="list-group-item list-group-item-action{{ request()->is(ltrim($item->path, '/')) ? ' active' : '' }}"
          href="{{ url($item->path) }}">
          {{ $item->label }}
        </a>
      @endforeach
    </div>
  </section>
@endif
