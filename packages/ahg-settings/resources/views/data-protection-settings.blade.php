{{--
  Data Protection - POPIA / GDPR compliance and data handling settings
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
<h1><i class="fas fa-user-shield me-2"></i>{{ __('Data Protection') }}</h1>
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
        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>{{ __('Data Protection Compliance') }}</h5>
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
        <h5 class="mb-0"><i class="fas fa-gavel me-2"></i>{{ __('POPIA / PAIA Settings') }}</h5>
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

    {{-- PII scanning: what the scanner looks for --}}
    <div class="card mb-4">
      <div class="card-header fw-bold" style="background:#10373E;color:#fff">
        <i class="fas fa-search me-1"></i>{{ __('PII Scanning') }}
      </div>
      <div class="card-body">

        <div class="mb-3">
          <label for="privacy_jurisdiction" class="form-label fw-bold">{{ __('Scanning jurisdiction') }}</label>
          {{-- Options come from the privacy_jurisdiction registry, never from a
               list typed here - the hardcoded one had already drifted out of
               step with the table it was meant to mirror. --}}
          <select class="form-select" id="privacy_jurisdiction" name="privacy_jurisdiction">
            @foreach($jurisdictionOptions as $code => $label)
              <option value="{{ $code }}" @selected(($settings['privacy_jurisdiction'] ?? 'gdpr') === $code)>{{ $label }}</option>
            @endforeach
          </select>
          <div class="form-text">
            {{ __('Decides which national ID and telephone formats the scanner recognises. A South African ID number is not matched while this is set to GDPR.') }}
          </div>
        </div>

        <div class="mb-2">
          <label for="privacy_custom_terms" class="form-label fw-bold">{{ __('Additional words to scan for') }}</label>
          <textarea class="form-control font-monospace" id="privacy_custom_terms" name="privacy_custom_terms"
                    rows="8" spellcheck="false"
                    placeholder="{{ __("Blaauwbosch
van der Merwe
Kerkstraat") }}">{{ $settings['privacy_custom_terms'] ?? '' }}</textarea>
          <div class="form-text">
            {{ __('One term per line; commas and semicolons also separate, so a column pasted from a spreadsheet works. Matching is whole-word and ignores case. Up to :max terms.', ['max' => \AhgPrivacy\Services\PiiScanService::MAX_CUSTOM_TERMS]) }}
          </div>
          <div class="form-text">
            {{ __('The built-in detectors answer whether something looks like an identifier - an email address, an ID number, a card. They cannot know that a particular surname, farm or case reference is sensitive in this collection. That judgement is yours, and this is where you record it.') }}
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
