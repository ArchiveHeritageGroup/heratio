@php
  $user = $themeData['user'] ?? null;
  $isAuthenticated = $themeData['isAuthenticated'] ?? false;
  $isAdmin = $themeData['isAdmin'] ?? false;
@endphp

@if($isAuthenticated && $user)
{{-- Authenticated: user profile dropdown --}}
<div class="dropdown my-2">
  <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-user-circle me-1"></i>{{ $user->username }}
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

    {{-- Logout --}}
    <li><hr class="dropdown-divider"></li>
    <li>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="dropdown-item text-danger">
          <i class="fas fa-sign-out-alt me-2"></i>Logout
        </button>
      </form>
    </li>

  </ul>
</div>

@else
{{-- Unauthenticated: login dropdown --}}
<div class="dropdown my-2">
  <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
    <i class="fas fa-sign-in-alt me-1"></i>Log in
  </button>
  <div class="dropdown-menu dropdown-menu-lg-end mt-2 p-3" aria-labelledby="user-menu" style="min-width: 280px;">
    <h6 class="dropdown-header px-0">Have an account?</h6>
    <form method="POST" action="{{ route('login') }}" class="mt-2">
      @csrf
      <div class="mb-2">
        <input type="text" class="form-control form-control-sm" name="email" placeholder="Email or username" required>
      </div>
      <div class="mb-2">
        <input type="password" class="form-control form-control-sm" name="password" placeholder="Password" required autocomplete="off">
      </div>
      <button class="btn btn-sm atom-btn-secondary w-100 mt-2" type="submit">Log in</button>
    </form>

    <div class="text-center mt-2">
      <a href="{{ route('password.reset') }}" class="small text-muted">
        <i class="fas fa-key me-1"></i>Forgot password?
      </a>
    </div>

    <hr class="my-3">
    <div class="text-center">
      <a href="{{ route('register') }}" class="btn btn-sm btn-primary w-100 mb-2">
        <i class="fas fa-user-plus me-1"></i>Register
      </a>
      <a href="{{ route('researcher.register') }}" class="btn btn-sm btn-success w-100 mb-1">
        <i class="fas fa-user-graduate me-1"></i>Register as Researcher
      </a>
      <a href="{{ url('/research/dashboard') }}" class="small text-muted d-block mt-1">
        <i class="fas fa-book-reader me-1"></i>View Research Services
      </a>
    </div>
  </div>
</div>
@endif
