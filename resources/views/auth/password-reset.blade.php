@extends('theme::layouts.1col')

@section('title', 'Reset Password - ' . ($themeData['siteTitle'] ?? 'Heratio'))
@section('body-class', 'user password-reset')

@section('content')
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">

      <div class="card shadow-sm mt-4">
        <div class="card-header">
          <div class="accordion-button collapsed p-0 bg-transparent border-0 shadow-none" style="cursor: default;">
            <i class="fas fa-key me-2"></i> Reset Password
          </div>
        </div>
        <div class="card-body p-4">

          @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              {{ session('success') }}
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

          <p class="text-muted mb-3">Enter your email address and we will send you instructions to reset your password.</p>

          <form method="POST" action="{{ route('password.reset') }}">
            @csrf

            <div class="mb-3">
              <label for="email" class="form-label">Email address</label>
              <input type="email" class="form-control" id="email" name="email"
                     value="{{ old('email') }}" required autofocus
                     autocomplete="email">
            </div>

            <div class="d-flex justify-content-between align-items-center">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane me-1"></i> Send Reset Instructions
              </button>
              <a href="{{ route('login') }}" class="text-decoration-none small">
                Cancel
              </a>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
@endsection
