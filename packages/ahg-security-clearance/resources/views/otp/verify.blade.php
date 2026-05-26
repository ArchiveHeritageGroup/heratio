@extends('ahg-theme-b5::layout')

@section('title', __('Email or SMS verification'))

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="bi bi-envelope-paper"></i> {{ __('Email or SMS verification') }}</h4>
        </div>
        <div class="card-body">

          @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif
          @if(session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
          @endif

          @if($factors->isEmpty())
            <div class="alert alert-warning text-center">
              {{ __('No verified email or SMS factors found on your account.') }}
            </div>
            <a href="{{ route('security-clearance.two-factor', ['return' => $returnUrl]) }}" class="btn btn-outline-secondary w-100">
              {{ __('Back to second-factor sign-in') }}
            </a>
          @else
            <p class="text-muted text-center">
              {{ __('Pick a destination, request a code, then type the 6 digits you receive.') }}
            </p>

            <form method="POST" action="{{ route('security-clearance.otp.assert-begin') }}" class="mb-4">
              @csrf
              <input type="hidden" name="return" value="{{ $returnUrl }}">

              <div class="mb-3">
                <label class="form-label">{{ __('Destination') }}</label>
                <select name="factor" class="form-select form-select-lg">
                  @foreach($factors as $f)
                    <option value="{{ $f->id }}" {{ $selectedFactorId == $f->id ? 'selected' : '' }}>
                      {{ $f->factor_type === 'email' ? 'Email' : 'SMS' }} - {{ $f->label }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="d-grid">
                <button type="submit" class="btn btn-outline-primary btn-lg">
                  <i class="bi bi-send"></i>
                  {{ $codeSent ? __('Resend code') : __('Send code') }}
                </button>
              </div>
            </form>

            <hr>

            <form method="POST" action="{{ route('security-clearance.otp.assert-complete') }}">
              @csrf
              <input type="hidden" name="return" value="{{ $returnUrl }}">
              <input type="hidden" name="factor" value="{{ $selectedFactorId }}">

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
          @endif

          <hr>

          <div class="text-center small">
            <a href="{{ route('security-clearance.two-factor', ['return' => $returnUrl]) }}">
              {{ __('Use a different second-factor method') }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
