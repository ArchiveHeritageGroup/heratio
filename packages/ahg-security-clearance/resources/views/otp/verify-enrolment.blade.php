@extends('ahg-theme-b5::layout')

@section('title', __('Verify your factor'))

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="bi bi-shield-check"></i> {{ __('Verify your factor') }}</h4>
        </div>
        <div class="card-body">

          @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif
          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif
          @if(session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
          @endif

          <p class="text-muted text-center">
            @if($factor->factor_type === 'email')
              {{ __('Enter the 6-digit code we emailed to your address to confirm you own it.') }}
            @else
              {{ __('Enter the 6-digit code we just sent by SMS to confirm you own this phone number.') }}
            @endif
          </p>

          <div class="alert alert-secondary text-center mb-3">
            <strong>{{ $factor->label }}</strong>
            <div class="small">
              <i class="bi bi-{{ $factor->factor_type === 'email' ? 'envelope' : 'phone' }}"></i>
              {{ $factor->factor_type === 'email' ? __('Email') : __('SMS') }}
              -
              {{ $factor->destination }}
            </div>
          </div>

          <form method="POST" action="{{ route('security-clearance.otp.verify-enrolment-submit', $factor->id) }}">
            @csrf
            <input type="hidden" name="return" value="{{ $returnUrl }}">

            <div class="mb-3">
              <label class="form-label">{{ __('Verification code') }}</label>
              <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                     autocomplete="one-time-code" autofocus required
                     class="form-control form-control-lg text-center"
                     placeholder="000000"
                     style="letter-spacing: 0.4em; font-size: 1.5em;">
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg">{{ __('Verify') }}</button>
            </div>
          </form>

          <hr>

          <form method="POST" action="{{ route('security-clearance.otp.resend-enrolment', $factor->id) }}" class="text-center">
            @csrf
            <input type="hidden" name="return" value="{{ $returnUrl }}">
            <button type="submit" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-arrow-clockwise"></i> {{ __('Resend code') }}
            </button>
            <div class="form-text">{{ __('Rate limit: at most one code per 60 seconds.') }}</div>
          </form>

          <div class="text-center mt-3">
            <a href="{{ route('security-clearance.otp.list', ['return' => $returnUrl]) }}" class="btn btn-link btn-sm">
              {{ __('Cancel') }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
