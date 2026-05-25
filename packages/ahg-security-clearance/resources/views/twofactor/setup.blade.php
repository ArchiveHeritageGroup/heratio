@extends('ahg-theme-b5::layout')

@section('title', 'Setup Two-Factor Authentication')

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="fas fa-shield-alt"></i> {{ __('Setup Two-Factor Authentication') }}</h4>
        </div>
        <div class="card-body">
          @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif

          <div class="text-center mb-4">
            <p>{{ __('Scan the QR code below with your authenticator app (Google Authenticator, Authy, 1Password, etc.).') }}</p>

            <div class="border rounded p-3 d-inline-block mb-3 bg-white">
              <img src="{{ $qrSvgDataUri }}" alt="{{ __('TOTP enrolment QR code') }}"
                   style="width:220px; height:220px;" />
            </div>

            <p class="small text-muted mb-1">{{ __("Can't scan? Enter this key manually:") }}</p>
            <code class="d-block p-2 bg-light border rounded user-select-all"
                  style="letter-spacing: 2px; font-size: 1.1em; word-break: break-all;">
              {{ $secret }}
            </code>
          </div>

          <hr>

          <form method="POST" action="{{ route('security-clearance.confirm-2fa') }}">
            @csrf
            <input type="hidden" name="return" value="{{ $returnUrl }}">

            <div class="mb-3">
              <label class="form-label">{{ __('Enter the 6-digit code from your app to confirm setup:') }}</label>
              <input type="text" name="code" class="form-control form-control-lg text-center" maxlength="6"
                     pattern="[0-9]{6}" placeholder="000000" autofocus required
                     style="letter-spacing: 0.5em; font-size: 1.5em;">
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg">{{ __('Confirm Setup') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
