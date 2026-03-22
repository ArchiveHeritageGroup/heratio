<nav id="heritage-sidebar" class="list-group mb-3 sticky-top" style="top: 1rem;">
  <div class="list-group-item list-group-item-dark fw-bold small text-uppercase">
    <i class="fas fa-tachometer-alt me-1"></i> Heritage Admin
  </div>

  {{-- Dashboard --}}
  <a href="{{ route('heritage.admin') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.admin') ? ' active' : '' }}">
    <i class="fas fa-tachometer-alt me-2" style="width:18px;text-align:center;"></i>
    Dashboard
  </a>
  <a href="{{ route('heritage.landing') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.landing') ? ' active' : '' }}">
    <i class="fas fa-home me-2" style="width:18px;text-align:center;"></i>
    Landing Config
  </a>
  <a href="{{ route('settings.index') }}"
     class="list-group-item list-group-item-action d-flex align-items-center">
    <i class="fas fa-toggle-on me-2" style="width:18px;text-align:center;"></i>
    Feature Toggles
  </a>
  <a href="{{ route('settings.index') }}"
     class="list-group-item list-group-item-action d-flex align-items-center">
    <i class="fas fa-paint-brush me-2" style="width:18px;text-align:center;"></i>
    Branding
  </a>
  <a href="{{ route('user.browse') }}"
     class="list-group-item list-group-item-action d-flex align-items-center">
    <i class="fas fa-users me-2" style="width:18px;text-align:center;"></i>
    Users
  </a>

  {{-- Access Control --}}
  <div class="list-group-item list-group-item-light fw-bold small text-uppercase mt-2">
    <i class="fas fa-shield-alt me-1"></i> Access Control
  </div>
  <a href="{{ route('acl.access-requests') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('acl.access-requests') ? ' active' : '' }}">
    <i class="fas fa-key me-2" style="width:18px;text-align:center;"></i>
    Access Requests
  </a>
  <a href="{{ route('acl.clearances') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('acl.clearances') ? ' active' : '' }}">
    <i class="fas fa-clock me-2" style="width:18px;text-align:center;"></i>
    Embargoes
  </a>
  <a href="{{ route('acl.classifications') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('acl.classifications') ? ' active' : '' }}">
    <i class="fas fa-user-shield me-2" style="width:18px;text-align:center;"></i>
    POPIA Flags
  </a>

  {{-- Custodian Tools --}}
  <div class="list-group-item list-group-item-light fw-bold small text-uppercase mt-2">
    <i class="fas fa-tools me-1"></i> Custodian Tools
  </div>
  <a href="{{ route('heritage.custodian') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.custodian') ? ' active' : '' }}">
    <i class="fas fa-hard-hat me-2" style="width:18px;text-align:center;"></i>
    Custodian Dashboard
  </a>
  <a href="{{ route('heritage.custodian') }}"
     class="list-group-item list-group-item-action d-flex align-items-center">
    <i class="fas fa-layer-group me-2" style="width:18px;text-align:center;"></i>
    Batch Operations
  </a>
  <a href="{{ route('audit.browse') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('audit.*') ? ' active' : '' }}">
    <i class="fas fa-history me-2" style="width:18px;text-align:center;"></i>
    Audit Trail
  </a>

  {{-- Content --}}
  <div class="list-group-item list-group-item-light fw-bold small text-uppercase mt-2">
    <i class="fas fa-file-alt me-1"></i> Content
  </div>
  <a href="{{ route('staticpage.browse') }}"
     class="list-group-item list-group-item-action d-flex align-items-center">
    <i class="fas fa-images me-2" style="width:18px;text-align:center;"></i>
    Hero Slides
  </a>
  <a href="{{ route('staticpage.browse') }}"
     class="list-group-item list-group-item-action d-flex align-items-center">
    <i class="fas fa-star me-2" style="width:18px;text-align:center;"></i>
    Featured Collections
  </a>
  <a href="{{ route('heritage.graph') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.graph') ? ' active' : '' }}">
    <i class="fas fa-project-diagram me-2" style="width:18px;text-align:center;"></i>
    Knowledge Graph
  </a>

  {{-- Analytics --}}
  <div class="list-group-item list-group-item-light fw-bold small text-uppercase mt-2">
    <i class="fas fa-chart-bar me-1"></i> Analytics
  </div>
  <a href="{{ route('heritage.analytics') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.analytics') ? ' active' : '' }}">
    <i class="fas fa-chart-line me-2" style="width:18px;text-align:center;"></i>
    Analytics Dashboard
  </a>
  <a href="{{ route('heritage.analytics') }}"
     class="list-group-item list-group-item-action d-flex align-items-center">
    <i class="fas fa-search me-2" style="width:18px;text-align:center;"></i>
    Search Insights
  </a>
  <a href="{{ route('heritage.admin') }}"
     class="list-group-item list-group-item-action d-flex align-items-center">
    <i class="fas fa-bell me-2" style="width:18px;text-align:center;"></i>
    Alerts
  </a>
</nav>
