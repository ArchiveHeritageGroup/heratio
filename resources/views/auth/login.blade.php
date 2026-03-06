@extends('theme::layouts.1col')

@section('title', 'Log in - ' . ($themeData['siteTitle'] ?? 'Heratio'))
@section('body-class', 'user login')

@section('content')

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {!! session('success') !!}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

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

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="login-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#login-collapse" aria-expanded="true" aria-controls="login-collapse">
            @if($message ?? null)
              {{ $message }}
            @else
              Log in
            @endif
          </button>
        </h2>
        <div id="login-collapse" class="accordion-collapse collapse show" aria-labelledby="login-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="text" class="form-control" id="email" name="email"
                     value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" id="password" name="password"
                     required autocomplete="off">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <button type="submit" class="btn atom-btn-outline-success">
        <i class="fas fa-sign-in-alt me-1"></i> Log in
      </button>
      <a href="{{ route('password.reset') }}" class="text-muted">
        <i class="fas fa-key me-1"></i> Forgot password?
      </a>
    </div>
  </form>

  <hr class="my-4">

  {{-- User Registration --}}
  <div class="card border-primary mb-3">
    <div class="card-body text-center">
      <h5 class="card-title"><i class="fas fa-user-plus text-primary me-2"></i>New User?</h5>
      <p class="card-text text-muted">
        Register for an account to access archival materials and services.
      </p>
      <a href="{{ route('register') }}" class="btn btn-primary">
        <i class="fas fa-user-plus me-2"></i> Register
      </a>
    </div>
  </div>

  {{-- Researcher Registration --}}
  <div class="card border-success mb-3">
    <div class="card-body text-center">
      <h5 class="card-title"><i class="fas fa-user-graduate text-success me-2"></i>New Researcher?</h5>
      <p class="card-text text-muted">
        Register to access the reading room, request archival materials, and save your research.
      </p>
      <a href="{{ route('researcher.register') }}" class="btn btn-success">
        <i class="fas fa-user-plus me-2"></i> Register as Researcher
      </a>
    </div>
  </div>

  {{-- Research Services Link --}}
  <div class="text-center mt-3">
    <a href="{{ url('/research/dashboard') }}" class="text-muted">
      <i class="fas fa-book-reader me-1"></i> View Research Services
    </a>
  </div>

@endsection
