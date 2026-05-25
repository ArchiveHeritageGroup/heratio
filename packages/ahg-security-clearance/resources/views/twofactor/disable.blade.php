@extends('ahg-theme-b5::layout')

@section('title', __('Disable Two-Factor Authentication'))

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="fas fa-shield-alt text-warning"></i> {{ __('Disable Two-Factor') }}</h4>
        </div>
        <div class="card-body">
          @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif

          <div class="alert alert-warning">
            {{ __('Disabling two-factor authentication removes the second factor protecting your account. You can re-enable it at any time from your profile.') }}
          </div>

          <p class="text-muted">{{ __('To confirm, enter a current 6-digit code from your authenticator app or one of your recovery codes.') }}</p>

          <form method="POST" action="{{ route('security-clearance.disable-2fa.confirm') }}">
            @csrf

            <div class="mb-3">
              <label class="form-label">{{ __('Verification or recovery code') }}</label>
              <input type="text" name="code" class="form-control form-control-lg text-center" maxlength="16"
                     pattern="[0-9A-Za-z\-]{6,16}" placeholder="000000" autofocus required
                     autocomplete="one-time-code"
                     style="letter-spacing: 0.3em; font-size: 1.25em;">
            </div>

            <div class="d-flex gap-2">
              <a href="/user/profile" class="btn btn-outline-secondary flex-fill">{{ __('Cancel') }}</a>
              <button type="submit" class="btn btn-danger flex-fill">{{ __('Disable two-factor') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
