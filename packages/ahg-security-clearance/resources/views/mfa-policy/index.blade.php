@extends('ahg-theme-b5::layout')

@section('title', __('MFA Enforcement Policy'))

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/admin">{{ __('Admin') }}</a></li>
      <li class="breadcrumb-item"><a href="/admin/security-clearance/dashboard">{{ __('Security') }}</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ __('MFA Enforcement') }}</li>
    </ol>
  </nav>

  <h1><i class="bi bi-shield-lock-fill"></i> {{ __('MFA Enforcement Policy') }}</h1>
  <p class="text-muted">
    {{ __('Layered on top of the opt-in factors (TOTP, WebAuthn, email/SMS OTP) shipped in #690 / #721 / #722. A tenant can require enrolment for some or all users, with a grace window before redirect.') }}
  </p>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong><i class="bi bi-globe2"></i> {{ __('Global default') }}</strong>
      <a href="{{ route('security-clearance.mfa-policy.edit', ['tenantId' => 0]) }}"
         class="btn btn-sm btn-outline-primary">
        <i class="bi bi-pencil"></i> {{ __('Edit') }}
      </a>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">{{ __('Enforcement') }}</dt>
        <dd class="col-sm-9">
          <span class="badge bg-secondary">{{ $global->enforcement }}</span>
          @if ($global->is_synthetic)
            <span class="text-muted small ms-2">({{ __('hardcoded fallback - no row in DB yet') }})</span>
          @endif
        </dd>
        <dt class="col-sm-3">{{ __('Grace period') }}</dt>
        <dd class="col-sm-9">{{ $global->grace_period_days }} {{ __('days') }}</dd>
      </dl>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <strong><i class="bi bi-buildings"></i> {{ __('Per-tenant policies') }}</strong>
    </div>
    <div class="card-body p-0">
      @if (empty($tenants))
        <div class="p-3 text-muted">
          {{ __('No tenants found. The global default applies to every user.') }}
        </div>
      @else
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>{{ __('Tenant') }}</th>
              <th>{{ __('Enforcement') }}</th>
              <th>{{ __('Grace period') }}</th>
              <th>{{ __('Source') }}</th>
              <th class="text-end">{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($tenants as $t)
              <tr>
                <td>
                  <strong>{{ $t->tenant_name }}</strong>
                  <small class="text-muted d-block">{{ $t->tenant_code }}</small>
                </td>
                <td><span class="badge bg-primary">{{ $t->enforcement }}</span></td>
                <td>{{ $t->grace_period_days }} {{ __('days') }}</td>
                <td>
                  @if ($t->is_global_default)
                    <em class="text-muted">{{ __('Global default') }}</em>
                  @else
                    <span class="text-success"><i class="bi bi-check-circle"></i> {{ __('Tenant override') }}</span>
                  @endif
                </td>
                <td class="text-end">
                  <a href="{{ route('security-clearance.mfa-policy.edit', ['tenantId' => $t->tenant_id]) }}"
                     class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil"></i> {{ __('Edit') }}
                  </a>
                  @if (! $t->is_global_default)
                    <form action="{{ route('security-clearance.mfa-policy.reset', ['tenantId' => $t->tenant_id]) }}"
                          method="POST" class="d-inline"
                          onsubmit="return confirm('{{ __('Reset this tenant to the global default policy?') }}');">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> {{ __('Reset to global') }}
                      </button>
                    </form>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  </div>
</div>
@endsection
