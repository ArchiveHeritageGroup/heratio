@php
    $currentRoute = request()->route()?->getName();
    $menuSections = [
        'Heritage Accounting' => [
            ['route' => 'heritage.accounting.dashboard', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
            ['route' => 'heritage.accounting.browse', 'icon' => 'fa-list', 'label' => 'Browse Assets'],
            ['route' => 'heritage.accounting.add', 'icon' => 'fa-plus', 'label' => 'Add Asset'],
            ['route' => 'heritage.accounting.settings', 'icon' => 'fa-cog', 'label' => 'Settings'],
        ],
        'GRAP 103 Compliance' => [
            ['route' => 'heritage.grap.dashboard', 'icon' => 'fa-balance-scale', 'label' => 'Compliance Dashboard'],
            ['route' => 'heritage.grap.batch-check', 'icon' => 'fa-check-double', 'label' => 'Batch Check'],
            ['route' => 'heritage.grap.national-treasury-report', 'icon' => 'fa-file-alt', 'label' => 'Treasury Report'],
        ],
        'Administration' => [
            ['route' => 'heritage.hadmin.index', 'icon' => 'fa-cog', 'label' => 'Admin Index'],
            ['route' => 'heritage.hadmin.regions', 'icon' => 'fa-globe-africa', 'label' => 'Regions'],
            ['route' => 'heritage.hadmin.rule-list', 'icon' => 'fa-gavel', 'label' => 'Rules'],
            ['route' => 'heritage.hadmin.standard-list', 'icon' => 'fa-book', 'label' => 'Standards'],
        ],
        'Reports' => [
            ['route' => 'heritage.hreport.index', 'icon' => 'fa-chart-bar', 'label' => 'Reports Index'],
            ['route' => 'heritage.hreport.asset-register', 'icon' => 'fa-clipboard-list', 'label' => 'Asset Register'],
            ['route' => 'heritage.hreport.movement', 'icon' => 'fa-exchange-alt', 'label' => 'Movement Report'],
            ['route' => 'heritage.hreport.valuation', 'icon' => 'fa-dollar-sign', 'label' => 'Valuation Report'],
        ],
    ];
@endphp
<nav class="list-group mb-3 sticky-top" style="top: 1rem;">
  @foreach($menuSections as $section => $items)
    <div class="list-group-item list-group-item-dark fw-bold small text-uppercase">{{ $section }}</div>
    @foreach($items as $item)
      <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
         class="list-group-item list-group-item-action d-flex align-items-center{{ $currentRoute === $item['route'] ? ' active' : '' }}">
        <i class="fas {{ $item['icon'] }} me-2" style="width:18px;text-align:center;"></i>{{ $item['label'] }}
      </a>
    @endforeach
  @endforeach
</nav>