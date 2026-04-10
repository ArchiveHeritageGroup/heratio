<nav id="heritage-sidebar" class="list-group mb-3 sticky-top" style="top: 1rem;">
  <div class="list-group-item fw-bold small text-uppercase" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-tachometer-alt me-1"></i> Heritage Admin
  </div>

  {{-- Dashboard --}}
  <a href="{{ route('heritage.admin') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.admin') ? ' active' : '' }}">
    <i class="fas fa-tachometer-alt me-2" style="width:18px;text-align:center;"></i>
    Dashboard
  </a>
  <a href="{{ route('heritage.admin-config') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.admin-config') ? ' active' : '' }}">
    <i class="fas fa-sliders-h me-2" style="width:18px;text-align:center;"></i>
    Landing Config
  </a>
  <a href="{{ route('heritage.admin-features') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.admin-features') ? ' active' : '' }}">
    <i class="fas fa-toggle-on me-2" style="width:18px;text-align:center;"></i>
    Feature Toggles
  </a>
  <a href="{{ route('heritage.admin-branding') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.admin-branding') ? ' active' : '' }}">
    <i class="fas fa-palette me-2" style="width:18px;text-align:center;"></i>
    Branding
  </a>
  <a href="{{ route('heritage.admin-hero-slides') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.admin-hero-slides') ? ' active' : '' }}">
    <i class="fas fa-images me-2" style="width:18px;text-align:center;"></i>
    Hero Slides
  </a>
  <a href="{{ route('heritage.admin-featured-collections') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.admin-featured-collections') ? ' active' : '' }}">
    <i class="fas fa-star me-2" style="width:18px;text-align:center;"></i>
    Featured Collections
  </a>
  <a href="{{ route('heritage.admin-users') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.admin-users') ? ' active' : '' }}">
    <i class="fas fa-users me-2" style="width:18px;text-align:center;"></i>
    Users
  </a>

  {{-- Contributions --}}
  <div class="list-group-item list-group-item-light fw-bold small text-uppercase mt-2">
    <i class="fas fa-inbox me-1"></i> Contributions
  </div>
  <a href="{{ route('heritage.review-queue') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.review-queue') ? ' active' : '' }}">
    <i class="fas fa-inbox me-2" style="width:18px;text-align:center;"></i>
    Review Queue
  </a>
  <a href="{{ route('heritage.leaderboard') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.leaderboard') ? ' active' : '' }}">
    <i class="fas fa-trophy me-2" style="width:18px;text-align:center;"></i>
    Leaderboard
  </a>

  {{-- Access Control --}}
  <div class="list-group-item list-group-item-light fw-bold small text-uppercase mt-2">
    <i class="fas fa-shield-alt me-1"></i> Access Control
  </div>
  <a href="{{ route('heritage.admin-access-requests') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.admin-access-requests') ? ' active' : '' }}">
    <i class="fas fa-key me-2" style="width:18px;text-align:center;"></i>
    Access Requests
  </a>
  <a href="{{ route('heritage.admin-embargoes') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.admin-embargoes') ? ' active' : '' }}">
    <i class="fas fa-lock me-2" style="width:18px;text-align:center;"></i>
    Embargoes
  </a>
  <a href="{{ route('heritage.admin-popia') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.admin-popia') ? ' active' : '' }}">
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
  <a href="{{ route('heritage.custodian-batch') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.custodian-batch') ? ' active' : '' }}">
    <i class="fas fa-layer-group me-2" style="width:18px;text-align:center;"></i>
    Batch Operations
  </a>
  <a href="{{ route('heritage.custodian-history') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.custodian-history') ? ' active' : '' }}">
    <i class="fas fa-history me-2" style="width:18px;text-align:center;"></i>
    Audit Trail
  </a>

  {{-- Content --}}
  <div class="list-group-item list-group-item-light fw-bold small text-uppercase mt-2">
    <i class="fas fa-file-alt me-1"></i> Content
  </div>
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
  <a href="{{ route('heritage.analytics-search') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.analytics-search') ? ' active' : '' }}">
    <i class="fas fa-search me-2" style="width:18px;text-align:center;"></i>
    Search Insights
  </a>
  <a href="{{ route('heritage.analytics-alerts') }}"
     class="list-group-item list-group-item-action d-flex align-items-center{{ request()->routeIs('heritage.analytics-alerts') ? ' active' : '' }}">
    <i class="fas fa-bell me-2" style="width:18px;text-align:center;"></i>
    Alerts
  </a>
</nav>
