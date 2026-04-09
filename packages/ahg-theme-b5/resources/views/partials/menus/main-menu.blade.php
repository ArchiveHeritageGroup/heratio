@php
  use AhgCore\Services\MenuService;

  $isAdmin = $themeData['isAdmin'] ?? false;
  $isEditor = $themeData['isEditor'] ?? false;
  $culture = $themeData['culture'] ?? 'en';

  // Get menu sections from DB
  $addItems = MenuService::getChildren('add', $culture);
  $manageItems = MenuService::getChildren('manage', $culture);
  $importItems = MenuService::getChildren('import', $culture);
  $adminItems = MenuService::getChildren('admin', $culture);

  // Icon map: menu name → Font Awesome icon class (matching AtoM exactly)
  $iconMap = [
    // Add menu
    'addInformationObject' => 'fas fa-file-alt',
    'addActor' => 'fas fa-user',
    'addRepository' => 'fas fa-building',
    'addTerm' => 'fas fa-tag',
    'addFunction' => 'fas fa-cogs',
    'addAccessionRecord' => 'fas fa-inbox',
    'addDonor' => 'fas fa-hand-holding-heart',
    'addRightsHolder' => 'fas fa-gavel',
    // Manage menu
    'taxonomies' => 'fas fa-tags',
    'donors' => 'fas fa-hand-holding-heart',
    'rightsholders' => 'fas fa-gavel',
    'accessions' => 'fas fa-inbox',
    'browsePhysicalObjects' => 'fas fa-archive',
    'staticPages' => 'fas fa-file',
    'feedback' => 'fas fa-comment',
    // Import menu
    'importXml' => 'fas fa-file-code',
    'importCsv' => 'fas fa-file-csv',
    'importSkos' => 'fas fa-project-diagram',
    'validateCsv' => 'fas fa-check-circle',
    'ftpUpload' => 'fas fa-upload',
    // Admin menu
    'users' => 'fas fa-users',
    'groups' => 'fas fa-user-shield',
    'menu' => 'fas fa-bars',
    'plugins' => 'fas fa-puzzle-piece',
    'themes' => 'fas fa-paint-brush',
    'settings' => 'fas fa-sliders-h',
    'siteInformation' => 'fas fa-info-circle',
    'descriptionUpdates' => 'fas fa-history',
    'globalReplace' => 'fas fa-exchange-alt',
    'visibleElements' => 'fas fa-eye',
    'identifier' => 'fas fa-fingerprint',
    'jobs' => 'fas fa-tasks',
    'portableExport' => 'fas fa-file-export',
    'integrity' => 'fas fa-shield-alt',
  ];

  // Admin-only menu items (require isAdmin, not just isEditor)
  $adminOnly = [
    'addAccessionRecord', 'addDonor', 'addRightsHolder',
    'donors', 'rightsholders', 'accessions', 'browsePhysicalObjects', 'staticPages',
  ];

  // Plugin detection for sector items
  $sectorPlugins = [];
  if (\Illuminate\Support\Facades\Schema::hasTable('atom_plugin')) {
      $sectorPlugins = \Illuminate\Support\Facades\DB::table('atom_plugin')
          ->where('is_enabled', 1)->pluck('name')->flip()->toArray();
  }
  $hasLibrary = isset($sectorPlugins['ahgLibraryPlugin']);
  $hasMuseum = isset($sectorPlugins['ahgMuseumPlugin']);
  $hasGallery = isset($sectorPlugins['ahgGalleryPlugin']);
  $hasDam = isset($sectorPlugins['arDAMPlugin']) || isset($sectorPlugins['ahgDAMPlugin']);

  // Top-level menu icons matching AtoM exactly
  $menuIcons = [
    'add' => 'plus-circle',
    'manage' => 'pen-square',
    'import' => 'download',
    'admin' => 'cog',
  ];
@endphp

{{-- ADD menu --}}
@if($isAdmin || $isEditor)
@if(count($addItems) > 0)
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="add-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-{{ $menuIcons['add'] }} px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="Add" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">Add</span>
    <span class="visually-hidden">Add</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="add-menu">
    {{-- Sector Items --}}
    <li><h6 class="dropdown-header">Sector items</h6></li>
    @php
      $infoObjectItem = collect($addItems)->firstWhere('name', 'addInformationObject');
    @endphp
    @if($infoObjectItem)
      <li>
        <a class="dropdown-item" href="{{ MenuService::resolvePath($infoObjectItem->path) }}">
          <i class="{{ $iconMap[$infoObjectItem->name] ?? 'fas fa-plus' }} me-2"></i>Archival description
        </a>
      </li>
    @endif
    @if($hasMuseum)<li><a class="dropdown-item" href="{{ url('/museum/add') }}"><i class="fas fa-university fa-fw me-2"></i>Museum object</a></li>@endif
    @if($hasGallery)<li><a class="dropdown-item" href="{{ url('/gallery/add') }}"><i class="fas fa-images fa-fw me-2"></i>Gallery item</a></li>@endif
    @if($hasLibrary)<li><a class="dropdown-item" href="{{ url('/library/add') }}"><i class="fas fa-book fa-fw me-2"></i>Library item</a></li>@endif
    @if($hasDam)<li><a class="dropdown-item" href="{{ url('/dam/create') }}"><i class="fas fa-photo-video fa-fw me-2"></i>Photo/DAM asset</a></li>@endif

    {{-- Other entity types --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">Other</h6></li>
    @foreach($addItems as $item)
      @if(in_array($item->name, $adminOnly) && !$isAdmin)
        @continue
      @endif
      @if($item->name !== 'addInformationObject')
        <li>
          <a class="dropdown-item" href="{{ MenuService::resolvePath($item->path) }}">
            <i class="{{ $iconMap[$item->name] ?? 'fas fa-plus' }} me-2"></i>{{ $item->label }}
          </a>
        </li>
      @endif
    @endforeach
  </ul>
</li>
@endif
@endif

{{-- MANAGE menu --}}
@if($isAdmin || $isEditor)
@if(count($manageItems) > 0)
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="manage-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-{{ $menuIcons['manage'] }} px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="Manage" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">Manage</span>
    <span class="visually-hidden">Manage</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="manage-menu">
    <li><h6 class="dropdown-header">Manage</h6></li>
    @foreach($manageItems as $item)
      @if(in_array($item->name, $adminOnly) && !$isAdmin)
        @continue
      @endif
      <li>
        <a class="dropdown-item" href="{{ MenuService::resolvePath($item->path) }}">
          <i class="{{ $iconMap[$item->name] ?? 'fas fa-list' }} me-2"></i>{{ $item->label }}
        </a>
      </li>
    @endforeach
    {{-- Central Dashboards link (matching AtoM) --}}
    <li><hr class="dropdown-divider"></li>
    <li>
      <a class="dropdown-item" href="{{ route('reports.dashboard') }}">
        <i class="fas fa-tachometer-alt fa-fw me-2"></i>Central Dashboards
      </a>
    </li>
  </ul>
</li>
@endif
@endif

{{-- IMPORT menu --}}
@if($isAdmin)
@if(count($importItems) > 0)
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="import-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-{{ $menuIcons['import'] }} px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="Import" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">Import</span>
    <span class="visually-hidden">Import</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="import-menu">
    <li><h6 class="dropdown-header">Import</h6></li>
    @foreach($importItems as $item)
      <li>
        <a class="dropdown-item" href="{{ MenuService::resolvePath($item->path) }}">
          <i class="{{ $iconMap[$item->name] ?? 'fas fa-download' }} me-2"></i>{{ $item->label }}
        </a>
      </li>
    @endforeach
  </ul>
</li>
@endif
@endif

{{-- ADMIN menu --}}
@if($isAdmin)
@if(count($adminItems) > 0)
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="admin-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-{{ $menuIcons['admin'] }} px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="Admin" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">Admin</span>
    <span class="visually-hidden">Admin</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="admin-menu">
    <li><h6 class="dropdown-header">Admin</h6></li>
    @foreach($adminItems as $item)
      <li>
        <a class="dropdown-item" href="{{ MenuService::resolvePath($item->path) }}">
          <i class="{{ $iconMap[$item->name] ?? 'fas fa-cog' }} me-2"></i>{{ $item->label }}
        </a>
      </li>
    @endforeach
  </ul>
</li>
@endif
@endif
