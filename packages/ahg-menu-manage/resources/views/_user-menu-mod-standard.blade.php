@php /**
 * User menu with conditional plugin checks
 */
$currentUser = Auth::user();
$isAdmin = $currentUser && $currentUser->hasRole('administrator');
$isAuthenticated = Auth::check();
$userId = $isAuthenticated ? $currentUser->id : null;

// Check which routes are registered
$hasAccessRequest = \Illuminate\Support\Facades\Route::has('access_request.my');
$hasResearch = \Illuminate\Support\Facades\Route::has('research.workspace');
$hasSpectrum = \Illuminate\Support\Facades\Route::has('spectrum.myTasks');
$hasResearcher = \Illuminate\Support\Facades\Route::has('researcher.dashboard');
$hasRegistration = \Illuminate\Support\Facades\Route::has('user.register');

// Get pending counts only if plugins exist
$pendingCount = 0;
$pendingResearcherCount = 0;
$pendingBookingCount = 0;
$spectrumTaskCount = 0;

if ($isAuthenticated && $hasAccessRequest) {
    try {
        if ($isAdmin || \AhgCore\Services\AccessRequestService::isApprover($userId)) {
            $pendingCount = \Illuminate\Support\Facades\DB::table('access_request')
                ->where('status', 'pending')
                ->count();
        }
    } catch (Exception $e) {
        // Table may not exist
    }
}

if ($isAuthenticated && $hasResearch && $isAdmin) {
    try {
        $pendingResearcherCount = \Illuminate\Support\Facades\DB::table('research_researcher')
            ->where('status', 'pending')
            ->count();
        $pendingBookingCount = \Illuminate\Support\Facades\DB::table('research_booking')
            ->where('status', 'pending')
            ->count();
    } catch (Exception $e) {
        // Table may not exist
    }
}

// Get Spectrum task count for current user (exclude terminal states)
if ($isAuthenticated && $hasSpectrum) {
    try {
        $terminalStates = ['completed', 'resolved', 'disposed', 'reported'];
        $spectrumTaskCount = \Illuminate\Support\Facades\DB::table('spectrum_workflow_state')
            ->where('assigned_to', $userId)
            ->whereNotIn('current_state', $terminalStates)
            ->count();
    } catch (Exception $e) {
        // Table may not exist
    }
} @endphp

@if($showLogin)
<!-- Login dropdown for unauthenticated users -->
<div class="dropdown my-2">
  <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
    <i class="fas fa-sign-in-alt me-1"></i>{{ $menuLabels['login'] ?? __('Log in') }}
  </button>
  <div class="dropdown-menu dropdown-menu-lg-end mt-2 p-3" aria-labelledby="user-menu" style="min-width: 280px;">
    <h6 class="dropdown-header px-0">{{ __('Have an account?') }}</h6>
    <form method="post" action="{{ route('user.login') }}" class="mt-2">
      @csrf
      <div class="mb-3">
        <label for="menu-email" class="form-label form-control-sm">{{ __('Email') }}</label>
        <input type="email" name="email" id="menu-email" class="form-control form-control-sm">
      </div>
      <div class="mb-3">
        <label for="menu-password" class="form-label form-control-sm">{{ __('Password') }}</label>
        <input type="password" name="password" id="menu-password" class="form-control form-control-sm" autocomplete="off">
      </div>
      <button class="btn btn-sm atom-btn-secondary w-100 mt-2" type="submit">
        {{ $menuLabels['login'] ?? __('Log in') }}
      </button>
    </form>

    <div class="alert alert-info py-2 px-2 mt-2 mb-2 small">
      <strong>{{ __('Demo') }}:</strong> <code>louise@theahg.co.za</code> / <code>Password@123</code>
    </div>

    <div class="text-center mt-2">
      <a href="{{ route('user.passwordReset') }}" class="small text-muted">
        <i class="fas fa-key me-1"></i>{{ __('Forgot password?') }}
      </a>
    </div>

    @if($hasRegistration || $hasResearch)
    <hr class="my-3">
    <div class="text-center">
      @if($hasRegistration)
      <a href="{{ route('user.register') }}" class="btn btn-sm atom-btn-white w-100 mb-2">
        <i class="fas fa-user-plus me-1"></i>{{ __('Register') }}
      </a>
      @endif
      @if($hasResearch)
      <a href="{{ route('research.publicRegister') }}" class="btn btn-sm atom-btn-white w-100 mb-1">
        <i class="fas fa-user-graduate me-1"></i>{{ __('Register as Researcher') }}
      </a>
      <a href="{{ route('research.dashboard') }}" class="small text-muted d-block mt-1">
        <i class="fas fa-book-reader me-1"></i>{{ __('View Research Services') }}
      </a>
      @endif
    </div>
    @endif
  </div>
</div>

@elseif($isAuthenticated)
<!-- User menu for authenticated users -->
<div class="dropdown my-2">
  <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-user-circle me-1"></i>{{ $currentUser->username }}
  </button>
  <ul class="dropdown-menu dropdown-menu-lg-end mt-2" aria-labelledby="user-menu">

    <!-- Profile Section -->
    <li><h6 class="dropdown-header"><i class="fas fa-user me-1"></i>{{ __('Profile') }}</h6></li>
    <li>
      <a class="dropdown-item" href="{{ route('user.show', ['slug' => $currentUser->slug ?? $currentUser->id]) }}">
        <i class="fas fa-id-card me-2"></i>{{ $menuLabels['myProfile'] ?? __('My Profile') }}
      </a>
    </li>
    <li>
      <a class="dropdown-item" href="{{ route('user.passwordEdit') }}">
        <i class="fas fa-key me-2"></i>{{ __('Change Password') }}
      </a>
    </li>

    @if($hasSpectrum)
    <!-- Spectrum Tasks Section -->
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header"><i class="fas fa-tasks me-1"></i>{{ __('Tasks') }}</h6></li>
    <li>
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ route('ahgspectrum.my-tasks') }}">
        <span><i class="fas fa-clipboard-list me-2"></i>{{ __('My Tasks') }}</span>
        @if($spectrumTaskCount > 0)
        <span class="badge bg-danger">{{ $spectrumTaskCount }}</span>
        @endif
      </a>
    </li>
    <li>
      <a class="dropdown-item" href="{{ route('ahgspectrum.dashboard') }}">
        <span><i class="fas fa-tachometer-alt me-2"></i>{{ __('Workflow Dashboard') }}</span>
      </a>
    </li>
    @endif

    @if($hasResearch)
    <!-- Research Section -->
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header"><i class="fas fa-book-reader me-1"></i>{{ __('Research') }}</h6></li>
    <li>
      <a class="dropdown-item" href="{{ route('research.dashboard') }}">
        <i class="fas fa-folder-open me-2"></i>{{ __('My Workspace') }}
      </a>
    </li>
    @endif

    @if($hasResearcher)
    <!-- Researcher Submissions -->
    @if(!$hasResearch)<li><hr class="dropdown-divider"></li>@endif
    <li>
      <a class="dropdown-item" href="{{ route('researcher.dashboard') }}">
        <i class="fas fa-cloud-upload-alt me-2"></i>{{ __('My Submissions') }}
      </a>
    </li>
    @endif

    @if($hasAccessRequest)
    <!-- Security Section -->
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header"><i class="fas fa-shield-alt me-1"></i>{{ __('Security') }}</h6></li>
    <li>
      <a class="dropdown-item" href="{{ route('access_request.my') }}">
        <i class="fas fa-key me-2"></i>{{ __('My Access Requests') }}
      </a>
    </li>
    @if($isAdmin || \AhgCore\Services\AccessRequestService::isApprover($userId))
    <li>
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ route('access_request.pending') }}">
        <span><i class="fas fa-clock me-2"></i>{{ __('Pending Requests') }}</span>
        @if($pendingCount > 0)
        <span class="badge bg-warning text-dark">{{ $pendingCount }}</span>
        @endif
      </a>
    </li>
    @endif
    @if($isAdmin)
    <li>
      <a class="dropdown-item" href="{{ route('access_request.approvers') }}">
        <i class="fas fa-user-shield me-2"></i>{{ __('Manage Approvers') }}
      </a>
    </li>
    @endif
    @endif

    @if($isAdmin && $hasResearch && ($pendingResearcherCount > 0 || $pendingBookingCount > 0))
    <!-- Admin Notifications -->
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header"><i class="fas fa-bell me-1"></i>{{ __('Notifications') }}</h6></li>
    @if($pendingResearcherCount > 0)
    <li>
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ route('research.researchers') }}">
        <span><i class="fas fa-user-clock me-2"></i>{{ __('Pending Researchers') }}</span>
        <span class="badge bg-warning text-dark">{{ $pendingResearcherCount }}</span>
      </a>
    </li>
    @endif
    @if($pendingBookingCount > 0)
    <li>
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ route('research.bookings') }}">
        <span><i class="fas fa-calendar-check me-2"></i>{{ __('Pending Bookings') }}</span>
        <span class="badge bg-danger">{{ $pendingBookingCount }}</span>
      </a>
    </li>
    @endif
    @endif

    <!-- Logout -->
    <li><hr class="dropdown-divider"></li>
    <li>
      <a class="dropdown-item text-danger" href="{{ route('user.logout') }}">
        <i class="fas fa-sign-out-alt me-2"></i>{{ $menuLabels['logout'] ?? __('Logout') }}
      </a>
    </li>

  </ul>
</div>
@endif
