@php
    $currentRoute = request()->route()?->getName();
    $menuItems = [
        ['route' => 'preservation.index',          'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
        ['route' => 'preservation.fixity-log',      'icon' => 'fa-check-double',   'label' => 'Fixity Log'],
        ['route' => 'preservation.events',          'icon' => 'fa-history',        'label' => 'PREMIS Events'],
        ['route' => 'preservation.formats',         'icon' => 'fa-file-code',      'label' => 'Format Registry'],
        ['route' => 'preservation.identification',  'icon' => 'fa-fingerprint',    'label' => 'Identification'],
        ['route' => 'preservation.conversion',      'icon' => 'fa-sync-alt',       'label' => 'Conversion'],
        ['route' => 'preservation.virus-scan',      'icon' => 'fa-shield-virus',   'label' => 'Virus Scans'],
        ['route' => 'preservation.policies',        'icon' => 'fa-clipboard-list', 'label' => 'Policies'],
        ['route' => 'preservation.packages',        'icon' => 'fa-box',            'label' => 'OAIS Packages'],
        ['route' => 'preservation.scheduler',       'icon' => 'fa-clock',          'label' => 'Scheduler'],
        ['route' => 'preservation.backup',          'icon' => 'fa-database',       'label' => 'Backup & Replication'],
        ['route' => 'preservation.reports',         'icon' => 'fa-chart-bar',      'label' => 'Reports'],
        ['route' => 'preservation.tiffpdfmerge.index', 'icon' => 'fa-file-pdf',    'label' => 'TIFF/PDF Merge'],
    ];
@endphp
<nav id="preservation-menu" class="list-group mb-3 sticky-top" style="top: 1rem;">
    @foreach ($menuItems as $item)
        <a href="{{ route($item['route']) }}"
           class="list-group-item list-group-item-action d-flex align-items-center{{ $currentRoute === $item['route'] ? ' active' : '' }}">
            <i class="fas {{ $item['icon'] }} me-2" style="width:18px;text-align:center;"></i>
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>
