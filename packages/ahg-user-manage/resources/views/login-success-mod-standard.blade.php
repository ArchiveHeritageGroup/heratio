@extends('theme::layout_1col')

@section('content')
  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <p>{{ $error }}</p>
      @endforeach
    </div>
  @endif

  <form method="post" action="{{ route('user.login') }}">
    @csrf
    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="login-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#login-collapse" aria-expanded="true" aria-controls="login-collapse">
            @if(request()->route() && request()->route()->getName() !== 'user.login')
              {{ __('Please log in to access that page') }}
            @else
              {{ __('Log in') }}
            @endif
          </button>
        </h2>
        <div id="login-collapse" class="accordion-collapse collapse show" aria-labelledby="login-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="email" class="form-label">{{ __('Email') }}</label>
              <input type="email" name="email" id="email" class="form-control" autofocus required value="{{ old('email') }}">
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">{{ __('Password') }}</label>
              <input type="password" name="password" id="password" class="form-control" autocomplete="off" required>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="alert alert-info py-2 mb-3">
      <i class="fas fa-info-circle me-1"></i><strong>{{ __('Demo') }}:</strong>
      <code>louise@theahg.co.za</code> / <code>Password@123</code>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <button type="submit" class="btn atom-btn-outline-success">
        <i class="fas fa-sign-in-alt me-1"></i>{{ __('Log in') }}
      </button>
      <a href="{{ route('user.passwordReset') }}" class="text-muted">
        <i class="fas fa-key me-1"></i>{{ __('Forgot password?') }}
      </a>
    </div>
  </form>

  <hr class="my-4">

  @php
    $hasRegistration = \Illuminate\Support\Facades\Route::has('user.register');
    $hasResearch = \Illuminate\Support\Facades\Route::has('research.workspace');
  @endphp

  @if($hasRegistration)
  <!-- User Registration -->
  <div class="card border-primary mb-3">
    <div class="card-body text-center">
      <h5 class="card-title"><i class="fas fa-user-plus text-primary me-2"></i>{{ __('New User?') }}</h5>
      <p class="card-text text-muted">
        {{ __('Register for an account to access archival materials and services.') }}
      </p>
      <a href="{{ route('user.register') }}" class="btn atom-btn-white">
        <i class="fas fa-user-plus me-2"></i>{{ __('Register') }}
      </a>
    </div>
  </div>
  @endif

  @if($hasResearch)
  <!-- Researcher Registration -->
  <div class="card border-success mb-3">
    <div class="card-body text-center">
      <h5 class="card-title"><i class="fas fa-user-graduate text-success me-2"></i>{{ __('New Researcher?') }}</h5>
      <p class="card-text text-muted">
        {{ __('Register to access the reading room, request archival materials, and save your research.') }}
      </p>
      <a href="{{ route('research.publicRegister') }}" class="btn atom-btn-white">
        <i class="fas fa-user-plus me-2"></i>{{ __('Register as Researcher') }}
      </a>
    </div>
  </div>

  <!-- Research Services Link -->
  <div class="text-center mt-3">
    <a href="{{ route('research.dashboard') }}" class="text-muted">
      <i class="fas fa-book-reader me-1"></i>{{ __('View Research Services') }}
    </a>
  </div>
  @endif
@endsection
