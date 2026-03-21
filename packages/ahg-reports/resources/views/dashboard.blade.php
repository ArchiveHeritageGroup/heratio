@extends('theme::layouts.1col')
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
@endphp

@section('content')
<div class="reports-dashboard">
  <div class="row">
    <div class="col-md-3">@include('ahg-reports::_menu')</div>
    <div class="col-md-9">

      <h1><i class="fas fa-tachometer-alt"></i> Central Dashboard</h1>

      {{-- Stats Row --}}
      <div class="row mb-4">
        <div class="col-md-3"><div class="card text-center bg-primary text-white"><div class="card-body"><h2 class="mb-0">{{ number_format($stats['descriptions'] ?? 0) }}</h2><p class="mb-0">Archival Descriptions</p></div></div></div>
        <div class="col-md-3"><div class="card text-center bg-success text-white"><div class="card-body"><h2 class="mb-0">{{ number_format($stats['authorities'] ?? 0) }}</h2><p class="mb-0">Authority Records</p></div></div></div>
        <div class="col-md-3"><div class="card text-center bg-info text-white"><div class="card-body"><h2 class="mb-0">{{ number_format($stats['digital_objects'] ?? 0) }}</h2><p class="mb-0">Digital Objects</p></div></div></div>
        <div class="col-md-3"><div class="card text-center bg-warning text-dark"><div class="card-body"><h2 class="mb-0">{{ number_format($stats['recent_updates'] ?? 0) }}</h2><p class="mb-0">Updated (7 days)</p></div></div></div>
      </div>

      {{-- 3-Column: Reports / Dashboards / Export --}}
      <div class="row mb-4">
        {{-- Reports (blue) --}}
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Reports</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ route('reports.descriptions') }}"><i class="fas fa-archive me-2 text-muted"></i>Archival Descriptions</a></li>
              <li class="list-group-item"><a href="{{ route('reports.authorities') }}"><i class="fas fa-users me-2 text-muted"></i>Authority Records</a></li>
              <li class="list-group-item"><a href="{{ route('reports.repositories') }}"><i class="fas fa-building me-2 text-muted"></i>Repositories</a></li>
              <li class="list-group-item"><a href="{{ route('reports.accessions') }}"><i class="fas fa-inbox me-2 text-muted"></i>Accessions</a></li>
              @if($hasDonor)<li class="list-group-item"><a href="{{ url('/donor/browse') }}"><i class="fas fa-handshake me-2 text-muted"></i>Donor Agreements</a></li>@endif
              <li class="list-group-item"><a href="{{ route('reports.storage') }}"><i class="fas fa-boxes me-2 text-muted"></i>Physical Storage</a></li>
              <li class="list-group-item"><a href="{{ route('reports.spatial') }}"><i class="fas fa-map-marker-alt me-2 text-muted"></i>Spatial Analysis Export</a></li>
              @if($hasGallery || $hasLibrary || $hasDam || $hasMuseum || $has3D || $hasSpectrum)
                <li class="list-group-item border-top mt-2 pt-2"><small class="text-muted fw-bold">Sector Reports</small></li>
                @if($hasGallery)<li class="list-group-item"><a href="{{ url('/gallery/reports') }}"><i class="fas fa-palette me-2 text-muted"></i>Gallery Reports</a></li>@endif
                @if($hasLibrary)<li class="list-group-item"><a href="{{ url('/library/reports') }}"><i class="fas fa-book me-2 text-muted"></i>Library Reports</a></li>@endif
                @if($hasDam)<li class="list-group-item"><a href="{{ url('/dam/reports') }}"><i class="fas fa-images me-2 text-muted"></i>DAM Reports</a></li>@endif
                @if($hasMuseum)<li class="list-group-item"><a href="{{ url('/museum/reports') }}"><i class="fas fa-landmark me-2 text-muted"></i>Museum Reports</a></li>@endif
                @if($has3D)<li class="list-group-item"><a href="{{ url('/3d/reports') }}"><i class="fas fa-cube me-2 text-muted"></i>3D Object Reports</a></li>@endif
                @if($hasSpectrum)<li class="list-group-item"><a href="{{ url('/spectrum/reports') }}"><i class="fas fa-clipboard-list me-2 text-muted"></i>Spectrum Reports</a></li>@endif
              @endif
            </ul>
          </div>
        </div>

        {{-- Dashboards (teal) --}}
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Dashboards</h5></div>
            <ul class="list-group list-group-flush">
              @if($hasSpectrum)<li class="list-group-item"><a href="{{ url('/spectrum/dashboard') }}"><i class="fas fa-layer-group me-2 text-muted"></i>Spectrum Workflow</a></li>@endif
              @if($hasWorkflow)
                <li class="list-group-item"><a href="{{ url('/workflow') }}"><i class="fas fa-tasks me-2 text-muted"></i>Approval Workflow</a></li>
                <li class="list-group-item"><a href="{{ url('/workflow/my-tasks') }}"><i class="fas fa-clipboard-check me-2 text-muted"></i>My Workflow Tasks</a></li>
                <li class="list-group-item"><a href="{{ url('/workflow/pool') }}"><i class="fas fa-inbox me-2 text-muted"></i>Task Pool</a></li>
              @endif
              @if($hasGrap)<li class="list-group-item"><a href="{{ url('/grap/dashboard') }}"><i class="fas fa-balance-scale me-2 text-muted"></i>GRAP 103 Dashboard</a></li>@endif
              @if($hasHeritage)<li class="list-group-item"><a href="{{ url('/heritage-accounting/dashboard') }}"><i class="fas fa-landmark me-2 text-muted"></i>Heritage Asset Accounting</a></li>@endif
              @if($hasCondition)<li class="list-group-item"><a href="{{ url('/admin/condition') }}"><i class="fas fa-heartbeat me-2 text-muted"></i>Condition Management</a></li>@endif
              @if($hasOais)<li class="list-group-item"><a href="{{ route('preservation.index') }}"><i class="fas fa-archive me-2 text-muted"></i>Digital Preservation (OAIS)</a></li>@endif
              @if($hasResearch)<li class="list-group-item"><a href="{{ url('/research/admin') }}"><i class="fas fa-graduation-cap me-2 text-muted"></i>Research Services</a></li>@endif
              @if($hasDonor)<li class="list-group-item"><a href="{{ url('/donor/browse') }}"><i class="fas fa-handshake me-2 text-muted"></i>Donor Management</a></li>@endif
              @if($hasGallery)<li class="list-group-item"><a href="{{ url('/gallery/dashboard') }}"><i class="fas fa-palette me-2 text-muted"></i>Gallery Management</a></li>@endif
              @if($hasLibrary)<li class="list-group-item"><a href="{{ url('/library/browse') }}"><i class="fas fa-book me-2 text-muted"></i>Library Management</a></li>@endif
              @if($hasDam)<li class="list-group-item"><a href="{{ url('/dam/dashboard') }}"><i class="fas fa-images me-2 text-muted"></i>Digital Asset Management</a></li>@endif
              @if($hasMuseum)
                <li class="list-group-item"><a href="{{ url('/museum/dashboard') }}"><i class="fas fa-landmark me-2 text-muted"></i>Museum Dashboard</a></li>
                <li class="list-group-item"><a href="{{ url('/museum/exhibitions') }}"><i class="fas fa-theater-masks me-2 text-muted"></i>Exhibitions</a></li>
              @endif
            </ul>
          </div>
        </div>

        {{-- Export (green) --}}
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-download me-2"></i>Export</h5></div>
            <ul class="list-group list-group-flush">
              @if($hasGrap)<li class="list-group-item"><a href="{{ url('/grap/national-treasury-report') }}"><i class="fas fa-balance-scale me-2 text-muted"></i>GRAP 103 National Treasury Report</a></li>@endif
              @if($hasSpectrum)<li class="list-group-item"><a href="{{ url('/spectrum/export') }}"><i class="fas fa-history me-2 text-muted"></i>Spectrum History Export</a></li>@endif
              <li class="list-group-item"><a href="{{ route('reports.descriptions', ['export' => 'csv']) }}"><i class="fas fa-file-csv me-2 text-muted"></i>CSV Export</a></li>
              <li class="list-group-item"><a href="{{ url('/export/ead') }}"><i class="fas fa-file-code me-2 text-muted"></i>EAD Export</a></li>
            </ul>
          </div>
        </div>
      </div>

      {{-- Workflow Row --}}
      @if($hasWorkflow)
      <div class="row mb-4">
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#6610f2!important"><h5 class="mb-0"><i class="fas fa-project-diagram me-2"></i>Approval Workflow</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/workflow') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Workflow Dashboard</a></li>
              <li class="list-group-item"><a href="{{ url('/workflow/my-tasks') }}"><i class="fas fa-clipboard-check me-2 text-muted"></i>My Tasks</a></li>
              <li class="list-group-item"><a href="{{ url('/workflow/pool') }}"><i class="fas fa-inbox me-2 text-muted"></i>Task Pool</a></li>
              <li class="list-group-item"><a href="{{ url('/workflow/history') }}"><i class="fas fa-history me-2 text-muted"></i>Workflow History</a></li>
              <li class="list-group-item"><a href="{{ url('/workflow/admin') }}"><i class="fas fa-cog me-2 text-muted"></i>Configure Workflows</a></li>
            </ul>
          </div>
        </div>
        @if($hasSpectrum)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#0d6efd!important"><h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Spectrum Workflow</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/spectrum/dashboard') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Spectrum Dashboard</a></li>
              <li class="list-group-item"><a href="{{ url('/spectrum/my-tasks') }}"><i class="fas fa-clipboard-list me-2 text-muted"></i>My Spectrum Tasks</a></li>
              <li class="list-group-item"><a href="{{ url('/spectrum/workflows') }}"><i class="fas fa-sitemap me-2 text-muted"></i>Workflow Configurations</a></li>
              <li class="list-group-item"><a href="{{ url('/spectrum/notifications') }}"><i class="fas fa-bell me-2 text-muted"></i>Notifications</a></li>
            </ul>
          </div>
        </div>
        @endif
      </div>
      @endif

      {{-- Compliance Row --}}
      @if($hasSecurity || $hasAudit || $hasPrivacy || $hasCondition || $hasAiCondition)
      <div class="row mb-4">
        @if($hasSecurity || $hasAudit)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-danger text-white"><h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security & Compliance</h5></div>
            <ul class="list-group list-group-flush">
              @if($hasSecurity)
              <li class="list-group-item"><a href="{{ route('acl.clearances') }}"><i class="fas fa-lock me-2 text-muted"></i>Security Dashboard</a></li>
              <li class="list-group-item"><a href="{{ route('acl.clearances') }}"><i class="fas fa-user-shield me-2 text-muted"></i>Clearance Report</a></li>
              @endif
              @if($hasAudit)
              <li class="list-group-item"><a href="{{ route('audit.browse') }}"><i class="fas fa-clipboard-list me-2 text-muted"></i>Audit Log</a></li>
              <li class="list-group-item"><a href="{{ route('audit.statistics') }}"><i class="fas fa-download me-2 text-muted"></i>Export Audit Log</a></li>
              @endif
            </ul>
          </div>
        </div>
        @endif

        @if($hasPrivacy)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-warning text-dark"><h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Privacy & Data Protection</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/privacyAdmin') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Privacy Dashboard</a></li>
              <li class="list-group-item"><a href="{{ url('/privacyAdmin/ropaList') }}"><i class="fas fa-clipboard-list me-2 text-muted"></i>ROPA</a></li>
              <li class="list-group-item"><a href="{{ url('/privacyAdmin/dsarList') }}"><i class="fas fa-user-clock me-2 text-muted"></i>DSAR Requests</a></li>
              <li class="list-group-item"><a href="{{ url('/privacyAdmin/breachList') }}"><i class="fas fa-exclamation-circle me-2 text-muted"></i>Breach Register</a></li>
              <li class="list-group-item"><a href="{{ url('/privacyAdmin/paiaList') }}"><i class="fas fa-file-contract me-2 text-muted"></i>PAIA Requests</a></li>
              <li class="list-group-item"><a href="{{ url('/privacyAdmin/officerList') }}"><i class="fas fa-user-tie me-2 text-muted"></i>Privacy Officers</a></li>
              <li class="list-group-item"><a href="{{ url('/privacyAdmin/config') }}"><i class="fas fa-file-alt me-2 text-muted"></i>Template Library</a></li>
            </ul>
          </div>
        </div>
        @endif

        @if($hasCondition)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-secondary text-white"><h5 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Condition (Spectrum 5.1)</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/admin/condition') }}"><i class="fas fa-clipboard-check me-2 text-muted"></i>Condition Dashboard</a></li>
              <li class="list-group-item"><a href="{{ url('/admin/condition/risk') }}"><i class="fas fa-exclamation-triangle me-2 text-muted"></i>Risk Assessment</a></li>
              <li class="list-group-item"><a href="{{ url('/condition/templates') }}"><i class="fas fa-clipboard me-2 text-muted"></i>Condition Templates</a></li>
            </ul>
          </div>
        </div>
        @endif

        @if($hasAiCondition)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-robot me-2"></i>AI Condition Assessment</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/ai-condition/assess') }}"><i class="fas fa-camera me-2 text-success"></i>New AI Assessment</a></li>
              <li class="list-group-item"><a href="{{ url('/ai-condition/manual') }}"><i class="fas fa-clipboard-check me-2 text-primary"></i>Manual Assessment</a></li>
              <li class="list-group-item"><a href="{{ url('/ai-condition/bulk') }}"><i class="fas fa-layer-group me-2 text-info"></i>Bulk Scan</a></li>
              <li class="list-group-item"><a href="{{ url('/ai-condition/browse') }}"><i class="fas fa-list me-2 text-muted"></i>Browse Assessments</a></li>
              <li class="list-group-item"><a href="{{ url('/ai-condition/training') }}"><i class="fas fa-brain me-2 text-warning"></i>Model Training</a></li>
              <li class="list-group-item"><a href="{{ url('/ai-condition/settings') }}"><i class="fas fa-cog me-2 text-secondary"></i>Settings & API Clients</a></li>
            </ul>
          </div>
        </div>
        @endif
      </div>
      @endif

      {{-- Rights Row --}}
      @if($hasRights)
      <div class="row mb-4">
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#6f42c1!important"><h5 class="mb-0"><i class="fas fa-gavel me-2"></i>Rights & Licensing</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/admin/rights') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Rights Dashboard</a></li>
              <li class="list-group-item"><a href="{{ url('/admin/rights/batch') }}"><i class="fas fa-layer-group me-2 text-muted"></i>Batch Rights Assignment</a></li>
              <li class="list-group-item"><a href="{{ url('/admin/rights/browse') }}"><i class="fas fa-list me-2 text-muted"></i>Browse Rights</a></li>
              <li class="list-group-item"><a href="{{ url('/admin/rights/export') }}"><i class="fas fa-download me-2 text-muted"></i>Export Rights Report</a></li>
            </ul>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#e83e8c!important"><h5 class="mb-0"><i class="fas fa-lock me-2"></i>Embargo Management</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/admin/rights/embargo') }}"><i class="fas fa-clock me-2 text-muted"></i>Active Embargoes</a></li>
              <li class="list-group-item"><a href="{{ url('/admin/rights/batch?type=embargo') }}"><i class="fas fa-plus-circle me-2 text-muted"></i>Apply Embargo</a></li>
              <li class="list-group-item"><a href="{{ url('/admin/rights/expiring') }}"><i class="fas fa-hourglass-half me-2 text-muted"></i>Expiring Soon</a></li>
            </ul>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#20c997!important"><h5 class="mb-0"><i class="fas fa-book-open me-2"></i>Rights Vocabularies</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/admin/rights/statements') }}"><i class="fas fa-copyright me-2 text-muted"></i>Rights Statements</a></li>
              <li class="list-group-item"><a href="{{ url('/admin/rights/creative-commons') }}"><i class="fab fa-creative-commons me-2 text-muted"></i>Creative Commons</a></li>
              <li class="list-group-item"><a href="{{ url('/admin/rights/tk-labels') }}"><i class="fas fa-globe-africa me-2 text-muted"></i>TK Labels</a></li>
              <li class="list-group-item"><a href="{{ url('/rightsholder/browse') }}"><i class="fas fa-user-tie me-2 text-muted"></i>Rights Holders</a></li>
            </ul>
          </div>
        </div>
      </div>
      @endif

      {{-- Vendor Row --}}
      @if($hasVendor)
      <div class="row mb-4">
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#fd7e14!important"><h5 class="mb-0"><i class="fas fa-building me-2"></i>Vendor Management</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/vendor') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Vendor Dashboard</a></li>
              <li class="list-group-item"><a href="{{ url('/vendor/list') }}"><i class="fas fa-list me-2 text-muted"></i>Browse Vendors</a></li>
              <li class="list-group-item"><a href="{{ url('/vendor/add') }}"><i class="fas fa-plus-circle me-2 text-muted"></i>Add Vendor</a></li>
              <li class="list-group-item"><a href="{{ url('/vendor/transactions') }}"><i class="fas fa-exchange-alt me-2 text-muted"></i>Transactions</a></li>
              <li class="list-group-item"><a href="{{ url('/vendor/add-transaction') }}"><i class="fas fa-file-invoice me-2 text-muted"></i>New Transaction</a></li>
              <li class="list-group-item"><a href="{{ url('/vendor/service-types') }}"><i class="fas fa-tools me-2 text-muted"></i>Service Types</a></li>
            </ul>
          </div>
        </div>
      </div>
      @endif

      {{-- Marketplace Row --}}
      @if($hasMarketplace || $hasCart)
      <div class="row mb-4">
        @if($hasMarketplace)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#7c3aed!important"><h5 class="mb-0"><i class="fas fa-store-alt me-2"></i>Marketplace</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/marketplace/admin') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Admin Dashboard</a></li>
              <li class="list-group-item"><a href="{{ url('/marketplace/admin/listings') }}"><i class="fas fa-list me-2 text-muted"></i>All Listings</a></li>
              <li class="list-group-item"><a href="{{ url('/marketplace') }}"><i class="fas fa-search me-2 text-muted"></i>Browse Marketplace</a></li>
              <li class="list-group-item"><a href="{{ url('/marketplace/auctions') }}"><i class="fas fa-gavel me-2 text-muted"></i>Active Auctions</a></li>
              <li class="list-group-item"><a href="{{ url('/marketplace/admin/reports') }}"><i class="fas fa-chart-bar me-2 text-muted"></i>Revenue Reports</a></li>
            </ul>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#2563eb!important"><h5 class="mb-0"><i class="fas fa-users me-2"></i>Sellers & Stores</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/marketplace/admin/sellers') }}"><i class="fas fa-id-badge me-2 text-muted"></i>Manage Sellers</a></li>
              <li class="list-group-item"><a href="{{ url('/marketplace/admin/reviews') }}"><i class="fas fa-star me-2 text-muted"></i>Moderate Reviews</a></li>
              <li class="list-group-item"><a href="{{ url('/marketplace/admin/categories') }}"><i class="fas fa-tags me-2 text-muted"></i>Categories</a></li>
              <li class="list-group-item"><a href="{{ url('/marketplace/admin/currencies') }}"><i class="fas fa-coins me-2 text-muted"></i>Currencies</a></li>
              <li class="list-group-item"><a href="{{ url('/marketplace/admin/settings') }}"><i class="fas fa-cog me-2 text-muted"></i>Marketplace Settings</a></li>
            </ul>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#059669!important"><h5 class="mb-0"><i class="fas fa-cash-register me-2"></i>Sales & Payouts</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/marketplace/admin/transactions') }}"><i class="fas fa-exchange-alt me-2 text-muted"></i>All Transactions</a></li>
              <li class="list-group-item"><a href="{{ url('/marketplace/admin/payouts') }}"><i class="fas fa-money-bill-wave me-2 text-muted"></i>Pending Payouts</a></li>
              <li class="list-group-item"><a href="{{ url('/marketplace/admin/payouts/batch') }}"><i class="fas fa-layer-group me-2 text-muted"></i>Batch Payouts</a></li>
              @if($hasCart)<li class="list-group-item"><a href="{{ url('/cart/admin/orders') }}"><i class="fas fa-shopping-bag me-2 text-muted"></i>Shop Orders</a></li>@endif
            </ul>
          </div>
        </div>
        @elseif($hasCart)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#059669!important"><h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>E-Commerce</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/cart/admin/settings') }}"><i class="fas fa-cog me-2 text-muted"></i>Shop Settings</a></li>
              <li class="list-group-item"><a href="{{ url('/cart/admin/orders') }}"><i class="fas fa-shopping-bag me-2 text-muted"></i>Orders</a></li>
            </ul>
          </div>
        </div>
        @endif
      </div>
      @endif

      {{-- Access / RiC / Backup Row --}}
      @if($hasAccessRequest || $hasRic || $hasBackup)
      <div class="row mb-4">
        @if($hasAccessRequest)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#0d6efd!important"><h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Access Requests</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ route('acl.access-requests') }}"><i class="fas fa-clock me-2 text-muted"></i>Pending Requests</a></li>
              <li class="list-group-item"><a href="{{ route('acl.approvers') }}"><i class="fas fa-user-check me-2 text-muted"></i>Approvers</a></li>
              <li class="list-group-item"><a href="{{ route('acl.access-requests', ['status' => 'all']) }}"><i class="fas fa-history me-2 text-muted"></i>Request History</a></li>
            </ul>
          </div>
        </div>
        @endif
        @if($hasRic)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#6f42c1!important"><h5 class="mb-0"><i class="fas fa-project-diagram me-2"></i>Records in Contexts (RiC)</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ route('ric.index') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>RiC Dashboard</a></li>
              <li class="list-group-item"><a href="{{ route('ric.sync-status') }}"><i class="fas fa-sitemap me-2 text-muted"></i>RiC Explorer</a></li>
              <li class="list-group-item"><a href="{{ route('ric.sync-status') }}"><i class="fas fa-sync me-2 text-muted"></i>Sync Status</a></li>
            </ul>
          </div>
        </div>
        @endif
        @if($hasBackup)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-database me-2"></i>Backup & Maintenance</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ route('backup.index') }}"><i class="fas fa-download me-2 text-muted"></i>Backup Dashboard</a></li>
              <li class="list-group-item"><a href="{{ route('backup.restore') }}"><i class="fas fa-undo-alt me-2 text-muted"></i>Restore</a></li>
              <li class="list-group-item"><a href="{{ route('job.browse') }}"><i class="fas fa-tasks me-2 text-muted"></i>Background Jobs</a></li>
            </ul>
          </div>
        </div>
        @endif
      </div>
      @endif

      {{-- Dedupe / Forms / DOI Row --}}
      @if($hasDedupe || $hasForms || $hasDoi)
      <div class="row mb-4">
        @if($hasDedupe)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#dc3545!important"><h5 class="mb-0"><i class="fas fa-clone me-2"></i>Duplicate Detection</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ route('dedupe.index') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Dedupe Dashboard</a></li>
              <li class="list-group-item"><a href="{{ route('dedupe.browse') }}"><i class="fas fa-list me-2 text-muted"></i>Browse Duplicates</a></li>
              <li class="list-group-item"><a href="{{ route('dedupe.index') }}"><i class="fas fa-search me-2 text-muted"></i>Run Scan</a></li>
              <li class="list-group-item"><a href="{{ route('dedupe.rules') }}"><i class="fas fa-cog me-2 text-muted"></i>Detection Rules</a></li>
              <li class="list-group-item"><a href="{{ route('dedupe.report') }}"><i class="fas fa-chart-bar me-2 text-muted"></i>Reports</a></li>
            </ul>
          </div>
        </div>
        @endif
        @if($hasForms)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#198754!important"><h5 class="mb-0"><i class="fas fa-edit me-2"></i>Form Templates</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/admin/formTemplates') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Forms Dashboard</a></li>
              <li class="list-group-item"><a href="{{ url('/admin/formTemplates/browse') }}"><i class="fas fa-list me-2 text-muted"></i>Browse Templates</a></li>
              <li class="list-group-item"><a href="{{ url('/admin/formTemplates/create') }}"><i class="fas fa-plus me-2 text-muted"></i>Create Template</a></li>
              <li class="list-group-item"><a href="{{ url('/admin/formTemplates/assignments') }}"><i class="fas fa-link me-2 text-muted"></i>Assignments</a></li>
            </ul>
          </div>
        </div>
        @endif
        @if($hasDoi)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#0dcaf0!important"><h5 class="mb-0"><i class="fas fa-link me-2"></i>DOI Management</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ route('doi.index') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>DOI Dashboard</a></li>
              <li class="list-group-item"><a href="{{ route('doi.browse') }}"><i class="fas fa-list me-2 text-muted"></i>Browse DOIs</a></li>
              <li class="list-group-item"><a href="{{ route('doi.queue') }}"><i class="fas fa-tasks me-2 text-muted"></i>Minting Queue</a></li>
              <li class="list-group-item"><a href="{{ route('doi.index') }}"><i class="fas fa-layer-group me-2 text-muted"></i>Batch Mint</a></li>
              <li class="list-group-item"><a href="{{ route('doi.config') }}"><i class="fas fa-cog me-2 text-muted"></i>DataCite Config</a></li>
            </ul>
          </div>
        </div>
        @endif
      </div>
      @endif

      {{-- Data Migration / Heritage / Preservation Row --}}
      @if($hasDataMigration || $hasHeritage2 || $hasPreservation)
      <div class="row mb-4">
        @if($hasDataMigration)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#fd7e14!important"><h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Data Migration</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ route('data-migration.index') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Migration Dashboard</a></li>
              <li class="list-group-item"><a href="{{ route('data-migration.upload') }}"><i class="fas fa-upload me-2 text-muted"></i>Import Data</a></li>
              <li class="list-group-item"><a href="{{ route('data-migration.batch-export') }}"><i class="fas fa-download me-2 text-muted"></i>Export Data</a></li>
              <li class="list-group-item"><a href="{{ route('data-migration.jobs') }}"><i class="fas fa-history me-2 text-muted"></i>Migration History</a></li>
            </ul>
          </div>
        </div>
        @endif
        @if($hasPreservation)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#20c997!important"><h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>TIFF to PDF Merge</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/tiffpdfmerge') }}"><i class="fas fa-plus-circle me-2 text-muted"></i>New Merge Job</a></li>
              <li class="list-group-item"><a href="{{ url('/tiffpdfmerge/browse') }}"><i class="fas fa-list me-2 text-muted"></i>Browse Merge Jobs</a></li>
            </ul>
          </div>
        </div>
        @endif
        @if($hasHeritage2)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#6c757d!important"><h5 class="mb-0"><i class="fas fa-landmark me-2"></i>Heritage Management</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ route('heritage.admin') }}"><i class="fas fa-cogs me-2 text-muted"></i>Admin Dashboard</a></li>
              <li class="list-group-item"><a href="{{ route('heritage.analytics') }}"><i class="fas fa-chart-line me-2 text-muted"></i>Analytics Dashboard</a></li>
              <li class="list-group-item"><a href="{{ route('heritage.custodian') }}"><i class="fas fa-user-shield me-2 text-muted"></i>Custodian Dashboard</a></li>
            </ul>
          </div>
        </div>
        @endif
      </div>
      @endif

      {{-- Preservation Detail Row --}}
      @if($hasPreservation)
      <div class="row mb-4">
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#17a2b8!important"><h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Digital Preservation</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ route('preservation.index') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Preservation Dashboard</a></li>
              <li class="list-group-item"><a href="{{ route('preservation.fixity-log') }}"><i class="fas fa-check-double me-2 text-muted"></i>Fixity Verification</a></li>
              <li class="list-group-item"><a href="{{ route('preservation.events') }}"><i class="fas fa-history me-2 text-muted"></i>PREMIS Events</a></li>
              <li class="list-group-item"><a href="{{ route('preservation.reports') }}"><i class="fas fa-chart-bar me-2 text-muted"></i>Preservation Reports</a></li>
            </ul>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#6610f2!important"><h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Format Registry</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ route('preservation.formats') }}"><i class="fas fa-list me-2 text-muted"></i>Browse Formats</a></li>
              <li class="list-group-item"><a href="{{ route('preservation.formats') }}?risk=high"><i class="fas fa-exclamation-triangle me-2 text-muted"></i>At-Risk Formats</a></li>
              <li class="list-group-item"><a href="{{ route('preservation.policies') }}"><i class="fas fa-cogs me-2 text-muted"></i>Preservation Policies</a></li>
            </ul>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#28a745!important"><h5 class="mb-0"><i class="fas fa-fingerprint me-2"></i>Checksums & Integrity</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ route('preservation.reports') }}?type=missing"><i class="fas fa-exclamation-circle me-2 text-muted"></i>Missing Checksums</a></li>
              <li class="list-group-item"><a href="{{ route('preservation.reports') }}?type=stale"><i class="fas fa-clock me-2 text-muted"></i>Stale Verification</a></li>
              <li class="list-group-item"><a href="{{ route('preservation.fixity-log') }}?status=failed"><i class="fas fa-times-circle me-2 text-muted"></i>Failed Checks</a></li>
            </ul>
          </div>
        </div>
      </div>
      @endif

      {{-- Zimbabwe Compliance Row --}}
      @if($hasCDPA || $hasNAZ || $hasIPSAS || $hasNMMZ)
      <div class="row mb-4">
        @if($hasCDPA)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#198754!important"><h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>CDPA Data Protection</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/cdpa') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>CDPA Dashboard</a></li>
              <li class="list-group-item"><a href="{{ url('/cdpa/license') }}"><i class="fas fa-id-card me-2 text-muted"></i>POTRAZ License</a></li>
              <li class="list-group-item"><a href="{{ url('/cdpa/requests') }}"><i class="fas fa-user-clock me-2 text-muted"></i>Data Subject Requests</a></li>
              <li class="list-group-item"><a href="{{ url('/cdpa/breaches') }}"><i class="fas fa-exclamation-triangle me-2 text-muted"></i>Breach Register</a></li>
            </ul>
          </div>
        </div>
        @endif
        @if($hasNAZ)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#0d6efd!important"><h5 class="mb-0"><i class="fas fa-landmark me-2"></i>NAZ Archives</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/naz') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>NAZ Dashboard</a></li>
              <li class="list-group-item"><a href="{{ url('/naz/closures') }}"><i class="fas fa-lock me-2 text-muted"></i>Closure Periods</a></li>
              <li class="list-group-item"><a href="{{ url('/naz/permits') }}"><i class="fas fa-id-card me-2 text-muted"></i>Research Permits</a></li>
              <li class="list-group-item"><a href="{{ url('/naz/transfers') }}"><i class="fas fa-truck me-2 text-muted"></i>Records Transfers</a></li>
            </ul>
          </div>
        </div>
        @endif
        @if($hasIPSAS)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#ffc107!important;color:#000!important"><h5 class="mb-0" style="color:#000!important"><i class="fas fa-coins me-2"></i>IPSAS Heritage Assets</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/ipsas') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>IPSAS Dashboard</a></li>
              <li class="list-group-item"><a href="{{ url('/ipsas/assets') }}"><i class="fas fa-archive me-2 text-muted"></i>Asset Register</a></li>
              <li class="list-group-item"><a href="{{ url('/ipsas/valuations') }}"><i class="fas fa-calculator me-2 text-muted"></i>Valuations</a></li>
              <li class="list-group-item"><a href="{{ url('/ipsas/insurance') }}"><i class="fas fa-shield-alt me-2 text-muted"></i>Insurance</a></li>
            </ul>
          </div>
        </div>
        @endif
      </div>

      @if($hasNMMZ)
      <div class="row mb-4">
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header text-white" style="background-color:#6c757d!important"><h5 class="mb-0"><i class="fas fa-monument me-2"></i>NMMZ Monuments</h5></div>
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><a href="{{ url('/nmmz') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>NMMZ Dashboard</a></li>
              <li class="list-group-item"><a href="{{ url('/nmmz/monuments') }}"><i class="fas fa-monument me-2 text-muted"></i>National Monuments</a></li>
              <li class="list-group-item"><a href="{{ url('/nmmz/antiquities') }}"><i class="fas fa-vase me-2 text-muted"></i>Antiquities Register</a></li>
              <li class="list-group-item"><a href="{{ url('/nmmz/permits') }}"><i class="fas fa-file-export me-2 text-muted"></i>Export Permits</a></li>
              <li class="list-group-item"><a href="{{ url('/nmmz/sites') }}"><i class="fas fa-map-marker-alt me-2 text-muted"></i>Archaeological Sites</a></li>
            </ul>
          </div>
        </div>
      </div>
      @endif
      @endif

    </div>
  </div>
</div>

@push('css')
<style>
.card-header {
  background-color: var(--ahg-primary, #005837);
  color: var(--ahg-card-header-text, #fff);
  border-color: var(--ahg-primary, #005837);
}
.card-header a {
  color: var(--ahg-card-header-text, #fff);
}
</style>
@endpush
@endsection
