{{--
  Data Protection — POPIA / GDPR compliance and data handling settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('data_protection')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Data Protection')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-user-shield me-2"></i>Data Protection</h1>
<p class="text-muted">POPIA / GDPR compliance and data handling</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.data_protection') }}">
    @csrf

    {{-- Card 1: Data Protection Compliance --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Data Protection Compliance</h5>
      </div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="dp_enabled"
                     name="dp_enabled" value="1"
                     {{ ($settings['dp_enabled'] ?? 'true') === 'true' || ($settings['dp_enabled'] ?? '') === '1' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="dp_enabled">{{ __('Enable Data Protection Module') }}</label>
            </div>
            <div class="form-text">Enable data protection module</div>
          </div>
          <div class="col-md-6">
            <label for="dp_default_regulation" class="form-label fw-bold">{{ __('Default Regulation') }}</label>
            <select class="form-select" id="dp_default_regulation" name="dp_default_regulation">
              @php $curReg = $settings['dp_default_regulation'] ?? 'popia'; @endphp
              <option value="popia" {{ $curReg === 'popia' ? 'selected' : '' }}>{{ __('POPIA (South Africa)') }}</option>
              <option value="gdpr" {{ $curReg === 'gdpr' ? 'selected' : '' }}>{{ __('GDPR (European Union)') }}</option>
              <option value="paia" {{ $curReg === 'paia' ? 'selected' : '' }}>{{ __('PAIA (South Africa)') }}</option>
              <option value="ccpa" {{ $curReg === 'ccpa' ? 'selected' : '' }}>{{ __('CCPA (California)') }}</option>
            </select>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="dp_notify_overdue"
                     name="dp_notify_overdue" value="1"
                     {{ ($settings['dp_notify_overdue'] ?? 'true') === 'true' || ($settings['dp_notify_overdue'] ?? '') === '1' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="dp_notify_overdue">{{ __('Notify Overdue') }}</label>
            </div>
            <div class="form-text">Send email notifications for overdue requests</div>
          </div>
          <div class="col-md-6">
            <label for="dp_notify_email" class="form-label fw-bold">{{ __('Notification Email') }}</label>
            <input type="email" class="form-control" id="dp_notify_email" name="dp_notify_email"
                   value="{{ $settings['dp_notify_email'] ?? '' }}" placeholder="{{ __('dpo@example.com') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Card 2: POPIA/PAIA Settings --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-gavel me-2"></i>POPIA / PAIA Settings</h5>
      </div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="dp_popia_fee" class="form-label fw-bold">{{ __('POPIA Request Fee') }}</label>
            <div class="input-group">
              <span class="input-group-text">R</span>
              <input type="number" class="form-control" id="dp_popia_fee" name="dp_popia_fee"
                     value="{{ $settings['dp_popia_fee'] ?? '50' }}" min="0" step="0.01">
            </div>
            <div class="form-text">Standard request fee (R50 per regulation)</div>
          </div>
          <div class="col-md-4">
            <label for="dp_popia_fee_special" class="form-label fw-bold">{{ __('Special Category Fee') }}</label>
            <div class="input-group">
              <span class="input-group-text">R</span>
              <input type="number" class="form-control" id="dp_popia_fee_special" name="dp_popia_fee_special"
                     value="{{ $settings['dp_popia_fee_special'] ?? '140' }}" min="0" step="0.01">
            </div>
            <div class="form-text">Fee for special categories of personal info (R140)</div>
          </div>
          <div class="col-md-4">
            <label for="dp_popia_response_days" class="form-label fw-bold">{{ __('Response Days') }}</label>
            <div class="input-group">
              <input type="number" class="form-control" id="dp_popia_response_days" name="dp_popia_response_days"
                     value="{{ $settings['dp_popia_response_days'] ?? '30' }}" min="1" max="90">
              <span class="input-group-text">days</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Save --}}
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
