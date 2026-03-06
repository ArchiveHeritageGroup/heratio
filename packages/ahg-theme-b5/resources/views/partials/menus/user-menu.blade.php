@php
  $user = $themeData['user'] ?? null;
  $isAuthenticated = $themeData['isAuthenticated'] ?? false;
@endphp

@if($isAuthenticated && $user)
  {{-- Authenticated: user profile dropdown --}}
  <div class="dropdown my-2 ms-lg-2">
    <button class="btn btn-sm atom-btn-secondary dropdown-toggle d-flex align-items-center" type="button" id="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-user me-1" aria-hidden="true"></i>
      {{ $user->getDisplayName() }}
    </button>
    <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="user-menu">
      <li><h6 class="dropdown-header">{{ $user->email }}</h6></li>
      <li><a class="dropdown-item" href="{{ url('/user/profile') }}"><i class="fas fa-id-card me-2"></i>Profile</a></li>
      <li><a class="dropdown-item" href="{{ url('/user/password') }}"><i class="fas fa-key me-2"></i>Change password</a></li>
      <li><hr class="dropdown-divider"></li>
      <li>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="dropdown-item"><i class="fas fa-sign-out-alt me-2"></i>Log out</button>
        </form>
      </li>
    </ul>
  </div>
@else
  {{-- Unauthenticated: login dropdown --}}
  <div class="dropdown my-2 ms-lg-2">
    <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-sign-in-alt me-1" aria-hidden="true"></i>
      Log in
    </button>
    <div class="dropdown-menu dropdown-menu-end mt-2 p-3" style="min-width: 280px;" aria-labelledby="user-menu">
      <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-2">
          <input type="text" class="form-control form-control-sm" name="email" placeholder="Email or username" required>
        </div>
        <div class="mb-2">
          <input type="password" class="form-control form-control-sm" name="password" placeholder="Password" required>
        </div>
        <button type="submit" class="btn btn-sm btn-primary w-100">Log in</button>
      </form>
    </div>
  </div>
@endif
