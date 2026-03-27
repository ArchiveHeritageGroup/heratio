{{-- Rights Admin Sidebar - migrated from AtoM _sidebar.php --}}
<div class="list-group mb-3">
  <a href="{{ route('ext-rights-admin.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('ext-rights-admin.index') ? 'active' : '' }}">
    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
  </a>
  <a href="{{ route('ext-rights-admin.embargoes') }}" class="list-group-item list-group-item-action {{ request()->routeIs('ext-rights-admin.embargoes', 'ext-rights-admin.embargo-*') ? 'active' : '' }}">
    <i class="fas fa-clock me-2"></i>Embargoes
  </a>
  <a href="{{ route('ext-rights-admin.orphan-works') }}" class="list-group-item list-group-item-action {{ request()->routeIs('ext-rights-admin.orphan-works', 'ext-rights-admin.orphan-work-*') ? 'active' : '' }}">
    <i class="fas fa-search me-2"></i>Orphan Works
  </a>
  <a href="{{ route('ext-rights-admin.tk-labels') }}" class="list-group-item list-group-item-action {{ request()->routeIs('ext-rights-admin.tk-labels') ? 'active' : '' }}">
    <i class="fas fa-tags me-2"></i>TK Labels
  </a>
  <a href="{{ route('ext-rights-admin.statements') }}" class="list-group-item list-group-item-action {{ request()->routeIs('ext-rights-admin.statements') ? 'active' : '' }}">
    <i class="fas fa-balance-scale me-2"></i>Statements &amp; Licenses
  </a>
</div>

<h6 class="text-muted px-1 mb-2">Reports</h6>
<div class="list-group mb-3">
  <a href="{{ route('ext-rights-admin.report', ['type' => 'summary']) }}" class="list-group-item list-group-item-action {{ request()->routeIs('ext-rights-admin.report') ? 'active' : '' }}">
    <i class="fas fa-chart-bar me-2"></i>Summary
  </a>
  <a href="{{ route('ext-rights-admin.report', ['type' => 'embargoes', 'export' => 'csv']) }}" class="list-group-item list-group-item-action">
    <i class="fas fa-download me-2"></i>Export Embargoes
  </a>
  <a href="{{ route('ext-rights-admin.report', ['type' => 'tk_labels', 'export' => 'csv']) }}" class="list-group-item list-group-item-action">
    <i class="fas fa-download me-2"></i>Export TK Labels
  </a>
</div>

<h6 class="text-muted px-1 mb-2">Actions</h6>
<div class="list-group mb-3">
  <a href="{{ route('ext-rights-admin.process-expired') }}" class="list-group-item list-group-item-action"
     onclick="return confirm('Process all expired embargoes?');">
    <i class="fas fa-sync me-2"></i>Process Expired
  </a>
</div>
