@extends('theme::layouts.1col')

@section('title', 'Change Password - ' . ($themeData['siteTitle'] ?? 'Heratio'))
@section('body-class', 'user password-edit')

@section('content')

  <h1>
    <i class="fas fa-key me-2"></i>
    User {{ $user->username }}
  </h1>

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('user.password.update') }}">
    @csrf
    @method('PUT')

    {{-- Reset password accordion --}}
    <div class="accordion mb-3" id="passwordAccordion">
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingPassword">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePassword" aria-expanded="true" aria-controls="collapsePassword">
            <i class="fas fa-lock me-2"></i> Reset password
          </button>
        </h2>
        <div id="collapsePassword" class="accordion-collapse collapse show" aria-labelledby="headingPassword" data-bs-parent="#passwordAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="current_password" class="form-label">Current password</label>
              <input type="password" class="form-control @error('current_password') is-invalid @enderror"
                     id="current_password" name="current_password"
                     required autocomplete="current-password">
              @error('current_password')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">New password</label>
              <input type="password" class="form-control @error('password') is-invalid @enderror"
                     id="password" name="password"
                     required autocomplete="new-password" minlength="8"
                     data-strength="true">
              <div class="form-text">
                Minimum 8 characters. Use a mix of letters, numbers, and symbols for a strong password.
              </div>
              @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="password_confirmation" class="form-label">Confirm new password</label>
              <input type="password" class="form-control"
                     id="password_confirmation" name="password_confirmation"
                     required autocomplete="new-password" minlength="8">
            </div>

          </div>
        </div>
      </div>
    </div>

    {{-- Buttons --}}
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Save
      </button>
      <a href="{{ route('user.profile') }}" class="btn btn-outline-secondary">
        Cancel
      </a>
    </div>

  </form>

@endsection
