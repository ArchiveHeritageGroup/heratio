@extends('theme::layouts.1col')

@section('title', 'Log in - ' . ($themeData['siteTitle'] ?? 'Heratio'))
@section('body-class', 'user login')

@section('content')
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">

      {{-- Login card --}}
      <div class="card shadow-sm mt-4">
        <div class="card-header">
          <div class="accordion-button collapsed p-0 bg-transparent border-0 shadow-none" style="cursor: default;">
            <i class="fas fa-sign-in-alt me-2"></i>
            @if($message ?? null)
              {{ $message }}
            @else
              Log in
            @endif
          </div>
        </div>
        <div class="card-body p-4">

          @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
              @endforeach
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          @endif

          <form method="POST" action="{{ route('login') }}">
            @csrf
            <input type="hidden" name="next" value="{{ $next ?? '' }}">

            <div class="mb-3">
              <label for="email" class="form-label">Email or username</label>
              <input type="text" class="form-control" id="email" name="email"
                     value="{{ old('email') }}" required autofocus
                     autocomplete="username">
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" id="password" name="password"
                     required autocomplete="current-password">
            </div>

            <div class="d-flex justify-content-between align-items-center">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-1"></i> Log in
              </button>
              <a href="{{ route('password.reset') }}" class="text-decoration-none small">
                Forgot password?
              </a>
            </div>
          </form>
        </div>
      </div>

      <hr class="my-4">

      {{-- Registration card (if route exists) --}}
      @if(Route::has('register'))
        <div class="card shadow-sm mb-3">
          <div class="card-body text-center">
            <p class="mb-2">Don't have an account?</p>
            <a href="{{ route('register') }}" class="btn btn-outline-primary">
              <i class="fas fa-user-plus me-1"></i> Register
            </a>
          </div>
        </div>
      @endif

      {{-- Researcher registration card (if route exists) --}}
      @if(Route::has('researcher.register'))
        <div class="card shadow-sm mb-3">
          <div class="card-body text-center">
            <p class="mb-2">Are you a researcher?</p>
            <a href="{{ route('researcher.register') }}" class="btn btn-outline-secondary">
              <i class="fas fa-graduation-cap me-1"></i> Researcher registration
            </a>
          </div>
        </div>
      @endif

    </div>
  </div>
@endsection
