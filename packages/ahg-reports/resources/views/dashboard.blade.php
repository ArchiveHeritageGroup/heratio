@extends('theme::layouts.1col')
@section('title', 'Central Dashboard')
@section('body-class', 'admin reports')

@php
  // Plugin detection using enabledPlugins passed from controller
  $plugins = $enabledPlugins ?? [];
  $has = fn($name) => isset($plugins[$name]);

  $hasLibrary = $has('ahgLibraryPlugin');
  $hasMuseum = $has('ahgMuseumPlugin');
  $hasGallery = $has('ahgGalleryPlugin');
  $hasDam = $has('arDAMPlugin') || $has('ahgDAMPlugin');
  $hasSpectrum = $has('ahgSpectrumPlugin');
  $hasGrap = $has('ahgHeritageAccountingPlugin');
  $hasResearch = $has('ahgResearchPlugin');
  $hasDonor = $has('ahgDonorAgreementPlugin');
  $hasRights = $has('ahgExtendedRightsPlugin');
  $hasCondition = $has('ahgConditionPlugin');
  $hasPrivacy = $has('ahgPrivacyPlugin');
  $hasSecurity = $has('ahgSecurityClearancePlugin');
  $hasAudit = $has('ahgAuditTrailPlugin');
  $hasWorkflow = $has('ahgWorkflowPlugin');
  $hasPreservation = $has('ahgPreservationPlugin');
  $hasAccessRequest = $has('ahgAccessRequestPlugin');
  $hasRic = $has('ahgRicExplorerPlugin');
  $hasDataMigration = $has('ahgDataMigrationPlugin');
  $hasBackup = $has('ahgBackupPlugin');
  $hasDedupe = $has('ahgDedupePlugin');
  $hasDoi = $has('ahgDoiPlugin');
  $hasHeritage2 = $has('ahgHeritagePlugin');
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-tachometer-alt"></i> Central Dashboard</h1>

    {{-- Stats Row: 4 cards --}}
    <div class="row mb-4">
      <div class="col-md-3"><div class="card text-center bg-primary text-white"><div class="card-body"><h2 class="mb-0">{{ number_format($stats['descriptions'] ?? 0) }}</h2><p class="mb-0">Archival Descriptions</p></div></div></div>
      <div class="col-md-3"><div class="card text-center bg-success text-white"><div class="card-body"><h2 class="mb-0">{{ number_format($stats['authorities'] ?? 0) }}</h2><p class="mb-0">Authority Records</p></div></div></div>
      <div class="col-md-3"><div class="card text-center bg-info text-white"><div class="card-body"><h2 class="mb-0">{{ number_format($stats['digital_objects'] ?? 0) }}</h2><p class="mb-0">Digital Objects</p></div></div></div>
      <div class="col-md-3"><div class="card text-center bg-warning text-dark"><div class="card-body"><h2 class="mb-0">{{ number_format($stats['recent_updates'] ?? 0) }}</h2><p class="mb-0">Updated (7 days)</p></div></div></div>
    </div>

    {{-- 3-Column Layout: Reports / Dashboards / Export --}}
    <div class="row mb-4">
      {{-- Reports Column (blue) --}}
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Reports</h5></div>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><a href="{{ route('reports.descriptions') }}"><i class="fas fa-archive me-2 text-muted"></i>Archival Descriptions</a></li>
            <li class="list-group-item"><a href="{{ route('reports.authorities') }}"><i class="fas fa-users me-2 text-muted"></i>Authority Records</a></li>
            <li class="list-group-item"><a href="{{ route('reports.repositories') }}"><i class="fas fa-building me-2 text-muted"></i>Repositories</a></li>
            <li class="list-group-item"><a href="{{ route('reports.accessions') }}"><i class="fas fa-inbox me-2 text-muted"></i>Accessions</a></li>
            <li class="list-group-item"><a href="{{ route('reports.donors') }}"><i class="fas fa-hand-holding-heart me-2 text-muted"></i>Donors</a></li>
            <li class="list-group-item"><a href="{{ route('reports.storage') }}"><i class="fas fa-boxes me-2 text-muted"></i>Physical Storage</a></li>
            <li class="list-group-item"><a href="{{ route('reports.spatial') }}"><i class="fas fa-map-marker-alt me-2 text-muted"></i>Spatial Analysis Export</a></li>
            {{-- Sector Reports (conditional) --}}
            @if($hasGallery || $hasLibrary || $hasDam || $hasMuseum)
              <li class="list-group-item border-top mt-2 pt-2"><small class="text-muted fw-bold">Sector Reports</small></li>
              @if($hasGallery)<li class="list-group-item"><a href="{{ url('/gallery/reports') }}"><i class="fas fa-palette me-2 text-muted"></i>Gallery Reports</a></li>@endif
              @if($hasLibrary)<li class="list-group-item"><a href="{{ url('/library/reports') }}"><i class="fas fa-book me-2 text-muted"></i>Library Reports</a></li>@endif
              @if($hasDam)<li class="list-group-item"><a href="{{ url('/dam/reports') }}"><i class="fas fa-images me-2 text-muted"></i>DAM Reports</a></li>@endif
              @if($hasMuseum)<li class="list-group-item"><a href="{{ url('/museum/reports') }}"><i class="fas fa-landmark me-2 text-muted"></i>Museum Reports</a></li>@endif
            @endif
          </ul>
        </div>
      </div>

      {{-- Dashboards Column (teal) --}}
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Dashboards</h5></div>
          <ul class="list-group list-group-flush">
            @if($hasWorkflow)
              <li class="list-group-item"><a href="{{ url('/workflow') }}"><i class="fas fa-tasks me-2 text-muted"></i>Approval Workflow</a></li>
              <li class="list-group-item"><a href="{{ url('/workflow/my-tasks') }}"><i class="fas fa-clipboard-check me-2 text-muted"></i>My Workflow Tasks</a></li>
            @endif
            @if($hasPreservation)
              <li class="list-group-item"><a href="{{ url('/admin/preservation') }}"><i class="fas fa-archive me-2 text-muted"></i>Digital Preservation (OAIS)</a></li>
            @endif
            @if($hasResearch)
              <li class="list-group-item"><a href="{{ url('/research/admin') }}"><i class="fas fa-graduation-cap me-2 text-muted"></i>Research Services</a></li>
            @endif
            @if($hasGallery)<li class="list-group-item"><a href="{{ url('/gallery/dashboard') }}"><i class="fas fa-palette me-2 text-muted"></i>Gallery Management</a></li>@endif
            @if($hasLibrary)<li class="list-group-item"><a href="{{ url('/library/browse') }}"><i class="fas fa-book me-2 text-muted"></i>Library Management</a></li>@endif
            @if($hasDam)<li class="list-group-item"><a href="{{ url('/dam/dashboard') }}"><i class="fas fa-images me-2 text-muted"></i>Digital Asset Management</a></li>@endif
            @if($hasMuseum)<li class="list-group-item"><a href="{{ url('/museum/dashboard') }}"><i class="fas fa-landmark me-2 text-muted"></i>Museum Dashboard</a></li>@endif
          </ul>
        </div>
      </div>

      {{-- Export Column (green) --}}
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-download me-2"></i>Export</h5></div>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><a href="{{ route('reports.descriptions', ['export' => 'csv']) }}"><i class="fas fa-file-csv me-2 text-muted"></i>CSV Export</a></li>
            <li class="list-group-item"><a href="{{ url('/export/ead') }}"><i class="fas fa-file-code me-2 text-muted"></i>EAD Export</a></li>
          </ul>
        </div>
      </div>
    </div>

    {{-- Workflow Row (conditional) --}}
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
    </div>
    @endif

    {{-- Compliance Row (conditional) --}}
    @if($hasSecurity || $hasAudit || $hasPrivacy || $hasCondition)
    <div class="row mb-4">
      @if($hasSecurity || $hasAudit)
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header bg-danger text-white"><h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security & Compliance</h5></div>
          <ul class="list-group list-group-flush">
            @if($hasSecurity)
            <li class="list-group-item"><a href="{{ route('acl.clearances') }}"><i class="fas fa-lock me-2 text-muted"></i>Security Dashboard</a></li>
            @endif
            @if($hasAudit)
            <li class="list-group-item"><a href="{{ route('audit.browse') }}"><i class="fas fa-clipboard-list me-2 text-muted"></i>Audit Log</a></li>
            @endif
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
          </ul>
        </div>
      </div>
      @endif
    </div>
    @endif

    {{-- Rights Row (conditional) --}}
    @if($hasRights ?? false)
    <div class="row mb-4">
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header text-white" style="background-color:#6f42c1!important"><h5 class="mb-0"><i class="fas fa-gavel me-2"></i>Rights & Licensing</h5></div>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><a href="{{ url('/admin/rights') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Rights Dashboard</a></li>
            <li class="list-group-item"><a href="{{ url('/admin/rights/browse') }}"><i class="fas fa-list me-2 text-muted"></i>Browse Rights</a></li>
          </ul>
        </div>
      </div>
    </div>
    @endif

    {{-- Access/RiC/Backup Row (conditional) --}}
    @if($hasAccessRequest || $hasRic || $hasBackup)
    <div class="row mb-4">
      @if($hasAccessRequest)
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header bg-warning text-dark"><h5 class="mb-0"><i class="fas fa-key me-2"></i>Access Requests</h5></div>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><a href="{{ route('acl.access-requests') }}"><i class="fas fa-inbox me-2 text-muted"></i>Pending Requests</a></li>
            <li class="list-group-item"><a href="{{ route('acl.approvers') }}"><i class="fas fa-user-check me-2 text-muted"></i>Approvers</a></li>
          </ul>
        </div>
      </div>
      @endif
      @if($hasRic)
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>RiC Explorer</h5></div>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><a href="{{ route('ric.index') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>RiC Dashboard</a></li>
            <li class="list-group-item"><a href="{{ route('ric.sync-status') }}"><i class="fas fa-sync me-2 text-muted"></i>Sync Status</a></li>
          </ul>
        </div>
      </div>
      @endif
      @if($hasBackup)
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header bg-secondary text-white"><h5 class="mb-0"><i class="fas fa-hdd me-2"></i>Backup & Restore</h5></div>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><a href="{{ route('backup.index') }}"><i class="fas fa-download me-2 text-muted"></i>Backup</a></li>
            <li class="list-group-item"><a href="{{ route('backup.restore') }}"><i class="fas fa-upload me-2 text-muted"></i>Restore</a></li>
          </ul>
        </div>
      </div>
      @endif
    </div>
    @endif

    {{-- Dedupe/DOI/Data Migration Row (conditional) --}}
    @if($hasDedupe || $hasDoi || $hasDataMigration)
    <div class="row mb-4">
      @if($hasDedupe)
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-clone me-2"></i>Duplicate Detection</h5></div>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><a href="{{ route('dedupe.index') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Dashboard</a></li>
            <li class="list-group-item"><a href="{{ route('dedupe.browse') }}"><i class="fas fa-list me-2 text-muted"></i>Browse Duplicates</a></li>
            <li class="list-group-item"><a href="{{ route('dedupe.rules') }}"><i class="fas fa-cog me-2 text-muted"></i>Detection Rules</a></li>
          </ul>
        </div>
      </div>
      @endif
      @if($hasDoi)
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-fingerprint me-2"></i>DOI Management</h5></div>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><a href="{{ route('doi.index') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>DOI Dashboard</a></li>
            <li class="list-group-item"><a href="{{ route('doi.queue') }}"><i class="fas fa-stream me-2 text-muted"></i>Minting Queue</a></li>
            <li class="list-group-item"><a href="{{ route('doi.config') }}"><i class="fas fa-cog me-2 text-muted"></i>Configuration</a></li>
          </ul>
        </div>
      </div>
      @endif
      @if($hasDataMigration)
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Data Migration</h5></div>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><a href="{{ route('data-migration.index') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Dashboard</a></li>
          </ul>
        </div>
      </div>
      @endif
    </div>
    @endif

    {{-- Heritage/Preservation Row (conditional) --}}
    @if($hasHeritage2 || $hasPreservation)
    <div class="row mb-4">
      @if($hasHeritage2)
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header bg-warning text-dark"><h5 class="mb-0"><i class="fas fa-landmark me-2"></i>Heritage Management</h5></div>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><a href="{{ route('heritage.admin') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Admin Dashboard</a></li>
            <li class="list-group-item"><a href="{{ route('heritage.analytics') }}"><i class="fas fa-chart-line me-2 text-muted"></i>Analytics</a></li>
            <li class="list-group-item"><a href="{{ route('heritage.custodian') }}"><i class="fas fa-hands me-2 text-muted"></i>Custodian</a></li>
          </ul>
        </div>
      </div>
      @endif
      @if($hasPreservation)
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Digital Preservation</h5></div>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><a href="{{ route('preservation.index') }}"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Preservation Dashboard</a></li>
            <li class="list-group-item"><a href="{{ route('preservation.fixity-log') }}"><i class="fas fa-fingerprint me-2 text-muted"></i>Fixity Log</a></li>
            <li class="list-group-item"><a href="{{ route('preservation.formats') }}"><i class="fas fa-file me-2 text-muted"></i>Format Registry</a></li>
          </ul>
        </div>
      </div>
      @endif
    </div>
    @endif

  </div>
</div>
@endsection
