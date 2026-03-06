@php
  $plugins = $themeData['enabledPluginMap'] ?? [];
@endphp

{{-- AHG Plugins menu (admin only) --}}
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="ahg-admin-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-cube px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="AHG Plugins" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">AHG Plugins</span>
    <span class="visually-hidden">AHG Plugins</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="ahg-admin-menu" style="max-height: 80vh; overflow-y: auto;">
    <li><h6 class="dropdown-header">AHG Plugins</h6></li>

    {{-- Settings --}}
    @if(isset($plugins['ahgSettingsPlugin']))
      <li><a class="dropdown-item" href="{{ url('/admin/ahgSettings') }}"><i class="fas fa-cog me-2"></i>AHG Settings</a></li>
    @endif
    <li><a class="dropdown-item" href="{{ url('/admin/dropdowns') }}"><i class="fas fa-list me-2"></i>Dropdown Manager</a></li>
    @if(isset($plugins['ahgCustomFieldsPlugin']))
      <li><a class="dropdown-item" href="{{ url('/admin/customFields') }}"><i class="fas fa-th-list me-2"></i>Custom Fields</a></li>
    @endif

    {{-- Security --}}
    @if(isset($plugins['ahgSecurityClearancePlugin']))
      <li><hr class="dropdown-divider"></li>
      <li><h6 class="dropdown-header">Security</h6></li>
      <li><a class="dropdown-item" href="{{ url('/admin/securityClassification') }}"><i class="fas fa-shield-alt me-2"></i>Security Classifications</a></li>
      <li><a class="dropdown-item" href="{{ url('/admin/userClearance') }}"><i class="fas fa-user-lock me-2"></i>User Clearances</a></li>
    @endif

    {{-- Research --}}
    @if(isset($plugins['ahgResearchPlugin']))
      <li><hr class="dropdown-divider"></li>
      <li><h6 class="dropdown-header">Research</h6></li>
      <li><a class="dropdown-item" href="{{ url('/admin/research') }}"><i class="fas fa-flask me-2"></i>Research Management</a></li>
      <li><a class="dropdown-item" href="{{ url('/admin/readingRoom') }}"><i class="fas fa-book-reader me-2"></i>Reading Rooms</a></li>
    @endif

    {{-- Access --}}
    @if(isset($plugins['ahgAccessRequestPlugin']))
      <li><hr class="dropdown-divider"></li>
      <li><h6 class="dropdown-header">Access</h6></li>
      <li><a class="dropdown-item" href="{{ url('/admin/accessRequests') }}"><i class="fas fa-key me-2"></i>Access Requests</a></li>
    @endif

    {{-- Audit --}}
    @if(isset($plugins['ahgAuditTrailPlugin']))
      <li><hr class="dropdown-divider"></li>
      <li><h6 class="dropdown-header">Audit</h6></li>
      <li><a class="dropdown-item" href="{{ url('/admin/auditLog') }}"><i class="fas fa-history me-2"></i>Audit Log</a></li>
      <li><a class="dropdown-item" href="{{ url('/admin/errorLog') }}"><i class="fas fa-exclamation-triangle me-2"></i>Error Log</a></li>
    @endif

    {{-- Data Quality --}}
    @if(isset($plugins['ahgDedupePlugin']) || isset($plugins['ahgDataMigrationPlugin']))
      <li><hr class="dropdown-divider"></li>
      <li><h6 class="dropdown-header">Data Quality</h6></li>
      @if(isset($plugins['ahgDedupePlugin']))
        <li><a class="dropdown-item" href="{{ url('/admin/dedupe') }}"><i class="fas fa-copy me-2"></i>Duplicate Detection</a></li>
      @endif
      @if(isset($plugins['ahgDataMigrationPlugin']))
        <li><a class="dropdown-item" href="{{ url('/admin/dataMigration') }}"><i class="fas fa-exchange-alt me-2"></i>Data Migration</a></li>
      @endif
    @endif

    {{-- DOI --}}
    @if(isset($plugins['ahgDoiPlugin']))
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="{{ url('/admin/doi') }}"><i class="fas fa-link me-2"></i>DOI Management</a></li>
    @endif

    {{-- Heritage --}}
    @if(isset($plugins['ahgHeritagePlugin']))
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="{{ url('/heritage/admin') }}"><i class="fas fa-monument me-2"></i>Heritage</a></li>
    @endif

    {{-- Queue --}}
    @if(isset($plugins['ahgJobsManagePlugin']))
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="{{ url('/admin/queue') }}"><i class="fas fa-stream me-2"></i>Queue Dashboard</a></li>
    @endif

    {{-- Maintenance --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">Maintenance</h6></li>
    @if(isset($plugins['ahgBackupPlugin']))
      <li><a class="dropdown-item" href="{{ url('/admin/backup') }}"><i class="fas fa-database me-2"></i>Backup &amp; Restore</a></li>
    @endif
    <li><a class="dropdown-item" href="{{ url('/jobs/browse') }}"><i class="fas fa-tasks me-2"></i>Jobs</a></li>
  </ul>
</li>
