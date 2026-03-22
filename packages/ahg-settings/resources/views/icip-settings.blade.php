@extends('theme::layouts.1col')
@section('title', 'ICIP Settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-settings::_menu')
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-shield-alt me-2"></i>ICIP Settings</h1>
    <p class="lead text-muted mb-4">Configure Indigenous Cultural and Intellectual Property management settings.</p>

    <form method="post" action="{{ route('settings.icip-settings') }}">
      @csrf
      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#display-collapse">Display Settings</button></h2>
          <div id="display-collapse" class="accordion-collapse collapse show">
            <div class="accordion-body">
              <div class="form-check form-switch mb-3">
                <input type="hidden" name="settings[enable_public_notices]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[enable_public_notices]" id="enable_public_notices" value="1" {{ ($settings['enable_public_notices'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="enable_public_notices">Display cultural notices to public users</label>
                <div class="form-text">When enabled, cultural sensitivity notices will be displayed on the public view of records.</div>
              </div>
              <div class="form-check form-switch mb-3">
                <input type="hidden" name="settings[enable_staff_notices]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[enable_staff_notices]" id="enable_staff_notices" value="1" {{ ($settings['enable_staff_notices'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="enable_staff_notices">Display cultural notices to staff</label>
                <div class="form-text">When enabled, cultural sensitivity notices will be displayed to authenticated staff members.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ack-collapse">Acknowledgement Settings</button></h2>
          <div id="ack-collapse" class="accordion-collapse collapse">
            <div class="accordion-body">
              <div class="form-check form-switch mb-3">
                <input type="hidden" name="settings[require_acknowledgement_default]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[require_acknowledgement_default]" id="require_ack" value="1" {{ ($settings['require_acknowledgement_default'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="require_ack">Require acknowledgement by default</label>
                <div class="form-text">When enabled, new sensitive cultural notices will require user acknowledgement before viewing content.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#consent-collapse">Consent & Consultation Settings</button></h2>
          <div id="consent-collapse" class="accordion-collapse collapse">
            <div class="accordion-body">
              <div class="form-check form-switch mb-3">
                <input type="hidden" name="settings[require_community_consent]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[require_community_consent]" id="require_consent" value="1" {{ ($settings['require_community_consent'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="require_consent">Require community consent for access</label>
                <div class="form-text">Restrict access to sensitive records until community consent is recorded.</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Default consultation period (days) <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" name="settings[consultation_period_days]" class="form-control" value="{{ $settings['consultation_period_days'] ?? '30' }}" style="max-width:200px;">
              </div>
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
