{{--
  Multi-Tenancy — repository-based multi-tenancy settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('multi_tenant')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Multi-Tenancy')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-building me-2"></i>{{ __('Multi-Tenancy') }}</h1>
<p class="text-muted">Repository-based multi-tenancy with user hierarchy</p>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.multi_tenant') }}">
    @csrf

    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-building me-2"></i>{{ __('Multi-Tenancy Configuration') }}</h5>
      </div>
      <div class="card-body">
        <p class="text-muted mb-4">Configure repository-based multi-tenancy. Each repository acts as a tenant with isolated user access and custom branding.</p>

        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="tenant_enabled"
                       name="settings[tenant_enabled]" value="true"
                       {{ ($settings['tenant_enabled'] ?? '') === 'true' ? 'checked' : '' }}>
                <label class="form-check-label" for="tenant_enabled"><strong>{{ __('Enable Multi-Tenancy') }}</strong></label>
              </div>
              <div class="form-text">Enable repository-based access control and filtering.</div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="tenant_enforce_filter"
                       name="settings[tenant_enforce_filter]" value="true"
                       {{ ($settings['tenant_enforce_filter'] ?? '') === 'true' ? 'checked' : '' }}>
                <label class="form-check-label" for="tenant_enforce_filter"><strong>{{ __('Enforce Repository Filtering') }}</strong></label>
              </div>
              <div class="form-text">Automatically filter browse/search results by current tenant.</div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="tenant_show_switcher"
                       name="settings[tenant_show_switcher]" value="true"
                       {{ ($settings['tenant_show_switcher'] ?? 'true') === 'true' ? 'checked' : '' }}>
                <label class="form-check-label" for="tenant_show_switcher"><strong>{{ __('Show Tenant Switcher') }}</strong></label>
              </div>
              <div class="form-text">Display the repository switcher dropdown in the navigation bar.</div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="tenant_allow_branding"
                       name="settings[tenant_allow_branding]" value="true"
                       {{ ($settings['tenant_allow_branding'] ?? 'true') === 'true' ? 'checked' : '' }}>
                <label class="form-check-label" for="tenant_allow_branding"><strong>{{ __('Allow Per-Tenant Branding') }}</strong></label>
              </div>
              <div class="form-text">Allow super users to customize colors and logos for their repositories.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>{{ __('Save') }}
      </button>
    </div>
  </form>
@endsection
