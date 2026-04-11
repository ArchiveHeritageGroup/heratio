{{--
  Spectrum / Collections — collections management settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('spectrum')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Spectrum / Collections')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-archive me-2"></i>Spectrum / Collections</h1>
<p class="text-muted">Spectrum collections management procedures</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.spectrum') }}">
    @csrf

    {{-- Collections Management --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-archive me-2"></i>Collections Management</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="spectrum_enabled"
                     name="settings[spectrum_enabled]" value="true"
                     {{ ($settings['spectrum_enabled'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="spectrum_enabled">Enable Spectrum</label>
            </div>
            <div class="form-text">Enable Spectrum collections management</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="spectrum_default_currency">Default Currency</label>
            <select class="form-select" id="spectrum_default_currency" name="settings[spectrum_default_currency]">
              @foreach (['ZAR' => 'South African Rand (ZAR)', 'USD' => 'US Dollar (USD)', 'EUR' => 'Euro (EUR)', 'GBP' => 'British Pound (GBP)'] as $code => $name)
                <option value="{{ $code }}" {{ ($settings['spectrum_default_currency'] ?? 'ZAR') === $code ? 'selected' : '' }}>{{ $name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <label class="form-label" for="spectrum_valuation_reminder_days">Valuation Reminder</label>
            <div class="input-group">
              <input type="number" class="form-control" id="spectrum_valuation_reminder_days"
                     name="settings[spectrum_valuation_reminder_days]"
                     value="{{ $settings['spectrum_valuation_reminder_days'] ?? 365 }}" min="30" max="1825">
              <span class="input-group-text">days</span>
            </div>
            <div class="form-text">Remind to re-value after this many days</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="spectrum_loan_default_period">Default Loan Period</label>
            <div class="input-group">
              <input type="number" class="form-control" id="spectrum_loan_default_period"
                     name="settings[spectrum_loan_default_period]"
                     value="{{ $settings['spectrum_loan_default_period'] ?? 90 }}" min="1" max="365">
              <span class="input-group-text">days</span>
            </div>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <label class="form-label" for="spectrum_condition_check_interval">Condition Check Interval</label>
            <div class="input-group">
              <input type="number" class="form-control" id="spectrum_condition_check_interval"
                     name="settings[spectrum_condition_check_interval]"
                     value="{{ $settings['spectrum_condition_check_interval'] ?? 180 }}" min="30" max="730">
              <span class="input-group-text">days</span>
            </div>
            <div class="form-text">Recommended interval between condition checks</div>
          </div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="spectrum_auto_create_movement"
                     name="settings[spectrum_auto_create_movement]" value="true"
                     {{ ($settings['spectrum_auto_create_movement'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="spectrum_auto_create_movement">Auto-create Movements</label>
            </div>
            <div class="form-text">Automatically create movement records on location change</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="spectrum_require_photos"
                     name="settings[spectrum_require_photos]" value="true"
                     {{ ($settings['spectrum_require_photos'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="spectrum_require_photos">Require Photos</label>
            </div>
            <div class="form-text">Require at least one photo for condition reports</div>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="spectrum_email_notifications"
                     name="settings[spectrum_email_notifications]" value="true"
                     {{ ($settings['spectrum_email_notifications'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="spectrum_email_notifications">Email Notifications</label>
            </div>
            <div class="form-text">Send email notifications for task assignments and state transitions. Requires SMTP to be configured in Email settings.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Save --}}
    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Settings
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>Save
      </button>
    </div>
  </form>
@endsection
