@extends('theme::layouts.2col')
@section('title', 'Central Dashboard')
@section('body-class', 'admin reports')

@php
  $plugins = $enabledPlugins ?? [];
  $has = fn($name) => isset($plugins[$name]);

  $hasLibrary = $has('ahgLibraryPlugin');
  $hasMuseum = $has('ahgMuseumPlugin');
  $hasGallery = $has('ahgGalleryPlugin');
  $hasDam = $has('arDAMPlugin') || $has('ahgDAMPlugin');
  $hasSpectrum = $has('ahgSpectrumPlugin');
  $hasGrap = $has('ahgHeritageAccountingPlugin');
  $hasHeritage = $has('ahgHeritageAccountingPlugin');
  $hasResearch = $has('ahgResearchPlugin');
  $hasDonor = $has('ahgDonorAgreementPlugin');
  $hasRights = $has('ahgExtendedRightsPlugin');
  $hasCondition = $has('ahgConditionPlugin');
  $hasPrivacy = $has('ahgPrivacyPlugin');
  $hasSecurity = $has('ahgSecurityClearancePlugin');
  $hasAudit = $has('ahgAuditTrailPlugin');
  $hasVendor = $has('ahgVendorPlugin');
  $has3D = $has('ahg3DModelPlugin');
  $hasOais = $has('ahgPreservationPlugin');
  $hasPreservation = $has('ahgPreservationPlugin');
  $hasReportBuilder = $has('ahgReportBuilderPlugin');
  $hasAccessRequest = $has('ahgAccessRequestPlugin');
  $hasRic = $has('ahgRicExplorerPlugin');
  $hasDataMigration = $has('ahgDataMigrationPlugin');
  $hasBackup = $has('ahgBackupPlugin');
  $hasDedupe = $has('ahgDedupePlugin');
  $hasForms = $has('ahgFormsPlugin');
  $hasDoi = $has('ahgDoiPlugin');
  $hasHeritage2 = $has('ahgHeritagePlugin');
  $hasWorkflow = $has('ahgWorkflowPlugin');
  $hasMarketplace = $has('ahgMarketplacePlugin');
  $hasCart = $has('ahgCartPlugin');
  $hasAiCondition = $has('ahgAiConditionPlugin');
  $hasCDPA = $has('ahgCDPAPlugin');
  $hasNAZ = $has('ahgNAZPlugin');
  $hasIPSAS = $has('ahgIPSASPlugin');
  $hasNMMZ = $has('ahgNMMZPlugin');
  $hasDataIngest = $has('ahgIngestPlugin') || class_exists(\AhgIngest\Controllers\IngestController::class);
  $hasKnowledge = $has('ahgResearchPlugin');

  $isAdmin = auth()->check() && auth()->user()->isAdministrator();
  $canManage = $isAdmin || (auth()->check() && auth()->user()->isEditor());
@endphp

@section('sidebar')
<div class="sidebar-content">
    <h4>{{ __('Quick Links') }}</h4>
    <ul class="list-unstyled">
        @if ($canManage && $hasReportBuilder)
        <li><a href="{{ url('/admin/report-builder') }}"><i class="fas fa-tools me-2"></i>{{ __('Report Builder') }}</a></li>
        @endif
        @if ($canManage)
        <li><a href="{{ url('/export') }}"><i class="fas fa-download me-2"></i>{{ __('Export Data') }}</a></li>
        @endif
        @if ($hasPreservation && $canManage)
        <li><a href="{{ Route::has('preservation.index') ? route('preservation.index') : url('/preservation') }}"><i class="fas fa-shield-alt me-2"></i>{{ __('Preservation') }}</a></li>
        @endif
    </ul>

    @if ($hasVendor && $canManage)
    <h4 class="mt-4">{{ __('Vendors') }}</h4>
    <ul class="list-unstyled">
        <li><a href="{{ route('ahgvendor.index') }}"><i class="fas fa-building me-2"></i>{{ __('Vendor Dashboard') }}</a></li>
        <li><a href="{{ route('ahgvendor.transactions') }}"><i class="fas fa-exchange-alt me-2"></i>{{ __('Transactions') }}</a></li>
    </ul>
    @endif

    @if ($hasMarketplace && $canManage)
    <h4 class="mt-4">{{ __('Marketplace') }}</h4>
    <ul class="list-unstyled">
        <li><a href="{{ route('ahgmarketplace.admin-dashboard') }}"><i class="fas fa-store-alt me-2"></i>{{ __('Admin Dashboard') }}</a></li>
        <li><a href="{{ route('ahgmarketplace.admin-listings') }}"><i class="fas fa-list me-2"></i>{{ __('Listings') }}</a></li>
        <li><a href="{{ route('ahgmarketplace.admin-sellers') }}"><i class="fas fa-users me-2"></i>{{ __('Sellers') }}</a></li>
        <li><a href="{{ route('ahgmarketplace.admin-transactions') }}"><i class="fas fa-exchange-alt me-2"></i>{{ __('Transactions') }}</a></li>
        <li><a href="{{ route('ahgmarketplace.admin-payouts') }}"><i class="fas fa-money-bill-wave me-2"></i>{{ __('Payouts') }}</a></li>
    </ul>
    @endif

    @if ($hasResearch && $canManage)
    <h4 class="mt-4">{{ __('Research') }}</h4>
    <ul class="list-unstyled">
        <li><a href="{{ url('/research/dashboard') }}"><i class="fas fa-graduation-cap me-2"></i>{{ __('Research Dashboard') }}</a></li>
        <li><a href="{{ url('/research/bookings') }}"><i class="fas fa-calendar-alt me-2"></i>{{ __('Bookings') }}</a></li>
    </ul>
    @endif

    @if ($hasAudit && $canManage)
    <h4 class="mt-4">{{ __('Audit') }}</h4>
    <ul class="list-unstyled">
        <li><a href="{{ Route::has('audit.statistics') ? route('audit.statistics') : url('/audit/statistics') }}"><i class="fas fa-chart-line me-2"></i>{{ __('Statistics') }}</a></li>
        <li><a href="{{ Route::has('audit.browse') ? route('audit.browse') : url('/audit/browse') }}"><i class="fas fa-clipboard-list me-2"></i>{{ __('Logs') }}</a></li>
    </ul>
    @endif

    @if ($isAdmin)
    <h4 class="mt-4">{{ __('Settings') }}</h4>
    <ul class="list-unstyled">
        <li><a href="{{ url('/admin/settings') }}"><i class="fas fa-cogs me-2"></i>{{ __('AHG Settings') }}</a></li>
        @if (Route::has('settings.levels'))
        <li><a href="{{ route('settings.levels') }}"><i class="fas fa-layer-group me-2"></i>{{ __('Levels of Description') }}</a></li>
        @endif
    </ul>

    <h4 class="mt-4">{{ __('Compliance') }}</h4>
    <ul class="list-unstyled">
        @if ($hasSecurity)
        <li><a href="{{ url('/admin/security/compliance') }}"><i class="fas fa-shield-alt me-2"></i>{{ __('Security') }}</a></li>
        @endif
        @if ($hasPrivacy)
        <li><a href="{{ url('/privacyAdmin') }}"><i class="fas fa-user-shield me-2"></i>{{ __('Privacy & Compliance') }}</a></li>
        @endif
        @if ($hasCondition)
        <li><a href="{{ url('/admin/condition') }}"><i class="fas fa-heartbeat me-2"></i>{{ __('Condition') }}</a></li>
        @endif
        @if ($hasRights)
        <li><a href="{{ url('/admin/rights') }}"><i class="fas fa-gavel me-2"></i>{{ __('Rights') }}</a></li>
        @endif
    </ul>
    @endif

    @if (($hasCDPA || $hasNAZ || $hasIPSAS || $hasNMMZ) && $isAdmin)
    <h4 class="mt-4">{{ __('Zimbabwe Compliance') }}</h4>
    <ul class="list-unstyled">
        @if ($hasCDPA)
        <li><a href="{{ url('/cdpa') }}"><i class="fas fa-shield-alt me-2"></i>{{ __('CDPA (Data Protection)') }}</a></li>
        @endif
        @if ($hasNAZ)
        <li><a href="{{ url('/naz') }}"><i class="fas fa-landmark me-2"></i>{{ __('NAZ (Archives)') }}</a></li>
        @endif
        @if ($hasIPSAS)
        <li><a href="{{ url('/ipsas') }}"><i class="fas fa-coins me-2"></i>{{ __('IPSAS (Assets)') }}</a></li>
        @endif
        @if ($hasNMMZ)
        <li><a href="{{ url('/nmmz') }}"><i class="fas fa-monument me-2"></i>{{ __('NMMZ (Monuments)') }}</a></li>
        @endif
    </ul>
    @endif
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-tachometer-alt"></i> {{ __('Central Dashboard') }}</h1>
@endsection

@section('content')
<div class="reports-dashboard">

  {{-- Stats Row --}}
  <div class="row mb-4">
    <div class="col-md-3"><div class="card text-center bg-primary text-white"><div class="card-body"><h2 class="mb-0">{{ number_format($stats['descriptions'] ?? 0) }}</h2><p class="mb-0">{{ __('Archival Descriptions') }}</p></div></div></div>
    <div class="col-md-3"><div class="card text-center bg-success text-white"><div class="card-body"><h2 class="mb-0">{{ number_format($stats['authorities'] ?? 0) }}</h2><p class="mb-0">{{ __('Authority Records') }}</p></div></div></div>
    <div class="col-md-3"><div class="card text-center bg-info text-white"><div class="card-body"><h2 class="mb-0">{{ number_format($stats['digital_objects'] ?? 0) }}</h2><p class="mb-0">{{ __('Digital Objects') }}</p></div></div></div>
    <div class="col-md-3"><div class="card text-center bg-warning text-dark"><div class="card-body"><h2 class="mb-0">{{ number_format($stats['recent_updates'] ?? 0) }}</h2><p class="mb-0">{{ __('Updated (7 days)') }}</p></div></div></div>
  </div>

  {{-- Row 1: Reports / Sector Dashboards / Export (cards 1-3) --}}
  <div class="row mb-4">
    {{-- 1. Reports (blue) --}}
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>{{ __('Reports') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ route('reports.descriptions') }}"><i class="fas fa-archive me-2 text-muted"></i>{{ __('Archival Descriptions') }}</a></li>
          <li class="list-group-item"><a href="{{ route('reports.authorities') }}"><i class="fas fa-users me-2 text-muted"></i>{{ __('Authority Records') }}</a></li>
          <li class="list-group-item"><a href="{{ route('reports.repositories') }}"><i class="fas fa-building me-2 text-muted"></i>{{ __('Repositories') }}</a></li>
          <li class="list-group-item"><a href="{{ route('reports.accessions') }}"><i class="fas fa-inbox me-2 text-muted"></i>{{ __('Accessions') }}</a></li>
          @if($hasDonor)<li class="list-group-item"><a href="{{ url('/donor/browse') }}"><i class="fas fa-handshake me-2 text-muted"></i>{{ __('Donor Agreements') }}</a></li>@endif
          <li class="list-group-item"><a href="{{ route('reports.storage') }}"><i class="fas fa-boxes me-2 text-muted"></i>{{ __('Physical Storage') }}</a></li>
          <li class="list-group-item"><a href="{{ route('reports.spatial') }}"><i class="fas fa-map-marker-alt me-2 text-muted"></i>{{ __('Spatial Analysis Export') }}</a></li>
          @if($hasGallery || $hasLibrary || $hasDam || $hasMuseum || $has3D || $hasSpectrum)
            <li class="list-group-item border-top mt-2 pt-2"><small class="text-muted fw-bold">{{ __('Sector Reports') }}</small></li>
            @if($hasGallery)<li class="list-group-item"><a href="{{ url('/gallery/reports') }}"><i class="fas fa-palette me-2 text-muted"></i>{{ __('Gallery Reports') }}</a></li>@endif
            @if($hasLibrary)<li class="list-group-item"><a href="{{ url('/library/reports') }}"><i class="fas fa-book me-2 text-muted"></i>{{ __('Library Reports') }}</a></li>@endif
            @if($hasDam)<li class="list-group-item"><a href="{{ url('/dam/reports') }}"><i class="fas fa-images me-2 text-muted"></i>{{ __('DAM Reports') }}</a></li>@endif
            @if($hasMuseum)<li class="list-group-item"><a href="{{ url('/museum/reports') }}"><i class="fas fa-landmark me-2 text-muted"></i>{{ __('Museum Reports') }}</a></li>@endif
            @if($has3D)<li class="list-group-item"><a href="{{ url('/3d/reports') }}"><i class="fas fa-cube me-2 text-muted"></i>{{ __('3D Object Reports') }}</a></li>@endif
            @if($hasSpectrum)<li class="list-group-item"><a href="{{ url('/spectrum/reports') }}"><i class="fas fa-clipboard-list me-2 text-muted"></i>{{ __('Spectrum Reports') }}</a></li>@endif
          @endif
        </ul>
      </div>
    </div>

    {{-- 2. Dashboards (teal) --}}
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>{{ __('Dashboards') }}</h5></div>
        <ul class="list-group list-group-flush">
          @if($hasSpectrum)<li class="list-group-item"><a href="{{ url('/spectrum/dashboard') }}"><i class="fas fa-layer-group me-2 text-muted"></i>{{ __('Spectrum Workflow') }}</a></li>@endif
          @if($hasWorkflow)
            <li class="list-group-item"><a href="{{ url('/workflow') }}"><i class="fas fa-tasks me-2 text-muted"></i>{{ __('Approval Workflow') }}</a></li>
            <li class="list-group-item"><a href="{{ url('/workflow/my-tasks') }}"><i class="fas fa-clipboard-check me-2 text-muted"></i>{{ __('My Workflow Tasks') }}</a></li>
            <li class="list-group-item"><a href="{{ url('/workflow/pool') }}"><i class="fas fa-inbox me-2 text-muted"></i>{{ __('Task Pool') }}</a></li>
          @endif
          @if($hasGrap)<li class="list-group-item"><a href="{{ route('heritage.grap.dashboard') }}"><i class="fas fa-balance-scale me-2 text-muted"></i>{{ __('GRAP 103 Dashboard') }}</a></li>@endif
          @if($hasHeritage)<li class="list-group-item"><a href="{{ route('heritage.accounting.dashboard') }}"><i class="fas fa-landmark me-2 text-muted"></i>{{ __('Heritage Asset Accounting') }}</a></li>@endif
          @if($hasCondition)<li class="list-group-item"><a href="{{ url('/admin/condition') }}"><i class="fas fa-heartbeat me-2 text-muted"></i>{{ __('Condition Management') }}</a></li>@endif
          @if($hasOais)<li class="list-group-item"><a href="{{ Route::has('preservation.index') ? route('preservation.index') : url('/preservation') }}"><i class="fas fa-archive me-2 text-muted"></i>{{ __('Digital Preservation (OAIS)') }}</a></li>@endif
          @if($hasResearch)<li class="list-group-item"><a href="{{ url('/research/admin') }}"><i class="fas fa-graduation-cap me-2 text-muted"></i>{{ __('Research Services') }}</a></li>@endif
          @if($hasDonor)<li class="list-group-item"><a href="{{ url('/donor/browse') }}"><i class="fas fa-handshake me-2 text-muted"></i>{{ __('Donor Management') }}</a></li>@endif
          @if($hasGallery)<li class="list-group-item"><a href="{{ url('/gallery/dashboard') }}"><i class="fas fa-palette me-2 text-muted"></i>{{ __('Gallery Management') }}</a></li>@endif
          @if($hasLibrary)<li class="list-group-item"><a href="{{ url('/library/browse') }}"><i class="fas fa-book me-2 text-muted"></i>{{ __('Library Management') }}</a></li>@endif
          @if($hasDam)<li class="list-group-item"><a href="{{ url('/dam/dashboard') }}"><i class="fas fa-images me-2 text-muted"></i>{{ __('Digital Asset Management') }}</a></li>@endif
          @if($hasMuseum)
            <li class="list-group-item"><a href="{{ url('/museum/dashboard') }}"><i class="fas fa-landmark me-2 text-muted"></i>{{ __('Museum Dashboard') }}</a></li>
            <li class="list-group-item"><a href="{{ url('/museum/exhibitions') }}"><i class="fas fa-theater-masks me-2 text-muted"></i>{{ __('Exhibitions') }}</a></li>
          @endif
        </ul>
      </div>
    </div>

    {{-- 3. Export (green) --}}
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-download me-2"></i>{{ __('Export') }}</h5></div>
        <ul class="list-group list-group-flush">
          @if($hasGrap)<li class="list-group-item"><a href="{{ url('/grap/national-treasury-report') }}"><i class="fas fa-balance-scale me-2 text-muted"></i>{{ __('GRAP 103 National Treasury Report') }}</a></li>@endif
          @if($hasSpectrum)<li class="list-group-item"><a href="{{ url('/spectrum/export') }}"><i class="fas fa-history me-2 text-muted"></i>{{ __('Spectrum History Export') }}</a></li>@endif
          <li class="list-group-item"><a href="{{ route('reports.descriptions', ['export' => 'csv']) }}"><i class="fas fa-file-csv me-2 text-muted"></i>{{ __('CSV Export') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/export/ead') }}"><i class="fas fa-file-code me-2 text-muted"></i>{{ __('EAD 2002 Export') }}</a></li>
          @if(Route::has('ahgmetadataexport.index'))
          <li class="list-group-item border-top mt-2 pt-2"><small class="text-muted fw-bold">{{ __('Metadata Standards Export') }}</small></li>
          <li class="list-group-item"><a href="{{ route('ahgmetadataexport.index') }}"><i class="fas fa-file-export me-2 text-muted"></i>{{ __('Metadata Export Hub') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmetadataexport.preview', ['format' => 'ead4']) }}"><i class="fas fa-code me-2 text-muted"></i>{{ __('EAD 4 (Draft)') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmetadataexport.preview', ['format' => 'eac2']) }}"><i class="fas fa-user-tag me-2 text-muted"></i>{{ __('EAC-CPF 2.0') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmetadataexport.preview', ['format' => 'eac-f']) }}"><i class="fas fa-cogs me-2 text-muted"></i>{{ __('EAC-F (Functions)') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmetadataexport.preview', ['format' => 'eag']) }}"><i class="fas fa-building me-2 text-muted"></i>{{ __('EAG 3.0 (Repositories)') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmetadataexport.bulk') }}"><i class="fas fa-download me-2 text-muted"></i>{{ __('Bulk Metadata Export') }}</a></li>
          @endif
        </ul>
      </div>
    </div>
  </div>

  {{-- Row 2: Approval Workflow / Spectrum Workflow (cards 4-5) --}}
  @if($hasWorkflow)
  <div class="row mb-4">
    {{-- 4. Approval Workflow --}}
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#6610f2!important"><h5 class="mb-0"><i class="fas fa-project-diagram me-2"></i>{{ __('Approval Workflow') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/workflow') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Workflow Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/workflow/my-tasks') }}"><i class="fas fa-clipboard-check me-2 text-muted"></i>{{ __('My Tasks') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/workflow/pool') }}"><i class="fas fa-inbox me-2 text-muted"></i>{{ __('Task Pool') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/workflow/history') }}"><i class="fas fa-history me-2 text-muted"></i>{{ __('Workflow History') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/workflow/admin') }}"><i class="fas fa-cog me-2 text-muted"></i>{{ __('Configure Workflows') }}</a></li>
        </ul>
      </div>
    </div>
    {{-- 5. Spectrum Workflow --}}
    @if($hasSpectrum)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#0d6efd!important"><h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>{{ __('Spectrum Workflow') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/spectrum/dashboard') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Spectrum Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/spectrum/my-tasks') }}"><i class="fas fa-clipboard-list me-2 text-muted"></i>{{ __('My Spectrum Tasks') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/spectrum/workflows') }}"><i class="fas fa-sitemap me-2 text-muted"></i>{{ __('Workflow Configurations') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/spectrum/notifications') }}"><i class="fas fa-bell me-2 text-muted"></i>{{ __('Notifications') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
  </div>
  @endif

  {{-- Row 3: Research Services / Knowledge Platform / Research Admin (cards 6-8) --}}
  @if($hasResearch || $hasKnowledge)
  <div class="row mb-4">
    {{-- 6. Research Services --}}
    @if($hasResearch)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#0d6efd!important"><h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>{{ __('Research Services') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/research/dashboard') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Research Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/research/bookings') }}"><i class="fas fa-calendar-alt me-2 text-muted"></i>{{ __('Bookings') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/research/enquiries') }}"><i class="fas fa-envelope me-2 text-muted"></i>{{ __('Research Enquiries') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/research/reproductions') }}"><i class="fas fa-copy me-2 text-muted"></i>{{ __('Reproductions') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
    {{-- 7. Knowledge Platform --}}
    @if($hasKnowledge)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#6f42c1!important"><h5 class="mb-0"><i class="fas fa-brain me-2"></i>{{ __('Knowledge Platform') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/knowledge') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Knowledge Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/knowledge/articles') }}"><i class="fas fa-file-alt me-2 text-muted"></i>{{ __('Articles') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/knowledge/categories') }}"><i class="fas fa-folder me-2 text-muted"></i>{{ __('Categories') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/knowledge/search') }}"><i class="fas fa-search me-2 text-muted"></i>{{ __('Search Knowledge Base') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
    {{-- 8. Research Admin --}}
    @if($hasResearch)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#198754!important"><h5 class="mb-0"><i class="fas fa-user-cog me-2"></i>{{ __('Research Admin') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/research/admin') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Admin Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/research/admin/users') }}"><i class="fas fa-users me-2 text-muted"></i>{{ __('Researcher Accounts') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/research/admin/settings') }}"><i class="fas fa-cog me-2 text-muted"></i>{{ __('Research Settings') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/research/admin/reports') }}"><i class="fas fa-chart-bar me-2 text-muted"></i>{{ __('Usage Reports') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
  </div>
  @endif

  {{-- Row 4: Access Requests (card 9) --}}
  @if($hasAccessRequest)
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#0d6efd!important"><h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>{{ __('Access Requests') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ Route::has('acl.access-requests') ? route('acl.access-requests') : url('/admin/access-requests') }}"><i class="fas fa-clock me-2 text-muted"></i>{{ __('Pending Requests') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('acl.approvers') ? route('acl.approvers') : url('/admin/approvers') }}"><i class="fas fa-user-check me-2 text-muted"></i>{{ __('Approvers') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('acl.access-requests') ? route('acl.access-requests', ['status' => 'all']) : url('/admin/access-requests?status=all') }}"><i class="fas fa-history me-2 text-muted"></i>{{ __('Request History') }}</a></li>
        </ul>
      </div>
    </div>
  </div>
  @endif

  {{-- Row 5: Security & Compliance / Privacy & Data Protection / Condition (cards 10-12) --}}
  @if($hasSecurity || $hasAudit || $hasPrivacy || $hasCondition)
  <div class="row mb-4">
    {{-- 10. Security & Compliance --}}
    @if($hasSecurity || $hasAudit)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header bg-danger text-white"><h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>{{ __('Security & Compliance') }}</h5></div>
        <ul class="list-group list-group-flush">
          @if($hasSecurity)
          <li class="list-group-item"><a href="{{ Route::has('acl.clearances') ? route('acl.clearances') : url('/admin/security/compliance') }}"><i class="fas fa-lock me-2 text-muted"></i>{{ __('Security Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('acl.clearances') ? route('acl.clearances') : url('/admin/security/compliance') }}"><i class="fas fa-user-shield me-2 text-muted"></i>{{ __('Clearance Report') }}</a></li>
          @endif
          @if($hasAudit)
          <li class="list-group-item"><a href="{{ Route::has('audit.browse') ? route('audit.browse') : url('/audit/browse') }}"><i class="fas fa-clipboard-list me-2 text-muted"></i>{{ __('Audit Log') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('audit.statistics') ? route('audit.statistics') : url('/audit/export') }}"><i class="fas fa-download me-2 text-muted"></i>{{ __('Export Audit Log') }}</a></li>
          @endif
        </ul>
      </div>
    </div>
    @endif

    {{-- 11. Privacy & Data Protection --}}
    @if($hasPrivacy)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header bg-warning text-dark"><h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>{{ __('Privacy & Data Protection') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/privacyAdmin') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Privacy Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/privacyAdmin/ropaList') }}"><i class="fas fa-clipboard-list me-2 text-muted"></i>{{ __('ROPA') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/privacyAdmin/dsarList') }}"><i class="fas fa-user-clock me-2 text-muted"></i>{{ __('DSAR Requests') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/privacyAdmin/breachList') }}"><i class="fas fa-exclamation-circle me-2 text-muted"></i>{{ __('Breach Register') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/privacyAdmin/paiaList') }}"><i class="fas fa-file-contract me-2 text-muted"></i>{{ __('PAIA Requests') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/privacyAdmin/officerList') }}"><i class="fas fa-user-tie me-2 text-muted"></i>{{ __('Privacy Officers') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/privacyAdmin/config') }}"><i class="fas fa-file-alt me-2 text-muted"></i>{{ __('Template Library') }}</a></li>
        </ul>
      </div>
    </div>
    @endif

    {{-- 12. Condition (Spectrum 5.1) --}}
    @if($hasCondition)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header bg-secondary text-white"><h5 class="mb-0"><i class="fas fa-heartbeat me-2"></i>{{ __('Condition (Spectrum 5.1)') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/admin/condition') }}"><i class="fas fa-clipboard-check me-2 text-muted"></i>{{ __('Condition Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/admin/condition/risk') }}"><i class="fas fa-exclamation-triangle me-2 text-muted"></i>{{ __('Risk Assessment') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/condition/templates') }}"><i class="fas fa-clipboard me-2 text-muted"></i>{{ __('Condition Templates') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
  </div>
  @endif

  {{-- Row 6: AI Condition Assessment (card 13) --}}
  @if($hasAiCondition)
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-robot me-2"></i>{{ __('AI Condition Assessment') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/ai-condition/assess') }}"><i class="fas fa-camera me-2 text-success"></i>{{ __('New AI Assessment') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/ai-condition/manual') }}"><i class="fas fa-clipboard-check me-2 text-primary"></i>{{ __('Manual Assessment') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/ai-condition/bulk') }}"><i class="fas fa-layer-group me-2 text-info"></i>{{ __('Bulk Scan') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/ai-condition/browse') }}"><i class="fas fa-list me-2 text-muted"></i>{{ __('Browse Assessments') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/ai-condition/training') }}"><i class="fas fa-brain me-2 text-warning"></i>{{ __('Model Training') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/ai-condition/settings') }}"><i class="fas fa-cog me-2 text-secondary"></i>{{ __('Settings & API Clients') }}</a></li>
        </ul>
      </div>
    </div>
  </div>
  @endif

  {{-- AI Condition Stats Row --}}
  @if($hasAiCondition && !empty($aiConditionStats))
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>{{ __('Assessment Statistics') }}</h5></div>
        <div class="card-body">
          <div class="row text-center mb-3">
            <div class="col-4">
              <div class="fs-3 fw-bold text-success">{{ $aiConditionStats['total'] ?? 0 }}</div>
              <small class="text-muted">{{ __('Total') }}</small>
            </div>
            <div class="col-4">
              <div class="fs-3 fw-bold text-primary">{{ $aiConditionStats['confirmed'] ?? 0 }}</div>
              <small class="text-muted">{{ __('Confirmed') }}</small>
            </div>
            <div class="col-4">
              <div class="fs-3 fw-bold text-warning">{{ $aiConditionStats['pending'] ?? 0 }}</div>
              <small class="text-muted">{{ __('Pending') }}</small>
            </div>
          </div>
          @php $avgScore = $aiConditionStats['avg_score'] ?? 0; $scoreColor = $avgScore >= 80 ? 'success' : ($avgScore >= 60 ? 'info' : ($avgScore >= 40 ? 'warning' : 'danger')); @endphp
          <div><span class="text-muted small">{{ __('Average Score') }}:</span> <span class="fw-bold text-{{ $scoreColor }} fs-5">{{ $avgScore }}/100</span></div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>{{ __('Grade Distribution') }}</h5></div>
        <div class="card-body">
          @php
          $gradeColors = ['excellent' => 'success', 'good' => 'info', 'fair' => 'warning', 'poor' => 'danger', 'critical' => 'dark'];
          $gradeIcons = ['excellent' => 'fa-check-circle', 'good' => 'fa-thumbs-up', 'fair' => 'fa-exclamation-triangle', 'poor' => 'fa-times-circle', 'critical' => 'fa-skull-crossbones'];
          $byGrade = $aiConditionStats['by_grade'] ?? [];
          $totalAssessments = max(1, $aiConditionStats['total'] ?? 1);
          @endphp
          @foreach (['excellent', 'good', 'fair', 'poor', 'critical'] as $grade)
          @php $count = $byGrade[$grade] ?? 0; $pct = round(($count / $totalAssessments) * 100); $color = $gradeColors[$grade]; $icon = $gradeIcons[$grade]; @endphp
          <div class="d-flex align-items-center mb-2">
            <span class="badge bg-{{ $color }} me-2" style="min-width:85px"><i class="fas {{ $icon }} me-1"></i>{{ ucfirst($grade) }}</span>
            <div class="progress flex-grow-1" style="height:8px">
              <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%"></div>
            </div>
            <span class="ms-2 small text-muted" style="min-width:30px">{{ $count }}</span>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
  @endif

  {{-- Row 7: Rights & Licensing / Embargo Management / Rights Vocabularies (cards 14-16) --}}
  @if($hasRights)
  <div class="row mb-4">
    {{-- 14. Rights & Licensing --}}
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#6f42c1!important"><h5 class="mb-0"><i class="fas fa-gavel me-2"></i>{{ __('Rights & Licensing') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/admin/rights') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Rights Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/admin/rights/batch') }}"><i class="fas fa-layer-group me-2 text-muted"></i>{{ __('Batch Rights Assignment') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/admin/rights/browse') }}"><i class="fas fa-list me-2 text-muted"></i>{{ __('Browse Rights') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/admin/rights/export') }}"><i class="fas fa-download me-2 text-muted"></i>{{ __('Export Rights Report') }}</a></li>
        </ul>
      </div>
    </div>
    {{-- 15. Embargo Management --}}
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#e83e8c!important"><h5 class="mb-0"><i class="fas fa-lock me-2"></i>{{ __('Embargo Management') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/admin/rights/embargo') }}"><i class="fas fa-clock me-2 text-muted"></i>{{ __('Active Embargoes') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/admin/rights/batch?type=embargo') }}"><i class="fas fa-plus-circle me-2 text-muted"></i>{{ __('Apply Embargo') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/admin/rights/expiring') }}"><i class="fas fa-hourglass-half me-2 text-muted"></i>{{ __('Expiring Soon') }}</a></li>
        </ul>
      </div>
    </div>
    {{-- 16. Rights Vocabularies --}}
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#20c997!important"><h5 class="mb-0"><i class="fas fa-book-open me-2"></i>{{ __('Rights Vocabularies') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/admin/rights/statements') }}"><i class="fas fa-copyright me-2 text-muted"></i>{{ __('Rights Statements') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/admin/rights/creative-commons') }}"><i class="fab fa-creative-commons me-2 text-muted"></i>{{ __('Creative Commons') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/admin/rights/tk-labels') }}"><i class="fas fa-globe-africa me-2 text-muted"></i>{{ __('TK Labels') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/rightsholder/browse') }}"><i class="fas fa-user-tie me-2 text-muted"></i>{{ __('Rights Holders') }}</a></li>
        </ul>
      </div>
    </div>
  </div>
  @endif

  {{-- Row 8: Vendor Management / Donor Management (cards 17-18) --}}
  @if($hasVendor || $hasDonor)
  <div class="row mb-4">
    {{-- 17. Vendor Management --}}
    @if($hasVendor)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#fd7e14!important"><h5 class="mb-0"><i class="fas fa-building me-2"></i>{{ __('Vendor Management') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ route('ahgvendor.index') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Vendor Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgvendor.list') }}"><i class="fas fa-list me-2 text-muted"></i>{{ __('Browse Vendors') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgvendor.add') }}"><i class="fas fa-plus-circle me-2 text-muted"></i>{{ __('Add Vendor') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgvendor.transactions') }}"><i class="fas fa-exchange-alt me-2 text-muted"></i>{{ __('Transactions') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgvendor.add-transaction') }}"><i class="fas fa-file-invoice me-2 text-muted"></i>{{ __('New Transaction') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgvendor.service-types') }}"><i class="fas fa-tools me-2 text-muted"></i>{{ __('Service Types') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
    {{-- 18. Donor Management --}}
    @if($hasDonor)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#17a2b8!important"><h5 class="mb-0"><i class="fas fa-handshake me-2"></i>{{ __('Donor Management') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/donor/browse') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Donor Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/donor/browse') }}"><i class="fas fa-list me-2 text-muted"></i>{{ __('Browse Donors') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/donor/agreements') }}"><i class="fas fa-file-contract me-2 text-muted"></i>{{ __('Donor Agreements') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/donor/reports') }}"><i class="fas fa-chart-bar me-2 text-muted"></i>{{ __('Donor Reports') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
  </div>
  @endif

  {{-- Row 9: Marketplace / Sellers & Stores / Sales & Payouts (cards 19-21) --}}
  @if($hasMarketplace || $hasCart)
  <div class="row mb-4">
    {{-- 19. Marketplace --}}
    @if($hasMarketplace)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#7c3aed!important"><h5 class="mb-0"><i class="fas fa-store-alt me-2"></i>{{ __('Marketplace') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.admin-dashboard') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Admin Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.admin-listings') }}"><i class="fas fa-list me-2 text-muted"></i>{{ __('All Listings') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.browse') }}"><i class="fas fa-search me-2 text-muted"></i>{{ __('Browse Marketplace') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.auction-browse') }}"><i class="fas fa-gavel me-2 text-muted"></i>{{ __('Active Auctions') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.admin-reports') }}"><i class="fas fa-chart-bar me-2 text-muted"></i>{{ __('Revenue Reports') }}</a></li>
        </ul>
      </div>
    </div>
    {{-- 20. Sellers & Stores --}}
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#2563eb!important"><h5 class="mb-0"><i class="fas fa-users me-2"></i>{{ __('Sellers & Stores') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.admin-sellers') }}"><i class="fas fa-id-badge me-2 text-muted"></i>{{ __('Manage Sellers') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.admin-reviews') }}"><i class="fas fa-star me-2 text-muted"></i>{{ __('Moderate Reviews') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.admin-categories') }}"><i class="fas fa-tags me-2 text-muted"></i>{{ __('Categories') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.admin-currencies') }}"><i class="fas fa-coins me-2 text-muted"></i>{{ __('Currencies') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.admin-settings') }}"><i class="fas fa-cog me-2 text-muted"></i>{{ __('Marketplace Settings') }}</a></li>
        </ul>
      </div>
    </div>
    {{-- 21. Sales & Payouts --}}
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#059669!important"><h5 class="mb-0"><i class="fas fa-cash-register me-2"></i>{{ __('Sales & Payouts') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.admin-transactions') }}"><i class="fas fa-exchange-alt me-2 text-muted"></i>{{ __('All Transactions') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.admin-payouts') }}"><i class="fas fa-money-bill-wave me-2 text-muted"></i>{{ __('Pending Payouts') }}</a></li>
          <li class="list-group-item"><a href="{{ route('ahgmarketplace.admin-payouts') }}"><i class="fas fa-layer-group me-2 text-muted"></i>{{ __('Batch Payouts') }}</a></li>
          @if($hasCart)<li class="list-group-item"><a href="{{ url('/cart/admin/orders') }}"><i class="fas fa-shopping-bag me-2 text-muted"></i>{{ __('Shop Orders') }}</a></li>@endif
        </ul>
      </div>
    </div>
    @elseif($hasCart)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#059669!important"><h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>{{ __('E-Commerce') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/cart/admin/settings') }}"><i class="fas fa-cog me-2 text-muted"></i>{{ __('Shop Settings') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/cart/admin/orders') }}"><i class="fas fa-shopping-bag me-2 text-muted"></i>{{ __('Orders') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
  </div>
  @endif

  {{-- Row 10: Form Templates / DOI Management / Records in Contexts (cards 22-24) --}}
  @if($hasForms || $hasDoi || $hasRic)
  <div class="row mb-4">
    @if($hasForms)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#198754!important"><h5 class="mb-0"><i class="fas fa-edit me-2"></i>{{ __('Form Templates') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/admin/formTemplates') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Forms Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/admin/formTemplates/browse') }}"><i class="fas fa-list me-2 text-muted"></i>{{ __('Browse Templates') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/admin/formTemplates/create') }}"><i class="fas fa-plus me-2 text-muted"></i>{{ __('Create Template') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/admin/formTemplates/assignments') }}"><i class="fas fa-link me-2 text-muted"></i>{{ __('Assignments') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
    @if($hasDoi)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#0dcaf0!important"><h5 class="mb-0"><i class="fas fa-link me-2"></i>{{ __('DOI Management') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ Route::has('doi.index') ? route('doi.index') : url('/doi') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('DOI Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('doi.browse') ? route('doi.browse') : url('/doi/browse') }}"><i class="fas fa-list me-2 text-muted"></i>{{ __('Browse DOIs') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('doi.queue') ? route('doi.queue') : url('/doi/queue') }}"><i class="fas fa-tasks me-2 text-muted"></i>{{ __('Minting Queue') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('doi.index') ? route('doi.index') : url('/doi') }}"><i class="fas fa-layer-group me-2 text-muted"></i>{{ __('Batch Mint') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('doi.config') ? route('doi.config') : url('/doi/config') }}"><i class="fas fa-cog me-2 text-muted"></i>{{ __('DataCite Config') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
    @if($hasRic)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#6f42c1!important"><h5 class="mb-0"><i class="fas fa-project-diagram me-2"></i>{{ __('Records in Contexts (RiC)') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ Route::has('ric.index') ? route('ric.index') : url('/ric') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('RiC Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('ric.sync-status') ? route('ric.sync-status') : url('/ric/sync-status') }}"><i class="fas fa-sitemap me-2 text-muted"></i>{{ __('RiC Explorer') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('ric.sync-status') ? route('ric.sync-status') : url('/ric/sync-status') }}"><i class="fas fa-sync me-2 text-muted"></i>{{ __('Sync Status') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
  </div>
  @endif

  {{-- Row 11: Data Migration / Data Ingest / Backup & Maintenance (cards 25-27) --}}
  @if($hasDataMigration || $hasDataIngest || $hasBackup)
  <div class="row mb-4">
    @if($hasDataMigration)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#fd7e14!important"><h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>{{ __('Data Migration') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ Route::has('data-migration.index') ? route('data-migration.index') : url('/data-migration') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Migration Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('data-migration.upload') ? route('data-migration.upload') : url('/data-migration/upload') }}"><i class="fas fa-upload me-2 text-muted"></i>{{ __('Import Data') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('data-migration.batch-export') ? route('data-migration.batch-export') : url('/data-migration/export') }}"><i class="fas fa-download me-2 text-muted"></i>{{ __('Export Data') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('data-migration.jobs') ? route('data-migration.jobs') : url('/data-migration/jobs') }}"><i class="fas fa-history me-2 text-muted"></i>{{ __('Migration History') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
    @if($hasDataIngest)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#20c997!important"><h5 class="mb-0"><i class="fas fa-file-import me-2"></i>{{ __('Data Ingest') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ Route::has('ingest.index') ? route('ingest.index') : url('/ingest') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Ingest Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('ingest.configure') ? route('ingest.configure') : url('/ingest/configure') }}"><i class="fas fa-plus-circle me-2 text-muted"></i>{{ __('New Ingest') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('ingest.template') ? route('ingest.template', 'archive') : url('/ingest/template/archive') }}"><i class="fas fa-file-csv me-2 text-muted"></i>{{ __('CSV Template') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
    @if($hasBackup)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-database me-2"></i>{{ __('Backup & Maintenance') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ Route::has('backup.index') ? route('backup.index') : url('/backup') }}"><i class="fas fa-download me-2 text-muted"></i>{{ __('Backup Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('backup.restore') ? route('backup.restore') : url('/backup/restore') }}"><i class="fas fa-undo-alt me-2 text-muted"></i>{{ __('Restore') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('job.browse') ? route('job.browse') : url('/jobs') }}"><i class="fas fa-tasks me-2 text-muted"></i>{{ __('Background Jobs') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
  </div>
  @endif

  {{-- Row 12: Heritage Management / Duplicate Detection / TIFF to PDF Merge (cards 28-30) --}}
  @if($hasHeritage2 || $hasDedupe || $hasPreservation)
  <div class="row mb-4">
    @if($hasHeritage2)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#6c757d!important"><h5 class="mb-0"><i class="fas fa-landmark me-2"></i>{{ __('Heritage Management') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ Route::has('heritage.admin') ? route('heritage.admin') : url('/heritage/admin') }}"><i class="fas fa-cogs me-2 text-muted"></i>{{ __('Admin Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('heritage.analytics') ? route('heritage.analytics') : url('/heritage/analytics') }}"><i class="fas fa-chart-line me-2 text-muted"></i>{{ __('Analytics Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('heritage.custodian') ? route('heritage.custodian') : url('/heritage/custodian') }}"><i class="fas fa-user-shield me-2 text-muted"></i>{{ __('Custodian Dashboard') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
    @if($hasDedupe)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#dc3545!important"><h5 class="mb-0"><i class="fas fa-clone me-2"></i>{{ __('Duplicate Detection') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ Route::has('dedupe.index') ? route('dedupe.index') : url('/dedupe') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Dedupe Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('dedupe.browse') ? route('dedupe.browse') : url('/dedupe/browse') }}"><i class="fas fa-list me-2 text-muted"></i>{{ __('Browse Duplicates') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('dedupe.index') ? route('dedupe.index') : url('/dedupe') }}"><i class="fas fa-search me-2 text-muted"></i>{{ __('Run Scan') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('dedupe.rules') ? route('dedupe.rules') : url('/dedupe/rules') }}"><i class="fas fa-cog me-2 text-muted"></i>{{ __('Detection Rules') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('dedupe.report') ? route('dedupe.report') : url('/dedupe/report') }}"><i class="fas fa-chart-bar me-2 text-muted"></i>{{ __('Reports') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
    @if($hasPreservation)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#20c997!important"><h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>{{ __('TIFF to PDF Merge') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/tiff-pdf-merge') }}"><i class="fas fa-plus-circle me-2 text-muted"></i>{{ __('New Merge Job') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/tiff-pdf-merge/browse') }}"><i class="fas fa-list me-2 text-muted"></i>{{ __('Browse Merge Jobs') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
  </div>
  @endif

  {{-- Row 13: Digital Preservation / Format Registry / Checksums & Integrity (cards 31-33) --}}
  @if($hasPreservation)
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#17a2b8!important"><h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>{{ __('Digital Preservation') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ Route::has('preservation.index') ? route('preservation.index') : url('/preservation') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('Preservation Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('preservation.fixity-log') ? route('preservation.fixity-log') : url('/preservation/fixity-log') }}"><i class="fas fa-check-double me-2 text-muted"></i>{{ __('Fixity Verification') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('preservation.events') ? route('preservation.events') : url('/preservation/events') }}"><i class="fas fa-history me-2 text-muted"></i>{{ __('PREMIS Events') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('preservation.reports') ? route('preservation.reports') : url('/preservation/reports') }}"><i class="fas fa-chart-bar me-2 text-muted"></i>{{ __('Preservation Reports') }}</a></li>
        </ul>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#6610f2!important"><h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>{{ __('Format Registry') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ Route::has('preservation.formats') ? route('preservation.formats') : url('/preservation/formats') }}"><i class="fas fa-list me-2 text-muted"></i>{{ __('Browse Formats') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('preservation.formats') ? route('preservation.formats') . '?risk=high' : url('/preservation/formats?risk=high') }}"><i class="fas fa-exclamation-triangle me-2 text-muted"></i>{{ __('At-Risk Formats') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('preservation.policies') ? route('preservation.policies') : url('/preservation/policies') }}"><i class="fas fa-cogs me-2 text-muted"></i>{{ __('Preservation Policies') }}</a></li>
        </ul>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#28a745!important"><h5 class="mb-0"><i class="fas fa-fingerprint me-2"></i>{{ __('Checksums & Integrity') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ Route::has('preservation.reports') ? route('preservation.reports') . '?type=missing' : url('/preservation/reports?type=missing') }}"><i class="fas fa-exclamation-circle me-2 text-muted"></i>{{ __('Missing Checksums') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('preservation.reports') ? route('preservation.reports') . '?type=stale' : url('/preservation/reports?type=stale') }}"><i class="fas fa-clock me-2 text-muted"></i>{{ __('Stale Verification') }}</a></li>
          <li class="list-group-item"><a href="{{ Route::has('preservation.fixity-log') ? route('preservation.fixity-log') . '?status=failed' : url('/preservation/fixity-log?status=failed') }}"><i class="fas fa-times-circle me-2 text-muted"></i>{{ __('Failed Checks') }}</a></li>
        </ul>
      </div>
    </div>
  </div>
  @endif

  {{-- Row 14: CDPA Data Protection / NAZ Archives / IPSAS Heritage Assets (cards 34-36) --}}
  @if($hasCDPA || $hasNAZ || $hasIPSAS || $hasNMMZ)
  <div class="row mb-4">
    @if($hasCDPA)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#198754!important"><h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>{{ __('CDPA Data Protection') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/cdpa') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('CDPA Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/cdpa/license') }}"><i class="fas fa-id-card me-2 text-muted"></i>{{ __('POTRAZ License') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/cdpa/requests') }}"><i class="fas fa-user-clock me-2 text-muted"></i>{{ __('Data Subject Requests') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/cdpa/breaches') }}"><i class="fas fa-exclamation-triangle me-2 text-muted"></i>{{ __('Breach Register') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
    @if($hasNAZ)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#0d6efd!important"><h5 class="mb-0"><i class="fas fa-landmark me-2"></i>{{ __('NAZ Archives') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/naz') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('NAZ Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/naz/closures') }}"><i class="fas fa-lock me-2 text-muted"></i>{{ __('Closure Periods') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/naz/permits') }}"><i class="fas fa-id-card me-2 text-muted"></i>{{ __('Research Permits') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/naz/transfers') }}"><i class="fas fa-truck me-2 text-muted"></i>{{ __('Records Transfers') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
    @if($hasIPSAS)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#ffc107!important;color:#000!important"><h5 class="mb-0" style="color:#000!important"><i class="fas fa-coins me-2"></i>{{ __('IPSAS Heritage Assets') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/ipsas') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('IPSAS Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/ipsas/assets') }}"><i class="fas fa-archive me-2 text-muted"></i>{{ __('Asset Register') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/ipsas/valuations') }}"><i class="fas fa-calculator me-2 text-muted"></i>{{ __('Valuations') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/ipsas/insurance') }}"><i class="fas fa-shield-alt me-2 text-muted"></i>{{ __('Insurance') }}</a></li>
        </ul>
      </div>
    </div>
    @endif
  </div>

  {{-- Row 15: NMMZ Monuments (card 37) --}}
  @if($hasNMMZ)
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header text-white" style="background-color:#6c757d!important"><h5 class="mb-0"><i class="fas fa-monument me-2"></i>{{ __('NMMZ Monuments') }}</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><a href="{{ url('/nmmz') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>{{ __('NMMZ Dashboard') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/nmmz/monuments') }}"><i class="fas fa-monument me-2 text-muted"></i>{{ __('National Monuments') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/nmmz/antiquities') }}"><i class="fas fa-vase me-2 text-muted"></i>{{ __('Antiquities Register') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/nmmz/permits') }}"><i class="fas fa-file-export me-2 text-muted"></i>{{ __('Export Permits') }}</a></li>
          <li class="list-group-item"><a href="{{ url('/nmmz/sites') }}"><i class="fas fa-map-marker-alt me-2 text-muted"></i>{{ __('Archaeological Sites') }}</a></li>
        </ul>
      </div>
    </div>
  </div>
  @endif
  @endif

</div>

@push('css')
<style>
.reports-dashboard .card-header { color: #fff; }
.reports-dashboard .card-header a { color: #fff; }
</style>
@endpush
@endsection
