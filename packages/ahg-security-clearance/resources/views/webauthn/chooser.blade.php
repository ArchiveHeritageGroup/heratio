@extends('ahg-theme-b5::layout')

@section('title', __('Choose a sign-in method'))

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="bi bi-shield-lock"></i> {{ __('Choose a second-factor method') }}</h4>
        </div>
        <div class="card-body">
          <p class="text-muted text-center">
            {{ __('You have enrolled both an authenticator app and a passkey. Pick one to complete sign-in.') }}
          </p>

          <div class="row g-3 mt-3">
            <div class="col-md-6">
              <a href="{{ route('security-clearance.webauthn.verify', ['return' => $returnUrl]) }}"
                 class="btn btn-primary btn-lg w-100 py-4">
                <i class="bi bi-key-fill fs-2 d-block mb-2"></i>
                {{ __('Passkey') }}
                <div class="small mt-1">{{ __('Hardware key, Touch ID, Windows Hello, etc.') }}</div>
              </a>
            </div>
            <div class="col-md-6">
              <a href="{{ route('security-clearance.two-factor', ['return' => $returnUrl]) }}?force_totp=1"
                 class="btn btn-outline-primary btn-lg w-100 py-4">
                <i class="bi bi-phone fs-2 d-block mb-2"></i>
                {{ __('Authenticator code') }}
                <div class="small mt-1">{{ __('6-digit code from your TOTP app or recovery code') }}</div>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
