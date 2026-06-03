@extends('ahg-theme-b5::layout')

@section('title', __('Edit MFA Policy'))

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-2">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/admin">{{ __('Admin') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('security-clearance.mfa-policy.index') }}">{{ __('MFA Enforcement') }}</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $tenantLabel }}</li>
    </ol>
  </nav>

  <h1><i class="bi bi-shield-lock-fill"></i> {{ __('MFA Policy') }}: {{ $tenantLabel }}</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card" style="max-width: 720px;">
    <div class="card-body">
      <form action="{{ route('security-clearance.mfa-policy.update', ['tenantId' => $tenantId ?? 0]) }}" method="POST">
        @csrf

        <div class="mb-3">
          <label for="enforcement" class="form-label fw-bold">{{ __('Enforcement') }}</label>
          <select class="form-select" id="enforcement" name="enforcement" required>
            @foreach ($enforcementOptions as $opt)
              <option value="{{ $opt['code'] }}" @selected(old('enforcement', $policy->enforcement) === $opt['code'])>
                {{ $opt['label'] }}
              </option>
            @endforeach
          </select>
          <div class="form-text">
            {{ __('Off') }}: {{ __('factor enrolment is disabled.') }}
            {{ __('Optional') }}: {{ __('user choice; nothing forced.') }}
            {{ __('Required for admins') }}: {{ __('admin + editor groups must enrol.') }}
            {{ __('Required for everyone') }}: {{ __('every signed-in user must enrol.') }}
          </div>
        </div>

        <div class="mb-3">
          <label for="grace_period_days" class="form-label fw-bold">{{ __('Grace period (days)') }}</label>
          <input type="number" class="form-control"
                 id="grace_period_days" name="grace_period_days"
                 min="0" max="365"
                 value="{{ old('grace_period_days', $policy->grace_period_days) }}" required>
          <div class="form-text">
            {{ __('Window during which a user can defer enrolment. The middleware shows a yellow banner instead of blocking. After this, the next request is redirected to the MFA setup page.') }}
          </div>
        </div>

        <div class="d-flex justify-content-between">
          <a href="{{ route('security-clearance.mfa-policy.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> {{ __('Cancel') }}
          </a>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> {{ __('Save policy') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
