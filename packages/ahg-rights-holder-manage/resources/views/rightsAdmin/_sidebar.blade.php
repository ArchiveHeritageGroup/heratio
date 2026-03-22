{{-- Rights Admin Sidebar --}}
<div class="list-group mb-3">
  <a href="{{ route('rights-admin.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('rights-admin.index') ? 'active' : '' }}">
    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
  </a>
  <a href="{{ route('rights-admin.embargoes') }}" class="list-group-item list-group-item-action {{ request()->routeIs('rights-admin.embargoes') ? 'active' : '' }}">
    <i class="fas fa-lock me-2"></i>Embargoes
  </a>
  <a href="{{ route('rights-admin.orphan-works') }}" class="list-group-item list-group-item-action {{ request()->routeIs('rights-admin.orphan-works') ? 'active' : '' }}">
    <i class="fas fa-question-circle me-2"></i>Orphan Works
  </a>
  <a href="{{ route('rights-admin.statements') }}" class="list-group-item list-group-item-action {{ request()->routeIs('rights-admin.statements') ? 'active' : '' }}">
    <i class="fas fa-balance-scale me-2"></i>Rights Statements
  </a>
  <a href="{{ route('rights-admin.tk-labels') }}" class="list-group-item list-group-item-action {{ request()->routeIs('rights-admin.tk-labels') ? 'active' : '' }}">
    <i class="fas fa-hand-holding-heart me-2"></i>TK Labels
  </a>
  <a href="{{ route('rights-admin.report') }}" class="list-group-item list-group-item-action {{ request()->routeIs('rights-admin.report') ? 'active' : '' }}">
    <i class="fas fa-chart-bar me-2"></i>Report
  </a>
</div>
