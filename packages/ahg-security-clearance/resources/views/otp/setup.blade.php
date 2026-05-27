@extends('ahg-theme-b5::layout')

@section('title', __('Add email or SMS factor'))

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="bi bi-envelope-paper"></i> {{ __('Add email or SMS factor') }}</h4>
        </div>
        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger">
              @foreach($errors->all() as $msg)
                <div>{{ $msg }}</div>
              @endforeach
            </div>
          @endif

          <p class="text-muted">
            {{ __('A 6-digit code will be sent to the address or number you enter. You will need to type the first code to confirm you own the destination.') }}
          </p>

          <form method="POST" action="{{ route('security-clearance.otp.enrol') }}">
            @csrf
            <input type="hidden" name="return" value="{{ $returnUrl }}">

            <div class="mb-3">
              <label class="form-label">{{ __('Channel') }}</label>
              <div class="btn-group w-100" role="group" aria-label="{{ __('OTP channel') }}">
                <input type="radio" class="btn-check" name="factor_type" id="ftEmail" value="email"
                       {{ old('factor_type', 'email') === 'email' ? 'checked' : '' }} required>
                <label class="btn btn-outline-primary" for="ftEmail">
                  <i class="bi bi-envelope-fill"></i> {{ __('Email') }}
                </label>

                <input type="radio" class="btn-check" name="factor_type" id="ftSms" value="sms"
                       {{ old('factor_type') === 'sms' ? 'checked' : '' }}>
                <label class="btn btn-outline-primary" for="ftSms">
                  <i class="bi bi-phone-fill"></i> {{ __('SMS') }}
                </label>
              </div>
            </div>

            <div class="mb-3">
              <label for="destination" class="form-label">{{ __('Destination') }}</label>
              <input type="text" id="destination" name="destination" class="form-control"
                     value="{{ old('destination', $defaultEmail ?? '') }}"
                     placeholder="{{ __('email@example.com  or  +27821234567') }}"
                     maxlength="255" required>
              <div class="form-text">
                {{ __('Email: any valid address. SMS: international format with + prefix (e.g. +27821234567).') }}
              </div>
            </div>

            <div class="mb-3">
              <label for="label" class="form-label">{{ __('Label (optional)') }}</label>
              <input type="text" id="label" name="label" class="form-control"
                     value="{{ old('label') }}"
                     placeholder="{{ __('e.g. Work email, Cellphone') }}" maxlength="120">
            </div>

            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-send"></i> {{ __('Send first code') }}
              </button>
              <a href="{{ route('security-clearance.otp.list', ['return' => $returnUrl]) }}" class="btn btn-outline-secondary">
                {{ __('Cancel') }}
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
