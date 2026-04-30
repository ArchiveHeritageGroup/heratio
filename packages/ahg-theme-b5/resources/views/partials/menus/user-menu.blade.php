@php
  $user = $themeData['user'] ?? null;
  $isAuthenticated = $themeData['isAuthenticated'] ?? false;
  $isAdmin = $themeData['isAdmin'] ?? false;
  $plugins = $themeData['enabledPluginMap'] ?? [];

  // Badge counts for authenticated users
  $pendingAccessCount = 0;
  $pendingResearcherCount = 0;
  $pendingBookingCount = 0;
  $workflowTaskCount = 0;

  if ($isAuthenticated) {
    try {
      $workflowTaskCount = \AhgSpectrum\Services\SpectrumNotificationService::getActiveTaskCount($user->id ?? 0);
    } catch (\Exception $e) {}

    if ($isAdmin) {
      try {
        $pendingAccessCount = \Illuminate\Support\Facades\DB::table('security_access_request')
          ->where('status', 'pending')
          ->count();
      } catch (\Exception $e) {}
      try {
        $pendingResearcherCount = \Illuminate\Support\Facades\DB::table('research_researcher')
          ->where('status', 'pending')
          ->count();
      } catch (\Exception $e) {}
      try {
        $pendingBookingCount = \Illuminate\Support\Facades\DB::table('research_booking')
          ->where('status', 'pending')
          ->count();
      } catch (\Exception $e) {}
    }
  }
@endphp

@if($isAuthenticated && $user)
{{-- Authenticated: user profile dropdown --}}
<div class="dropdown my-2">
  <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-user me-1"></i>{{ $user->username }}
  </button>
  <ul class="dropdown-menu dropdown-menu-lg-end mt-2" aria-labelledby="user-menu">

    {{-- Profile Section --}}
    <li><h6 class="dropdown-header"><i class="fas fa-user me-1"></i>Profile</h6></li>
    <li>
      <a class="dropdown-item" href="{{ url('/user/profile') }}">
        <i class="fas fa-id-card me-2"></i>My Profile
      </a>
    </li>
    <li>
      <a class="dropdown-item" href="{{ url('/user/password') }}">
        <i class="fas fa-key me-2"></i>Change Password
      </a>
    </li>

    {{-- Spectrum Tasks Section --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header"><i class="fas fa-tasks me-1"></i>Tasks</h6></li>
    <li>
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ route('ahgspectrum.my-tasks') }}">
        <span><i class="fas fa-clipboard-list me-2"></i>My Tasks</span>
        @if($workflowTaskCount > 0)
          <span class="badge bg-danger">{{ $workflowTaskCount }}</span>
        @endif
      </a>
    </li>
    <li>
      <a class="dropdown-item" href="{{ route('ahgspectrum.dashboard') }}">
        <i class="fas fa-tachometer-alt me-2"></i>Workflow Dashboard
      </a>
    </li>

    {{-- Research Section --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header"><i class="fas fa-book-reader me-1"></i>Research</h6></li>
    <li>
      <a class="dropdown-item" href="{{ url('/research/dashboard') }}">
        <i class="fas fa-folder-open me-2"></i>My Workspace
      </a>
    </li>

    {{-- Favorites --}}
    <li>
      <a class="dropdown-item" href="{{ route('favorites.browse') }}">
        <i class="fas fa-heart me-2"></i>My Favorites
      </a>
    </li>

    {{-- Cart / Orders --}}
    <li>
      <a class="dropdown-item" href="{{ route('cart.orders') }}">
        <i class="fas fa-shopping-cart me-2"></i>My Orders
      </a>
    </li>

    {{-- Security Section --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header"><i class="fas fa-shield-alt me-1"></i>Security</h6></li>
    <li>
      <a class="dropdown-item" href="{{ route('security.my-requests') }}">
        <i class="fas fa-key me-2"></i>My Access Requests
      </a>
    </li>
    @if($isAdmin)
    <li>
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ route('security.pending-requests') }}">
        <span><i class="fas fa-clock me-2"></i>Pending Requests</span>
        @if($pendingAccessCount > 0)
          <span class="badge bg-warning text-dark">{{ $pendingAccessCount }}</span>
        @endif
      </a>
    </li>
    <li>
      <a class="dropdown-item" href="{{ route('acl.approvers') }}">
        <i class="fas fa-user-shield me-2"></i>Manage Approvers
      </a>
    </li>
    @endif

    {{-- Admin Notifications --}}
    @if($isAdmin && ($pendingResearcherCount > 0 || $pendingBookingCount > 0))
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header"><i class="fas fa-bell me-1"></i>Notifications</h6></li>
    @if($pendingResearcherCount > 0)
    <li>
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ url('/research/researchers') }}">
        <span><i class="fas fa-user-clock me-2"></i>Pending Researchers</span>
        <span class="badge bg-warning text-dark">{{ $pendingResearcherCount }}</span>
      </a>
    </li>
    @endif
    @if($pendingBookingCount > 0)
    <li>
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ url('/research/bookings') }}">
        <span><i class="fas fa-calendar-check me-2"></i>Pending Bookings</span>
        <span class="badge bg-danger">{{ $pendingBookingCount }}</span>
      </a>
    </li>
    @endif
    @endif

    {{-- Logout --}}
    <li><hr class="dropdown-divider"></li>
    <li>
      @php $authMode = config('auth.external_mode'); @endphp
      @if ($authMode === 'cas')
        <a href="{{ route('cas.logout') }}" class="dropdown-item text-danger">
          <i class="fas fa-sign-out-alt me-2"></i>Logout
        </a>
      @elseif ($authMode === 'oidc')
        <a href="{{ route('oidc.logout') }}" class="dropdown-item text-danger">
          <i class="fas fa-sign-out-alt me-2"></i>Logout
        </a>
      @else
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="dropdown-item text-danger">
            <i class="fas fa-sign-out-alt me-2"></i>Logout
          </button>
        </form>
      @endif
    </li>

  </ul>
</div>

@else
{{-- Unauthenticated: login dropdown --}}
@php
  $authMode = config('auth.external_mode'); // null, 'cas', or 'oidc'
@endphp

@if ($authMode === 'cas')
  {{-- CAS login mode - ported from ahgThemeB5Plugin/_userMenu.mod_cas.php --}}
  <div class="dropdown my-2">
    <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
      <i class="fas fa-sign-in-alt me-1"></i>Log in
    </button>
    <div class="dropdown-menu dropdown-menu-lg-end mt-2" aria-labelledby="user-menu">
      <div>
        <h6 class="dropdown-header">{{ __('Have an account?') }}</h6>
      </div>
      <form method="POST" action="{{ route('cas.login') }}" class="mx-3 my-2">
        @csrf
        <button class="btn btn-sm atom-btn-secondary" type="submit">
          {{ __('Log in with CAS') }}
        </button>
      </form>
    </div>
  </div>

@elseif ($authMode === 'oidc')
  {{-- OIDC/SSO login mode - ported from ahgThemeB5Plugin/_userMenu.mod_ext_auth.php --}}
  <div class="dropdown my-2">
    <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
      <i class="fas fa-sign-in-alt me-1"></i>Log in
    </button>
    <div class="dropdown-menu dropdown-menu-lg-end mt-2" aria-labelledby="user-menu">
      <div>
        <h6 class="dropdown-header">{{ __('Have an account?') }}</h6>
      </div>
      <form method="POST" action="{{ route('oidc.login') }}" class="mx-3 my-2">
        @csrf
        <button class="btn btn-sm atom-btn-secondary" type="submit">
          {{ __('Log in with SSO') }}
        </button>
      </form>
    </div>
  </div>

@else
  {{-- Standard login mode --}}
  <div class="dropdown my-2">
    <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
      <i class="fas fa-sign-in-alt me-1"></i>Log in
    </button>
    <div class="dropdown-menu dropdown-menu-lg-end mt-2 p-3" aria-labelledby="user-menu" style="min-width: 280px;">
      <h6 class="dropdown-header px-0">{{ __('Have an account?') }}</h6>
      <form method="POST" action="{{ route('login') }}" class="mt-2">
        @csrf
        <div class="mb-3">
          <label class="form-label" for="nav-email">Email<span aria-hidden="true" class="text-primary ms-1" title="{{ __('This field is required.') }}"><strong>*</strong></span><span class="visually-hidden">This field is required.</span></label>
          <input type="text" name="email" class="form-control-sm form-control" id="nav-email" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="nav-password">Password<span aria-hidden="true" class="text-primary ms-1" title="{{ __('This field is required.') }}"><strong>*</strong></span><span class="visually-hidden">This field is required.</span></label>
          <input type="password" name="password" class="form-control-sm form-control" id="nav-password" required autocomplete="off">
        </div>
        <button class="btn btn-sm atom-btn-secondary w-100 mt-2" type="submit">{{ __('Log in') }}</button>
      </form>

      {{-- Demo credentials — only on the AHG-branded heratio site, not on white-label client deployments.
           Gated by Show Branding (same toggle that controls "Powered by Heratio"). --}}
      @if($themeData['showBranding'] ?? true)
      <div class="alert alert-info py-2 px-2 mt-2 mb-2 small">
        <strong>Demo:</strong> <code>louise@theahg.co.za</code> / <code>Password@123</code>
      </div>
      @endif

      <div class="text-center mt-2">
        <a href="{{ route('password.reset') }}" class="small text-muted">
          <i class="fas fa-key me-1"></i>Forgot password?
        </a>
      </div>

      <hr class="my-3">
      <div class="text-center">
        <a href="{{ route('register') }}" class="btn btn-sm atom-btn-secondary w-100 mb-2">
          <i class="fas fa-user-plus me-1"></i>Register
        </a>
        <a href="{{ route('researcher.register') }}" class="btn btn-sm atom-btn-white w-100 mb-1">
          <i class="fas fa-user-graduate me-1"></i>Register as Researcher
        </a>
        <a href="{{ url('/research/dashboard') }}" class="small text-muted d-block mt-1">
          <i class="fas fa-book-reader me-1"></i>View Research Services
        </a>
      </div>
    </div>
  </div>
@endif
@endif
