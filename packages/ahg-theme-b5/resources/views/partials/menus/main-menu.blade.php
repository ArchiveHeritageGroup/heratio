@php
  $plugins = $themeData['enabledPluginMap'] ?? [];
  $isAdmin = $themeData['isAdmin'] ?? false;
  $isEditor = $themeData['isEditor'] ?? false;
  $hasLibrary = isset($plugins['ahgLibraryPlugin']);
  $hasMuseum = isset($plugins['ahgMuseumPlugin']);
  $hasGallery = isset($plugins['ahgGalleryPlugin']);
  $hasDam = isset($plugins['ahgDAMPlugin']);
  $hasIO = isset($plugins['ahgInformationObjectManagePlugin']);
  $hasDacs = isset($plugins['ahgDacsManagePlugin']);
  $hasDc = isset($plugins['ahgDcManagePlugin']);
  $hasMods = isset($plugins['ahgModsManagePlugin']);
  $hasRad = isset($plugins['ahgRadManagePlugin']);
@endphp

{{-- ADD menu --}}
@if($isAdmin || $isEditor)
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="add-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-plus px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="Add" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">Add</span>
    <span class="visually-hidden">Add</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="add-menu">
    <li><h6 class="dropdown-header">Add</h6></li>
    <li><a class="dropdown-item" href="{{ url('/informationobject/add') }}"><i class="fas fa-file-alt me-2"></i>Archival description</a></li>
    <li><a class="dropdown-item" href="{{ url('/actor/add') }}"><i class="fas fa-user me-2"></i>Authority record</a></li>
    <li><a class="dropdown-item" href="{{ url('/repository/add') }}"><i class="fas fa-building me-2"></i>Archival institution</a></li>
    <li><a class="dropdown-item" href="{{ url('/term/add') }}"><i class="fas fa-tag me-2"></i>Term</a></li>
    <li><a class="dropdown-item" href="{{ url('/function/add') }}"><i class="fas fa-cogs me-2"></i>Function</a></li>
    @if($isAdmin)
      <li><a class="dropdown-item" href="{{ url('/accession/add') }}"><i class="fas fa-inbox me-2"></i>Accession record</a></li>
      <li><a class="dropdown-item" href="{{ url('/donor/add') }}"><i class="fas fa-hand-holding-heart me-2"></i>Donor record</a></li>
      <li><a class="dropdown-item" href="{{ url('/rightsholder/add') }}"><i class="fas fa-gavel me-2"></i>Rights holder</a></li>
    @endif
    @if($hasMuseum)
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="{{ url('/museum/object/add') }}"><i class="fas fa-landmark me-2"></i>Museum object</a></li>
    @endif
    @if($hasGallery)
      <li><a class="dropdown-item" href="{{ url('/gallery/item/add') }}"><i class="fas fa-palette me-2"></i>Gallery item</a></li>
    @endif
    @if($hasLibrary)
      <li><a class="dropdown-item" href="{{ url('/library/item/add') }}"><i class="fas fa-book me-2"></i>Library item</a></li>
    @endif
    @if($hasDam)
      <li><a class="dropdown-item" href="{{ url('/dam/asset/add') }}"><i class="fas fa-camera me-2"></i>Photo / DAM asset</a></li>
    @endif
  </ul>
</li>
@endif

{{-- MANAGE menu --}}
@if($isAdmin || $isEditor)
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="manage-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-th-list px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="Manage" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">Manage</span>
    <span class="visually-hidden">Manage</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="manage-menu">
    <li><h6 class="dropdown-header">Manage</h6></li>
    <li><a class="dropdown-item" href="{{ url('/taxonomy/browse') }}"><i class="fas fa-tags me-2"></i>Taxonomies</a></li>
    @if($isAdmin)
      <li><a class="dropdown-item" href="{{ url('/donor/browse') }}"><i class="fas fa-hand-holding-heart me-2"></i>Donors</a></li>
      <li><a class="dropdown-item" href="{{ url('/rightsholder/browse') }}"><i class="fas fa-gavel me-2"></i>Rights holders</a></li>
      <li><a class="dropdown-item" href="{{ url('/accession/browse') }}"><i class="fas fa-inbox me-2"></i>Accessions</a></li>
      <li><a class="dropdown-item" href="{{ url('/physicalobject/browse') }}"><i class="fas fa-archive me-2"></i>Physical storage</a></li>
      <li><a class="dropdown-item" href="{{ url('/staticpage/browse') }}"><i class="fas fa-file me-2"></i>Static pages</a></li>
    @endif
  </ul>
</li>
@endif

{{-- IMPORT menu --}}
@if($isAdmin)
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="import-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-download px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="Import" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">Import</span>
    <span class="visually-hidden">Import</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="import-menu">
    <li><h6 class="dropdown-header">Import</h6></li>
    <li><a class="dropdown-item" href="{{ url('/object/importSelect?type=csv') }}"><i class="fas fa-file-csv me-2"></i>CSV</a></li>
    <li><a class="dropdown-item" href="{{ url('/object/importSelect?type=xml') }}"><i class="fas fa-file-code me-2"></i>XML/EAD</a></li>
    @if(isset($plugins['ahgIngestPlugin']))
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="{{ url('/ingest/wizard') }}"><i class="fas fa-magic me-2"></i>Ingest Wizard</a></li>
    @endif
  </ul>
</li>
@endif

{{-- ADMIN menu --}}
@if($isAdmin)
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="admin-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-cog px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="Admin" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">Admin</span>
    <span class="visually-hidden">Admin</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="admin-menu">
    <li><h6 class="dropdown-header">Admin</h6></li>
    <li><a class="dropdown-item" href="{{ url('/user/browse') }}"><i class="fas fa-users me-2"></i>Users</a></li>
    <li><a class="dropdown-item" href="{{ url('/aclGroup/browse') }}"><i class="fas fa-user-shield me-2"></i>Groups</a></li>
    <li><a class="dropdown-item" href="{{ url('/menu/browse') }}"><i class="fas fa-bars me-2"></i>Menus</a></li>
    <li><hr class="dropdown-divider"></li>
    <li><a class="dropdown-item" href="{{ url('/settings/siteInformation') }}"><i class="fas fa-info-circle me-2"></i>Site information</a></li>
    <li><a class="dropdown-item" href="{{ url('/settings/default') }}"><i class="fas fa-sliders-h me-2"></i>Default page elements</a></li>
    <li><a class="dropdown-item" href="{{ url('/sfPluginAdminPlugin/plugins') }}"><i class="fas fa-puzzle-piece me-2"></i>Plugins</a></li>
    <li><a class="dropdown-item" href="{{ url('/settings/identifier') }}"><i class="fas fa-fingerprint me-2"></i>Identifiers</a></li>
    <li><a class="dropdown-item" href="{{ url('/jobs/browse') }}"><i class="fas fa-tasks me-2"></i>Jobs</a></li>
  </ul>
</li>
@endif
