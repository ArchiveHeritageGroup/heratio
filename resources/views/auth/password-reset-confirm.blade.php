@extends('theme::layouts.1col')

@section('title', 'Reset Your Password - ' . ($themeData['siteTitle'] ?? 'Heratio'))
@section('body-class', 'user password-reset-confirm')

@section('content')
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">

      <div class="card shadow-sm mt-4">
        <div class="card-header">
          <div class="accordion-button collapsed p-0 bg-transparent border-0 shadow-none" style="cursor: default;">
            <i class="fas fa-lock me-2"></i> Reset Your Password
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

          <form method="POST" action="{{ route('password.reset.confirm', $token) }}">
            @csrf

            <div class="mb-3">
              <label for="password" class="form-label">New password</label>
              <input type="password" class="form-control" id="password" name="password"
                     required autofocus autocomplete="new-password"
                     minlength="8"
                     data-strength="true">
              <div class="form-text">
                Minimum 8 characters. Use a mix of letters, numbers, and symbols for a strong password.
              </div>
            </div>

            <div class="mb-3">
              <label for="password_confirmation" class="form-label">Confirm new password</label>
              <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
                     required autocomplete="new-password"
                     minlength="8">
            </div>

            <div class="d-flex justify-content-between align-items-center">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i> Reset Password
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
