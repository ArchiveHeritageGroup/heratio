@php /**
 * User menu with conditional plugin checks
 */
$userId = $sf_user->getUserID();
$isAdmin = $sf_user->isAdministrator();
$isAuthenticated = $sf_user->isAuthenticated();

// Check which plugins have routes registered
$routing = sfContext::getInstance()->getRouting();
$hasAccessRequest = $routing->hasRouteName('access_request_my');
$hasResearch = $routing->hasRouteName('research_workspace');
$hasSpectrum = $routing->hasRouteName('spectrum_my_tasks');
$hasResearcher = $routing->hasRouteName('researcher_dashboard');
$hasRegistration = $routing->hasRouteName('user_register');

// Get pending counts only if plugins exist
$pendingCount = 0;
$pendingResearcherCount = 0;
$pendingBookingCount = 0;
$spectrumTaskCount = 0;

if ($isAuthenticated && $hasAccessRequest) {
    try {
        if ($isAdmin || \AtomExtensions\Services\AccessRequestService::isApprover($userId)) {
            $pendingCount = \Illuminate\Database\Capsule\Manager::table('access_request')
                ->where('status', 'pending')
                ->count();
        }
    } catch (Exception $e) {
        // Table may not exist
    }
}

if ($isAuthenticated && $hasResearch && $isAdmin) {
    try {
        $pendingResearcherCount = \Illuminate\Database\Capsule\Manager::table('research_researcher')
            ->where('status', 'pending')
            ->count();
        $pendingBookingCount = \Illuminate\Database\Capsule\Manager::table('research_booking')
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
        $spectrumTaskCount = \Illuminate\Database\Capsule\Manager::table('spectrum_workflow_state')
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
    <i class="fas fa-sign-in-alt me-1"></i>@php echo $menuLabels['login'] ?? __('Log in'); @endphp
  </button>
  <div class="dropdown-menu dropdown-menu-lg-end mt-2 p-3" aria-labelledby="user-menu" style="min-width: 280px;">
    <h6 class="dropdown-header px-0">{{ __('Have an account?') }}</h6>
    @php echo $form->renderFormTag(route('user.login'), ['class' => 'mt-2']); @endphp
      @php echo $form->renderHiddenFields(); @endphp
      @php echo render_field($form->email, null, ['class' => 'form-control-sm']); @endphp
      @php echo render_field($form->password, null, ['class' => 'form-control-sm', 'autocomplete' => 'off']); @endphp
      <button class="btn btn-sm atom-btn-secondary w-100 mt-2" type="submit">
        @php echo $menuLabels['login'] ?? __('Log in'); @endphp
      </button>
    </form>

    <div class="alert alert-info py-2 px-2 mt-2 mb-2 small">
      <strong>{{ __('Demo') }}:</strong> <code>louise@theahg.co.za</code> / <code>Password@123</code>
    </div>

    <div class="text-center mt-2">
      <a href="@php echo route('user.passwordReset'); @endphp" class="small text-muted">
        <i class="fas fa-key me-1"></i>{{ __('Forgot password?') }}
      </a>
    </div>

    @if($hasRegistration || $hasResearch)
    <hr class="my-3">
    <div class="text-center">
      @if($hasRegistration)
      <a href="@php echo url_for('@user_register'); @endphp" class="btn btn-sm btn-primary w-100 mb-2">
        <i class="fas fa-user-plus me-1"></i>{{ __('Register') }}
      </a>
      @endif
      @if($hasResearch)
      <a href="@php echo route('research.publicRegister'); @endphp" class="btn btn-sm btn-success w-100 mb-1">
        <i class="fas fa-user-graduate me-1"></i>{{ __('Register as Researcher') }}
      </a>
      <a href="@php echo route('research.dashboard'); @endphp" class="small text-muted d-block mt-1">
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
    <i class="fas fa-user-circle me-1"></i>@php echo $sf_user->user->username; @endphp
  </button>
  <ul class="dropdown-menu dropdown-menu-lg-end mt-2" aria-labelledby="user-menu">

    <!-- Profile Section -->
    <li><h6 class="dropdown-header"><i class="fas fa-user me-1"></i>{{ __('Profile') }}</h6></li>
    <li>
      <a class="dropdown-item" href="@php echo url_for('user/' . $sf_user->getAttribute('user_slug')); @endphp">
        <i class="fas fa-id-card me-2"></i>@php echo $menuLabels['myProfile'] ?? __('My Profile'); @endphp
      </a>
    </li>
    <li>
      <a class="dropdown-item" href="@php echo route('user.passwordEdit'); @endphp">
        <i class="fas fa-key me-2"></i>{{ __('Change Password') }}
      </a>
    </li>

    @if($hasSpectrum)
    <!-- Spectrum Tasks Section -->
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header"><i class="fas fa-tasks me-1"></i>{{ __('Tasks') }}</h6></li>
    <li>
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="@php echo url_for('@spectrum_my_tasks'); @endphp">
        <span><i class="fas fa-clipboard-list me-2"></i>{{ __('My Tasks') }}</span>
        @if($spectrumTaskCount > 0)
        <span class="badge bg-danger">@php echo $spectrumTaskCount; @endphp</span>
        @endif
      </a>
    </li>
    <li>
      <a class="dropdown-item" href="@php echo url_for('@spectrum_dashboard'); @endphp">
        <span><i class="fas fa-tachometer-alt me-2"></i>{{ __('Workflow Dashboard') }}</span>
      </a>
    </li>
    @endif

    @if($hasResearch)
    <!-- Research Section -->
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header"><i class="fas fa-book-reader me-1"></i>{{ __('Research') }}</h6></li>
    <li>
      <a class="dropdown-item" href="@php echo url_for('@research_dashboard'); @endphp">
        <i class="fas fa-folder-open me-2"></i>{{ __('My Workspace') }}
      </a>
    </li>
    @endif

    @if($hasResearcher)
    <!-- Researcher Submissions -->
    @if(!$hasResearch)<li><hr class="dropdown-divider"></li>@endif
    <li>
      <a class="dropdown-item" href="@php echo route('researcher.dashboard'); @endphp">
        <i class="fas fa-cloud-upload-alt me-2"></i>{{ __('My Submissions') }}
      </a>
    </li>
    @endif

    @if($hasAccessRequest)
    <!-- Security Section -->
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header"><i class="fas fa-shield-alt me-1"></i>{{ __('Security') }}</h6></li>
    <li>
      <a class="dropdown-item" href="@php echo url_for('@access_request_my'); @endphp">
        <i class="fas fa-key me-2"></i>{{ __('My Access Requests') }}
      </a>
    </li>
    @if($isAdmin || \AtomExtensions\Services\AccessRequestService::isApprover($userId))
    <li>
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="@php echo url_for('@access_request_pending'); @endphp">
        <span><i class="fas fa-clock me-2"></i>{{ __('Pending Requests') }}</span>
        @if($pendingCount > 0)
        <span class="badge bg-warning text-dark">@php echo $pendingCount; @endphp</span>
        @endif
      </a>
    </li>
    @endif
    @if($isAdmin)
    <li>
      <a class="dropdown-item" href="@php echo url_for('@access_request_approvers'); @endphp">
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
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="@php echo url_for('@research_researchers'); @endphp">
        <span><i class="fas fa-user-clock me-2"></i>{{ __('Pending Researchers') }}</span>
        <span class="badge bg-warning text-dark">@php echo $pendingResearcherCount; @endphp</span>
      </a>
    </li>
    @endif
    @if($pendingBookingCount > 0)
    <li>
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="@php echo route('research.bookings'); @endphp">
        <span><i class="fas fa-calendar-check me-2"></i>{{ __('Pending Bookings') }}</span>
        <span class="badge bg-danger">@php echo $pendingBookingCount; @endphp</span>
      </a>
    </li>
    @endif
    @endif

    <!-- Logout -->
    <li><hr class="dropdown-divider"></li>
    <li>
      <a class="dropdown-item text-danger" href="@php echo route('user.logout'); @endphp">
        <i class="fas fa-sign-out-alt me-2"></i>@php echo $menuLabels['logout'] ?? __('Logout'); @endphp
      </a>
    </li>

  </ul>
</div>
@endif
