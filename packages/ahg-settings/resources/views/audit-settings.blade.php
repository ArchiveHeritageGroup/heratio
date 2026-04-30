@extends('theme::layouts.2col')
@section('title', 'Audit Trail Settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-history me-2"></i>Audit Trail Settings</h1>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="post" action="{{ route('settings.ahg.audit') }}">
    @csrf

    {{-- General Settings --}}
    <section class="card mb-4">
      <div class="card-header"><h5 class="mb-0">{{ __('General Settings') }}</h5></div>
      <div class="card-body">
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="audit_enabled" name="settings[audit_enabled]" value="1" {{ ($settings['audit_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="audit_enabled"><strong>Enable Audit Logging</strong></label>
        </div>
      </div>
    </section>

    {{-- What to Log --}}
    <section class="card mb-4">
      <div class="card-header"><h5 class="mb-0">{{ __('What to Log') }}</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            @foreach(['audit_views' => 'Log View Actions', 'audit_searches' => 'Log Search Queries', 'audit_downloads' => 'Log File Downloads'] as $key => $label)
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="{{ $key }}" name="settings[{{ $key }}]" value="1" {{ ($settings[$key] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
            </div>
            @endforeach
          </div>
          <div class="col-md-6">
            @foreach(['audit_api_requests' => 'Log API Requests', 'audit_authentication' => 'Log Authentication Events', 'audit_sensitive_access' => 'Log Classified Access'] as $key => $label)
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="{{ $key }}" name="settings[{{ $key }}]" value="1" {{ ($settings[$key] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
            </div>
            @endforeach
          </div>
        </div>
      </div>
    </section>

    {{-- Privacy Settings --}}
    <section class="card mb-4">
      <div class="card-header"><h5 class="mb-0">{{ __('Privacy Settings') }}</h5></div>
      <div class="card-body">
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="audit_mask_sensitive" name="settings[audit_mask_sensitive]" value="1" {{ ($settings['audit_mask_sensitive'] ?? '0') === '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="audit_mask_sensitive">{{ __('Mask Sensitive Data') }}</label>
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="audit_ip_anonymize" name="settings[audit_ip_anonymize]" value="1" {{ ($settings['audit_ip_anonymize'] ?? '0') === '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="audit_ip_anonymize">{{ __('Anonymize IP Addresses (POPIA)') }}</label>
        </div>
      </div>
    </section>

    <div class="d-flex justify-content-between">
      <a href="{{ route('settings.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Settings</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Settings</button>
    </div>
  </form>
@endsection
