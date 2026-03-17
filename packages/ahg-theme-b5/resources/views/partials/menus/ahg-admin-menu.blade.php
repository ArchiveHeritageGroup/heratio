@php
  $plugins = $themeData['enabledPluginMap'] ?? [];
@endphp

{{-- AHG Plugins menu (admin only) --}}
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="ahg-admin-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-cubes px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="AHG Plugins" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">AHG Plugins</span>
    <span class="visually-hidden">AHG Plugins</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="ahg-admin-menu" style="max-height: 80vh; overflow-y: auto;">
    <li><h6 class="dropdown-header">AHG Plugins</h6></li>

    {{-- Settings --}}
    <li><a class="dropdown-item" href="{{ route('settings.index') }}"><i class="fas fa-cog me-2"></i>Settings</a></li>
    <li><a class="dropdown-item" href="{{ url('/admin/dropdowns') }}"><i class="fas fa-list me-2"></i>Dropdown Manager</a></li>
    <li><a class="dropdown-item" href="{{ route('favorites.browse') }}"><i class="fas fa-heart me-2"></i>Favorites</a></li>

    {{-- GLAM / DAM --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">GLAM / DAM</h6></li>
    <li><a class="dropdown-item" href="{{ route('glam.browse') }}"><i class="fas fa-th me-2"></i>Browse by Sector</a></li>
    <li><a class="dropdown-item" href="{{ route('reports.dashboard') }}"><i class="fas fa-chart-bar me-2"></i>Reports</a></li>

    {{-- Research --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">Research</h6></li>
    <li><a class="dropdown-item" href="{{ url('/research/admin') }}"><i class="fas fa-flask me-2"></i>Research Management</a></li>
    <li><a class="dropdown-item" href="{{ url('/research/rooms') }}"><i class="fas fa-book-reader me-2"></i>Reading Rooms</a></li>

    {{-- Security --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">Security</h6></li>
    <li><a class="dropdown-item" href="{{ route('acl.classifications') }}"><i class="fas fa-shield-alt me-2"></i>Classifications</a></li>
    <li><a class="dropdown-item" href="{{ route('acl.clearances') }}"><i class="fas fa-user-lock me-2"></i>Clearances</a></li>
    <li><a class="dropdown-item" href="{{ route('acl.groups') }}"><i class="fas fa-users-cog me-2"></i>ACL Groups</a></li>
    <li><a class="dropdown-item" href="{{ route('acl.access-requests') }}"><i class="fas fa-key me-2"></i>Access Requests</a></li>

    {{-- Audit --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">Audit</h6></li>
    <li><a class="dropdown-item" href="{{ route('audit.browse') }}"><i class="fas fa-history me-2"></i>Audit Log</a></li>
    <li><a class="dropdown-item" href="{{ route('acl.audit-log') }}"><i class="fas fa-shield-alt me-2"></i>Security Audit Log</a></li>

    {{-- Data Quality --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">Data Quality</h6></li>
    <li><a class="dropdown-item" href="{{ route('data-migration.index') }}"><i class="fas fa-exchange-alt me-2"></i>Data Migration</a></li>

    {{-- Workflows --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">Workflows</h6></li>
    <li><a class="dropdown-item" href="{{ route('workflow.dashboard') }}"><i class="fas fa-project-diagram me-2"></i>Workflow Dashboard</a></li>
    <li><a class="dropdown-item" href="{{ route('workflow.admin') }}"><i class="fas fa-cogs me-2"></i>Workflow Admin</a></li>
    <li><a class="dropdown-item" href="{{ route('workflow.gates.admin') }}"><i class="fas fa-clipboard-check me-2"></i>Publish Gates</a></li>

    {{-- Preservation --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">Preservation</h6></li>
    <li><a class="dropdown-item" href="{{ route('preservation.index') }}"><i class="fas fa-archive me-2"></i>Preservation Dashboard</a></li>

    {{-- Loans --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">Loans</h6></li>
    <li><a class="dropdown-item" href="{{ route('loan.index') }}"><i class="fas fa-handshake me-2"></i>Loan Management</a></li>

    {{-- E-Commerce --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">E-Commerce</h6></li>
    <li><a class="dropdown-item" href="{{ route('cart.admin.orders') }}"><i class="fas fa-receipt me-2"></i>Orders</a></li>
    <li><a class="dropdown-item" href="{{ route('cart.admin.settings') }}"><i class="fas fa-shopping-bag me-2"></i>E-Commerce Settings</a></li>

    {{-- Maintenance --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">Maintenance</h6></li>
    <li><a class="dropdown-item" href="{{ url('/jobs/browse') }}"><i class="fas fa-tasks me-2"></i>Jobs</a></li>
  </ul>
</li>
