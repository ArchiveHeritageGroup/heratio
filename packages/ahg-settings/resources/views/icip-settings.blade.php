@extends('theme::layouts.2col')
@section('title', 'ICIP Settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-shield-alt me-2"></i>ICIP Settings</h1>
@endsection

@section('content')
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
                <label class="form-check-label" for="enable_public_notices">Display cultural notices to public users <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="form-text">When enabled, cultural sensitivity notices will be displayed on the public view of records.</div>
              </div>
              <div class="form-check form-switch mb-3">
                <input type="hidden" name="settings[enable_staff_notices]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[enable_staff_notices]" id="enable_staff_notices" value="1" {{ ($settings['enable_staff_notices'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="enable_staff_notices">Display cultural notices to staff <span class="badge bg-secondary ms-1">Optional</span></label>
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
                <label class="form-check-label" for="require_ack">Require acknowledgement by default <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="form-text">When enabled, new sensitive cultural notices will require user acknowledgement before viewing content.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#consent-collapse">Consent & Consultation</button></h2>
          <div id="consent-collapse" class="accordion-collapse collapse">
            <div class="accordion-body">
              <div class="form-check form-switch mb-3">
                <input type="hidden" name="settings[require_community_consent]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[require_community_consent]" id="require_consent" value="1" {{ ($settings['require_community_consent'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="require_consent">Require community consent for access <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="form-text">Restrict access to sensitive records until community consent is recorded.</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Consent expiry warning (days) <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" name="settings[consent_expiry_warning_days]" class="form-control" value="{{ $settings['consent_expiry_warning_days'] ?? '90' }}" style="max-width:200px;">
                <div class="form-text">Number of days before consent expiry to show warning in dashboard and reports. Default: 90 days.</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Default consultation follow-up (days) <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" name="settings[default_consultation_follow_up_days]" class="form-control" value="{{ $settings['default_consultation_follow_up_days'] ?? $settings['consultation_period_days'] ?? '30' }}" style="max-width:200px;">
                <div class="form-text">Default number of days for consultation follow-up reminders. Default: 30 days.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#lc-collapse">Local Contexts Integration</button></h2>
          <div id="lc-collapse" class="accordion-collapse collapse">
            <div class="accordion-body">
              <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Local Contexts Hub provides TK Labels for Indigenous communities. Visit <a href="https://localcontexts.org/" target="_blank">localcontexts.org</a> for more information.
              </div>
              <div class="form-check form-switch mb-3">
                <input type="hidden" name="settings[local_contexts_hub_enabled]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[local_contexts_hub_enabled]" id="lc_enabled" value="1" {{ ($settings['local_contexts_hub_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="lc_enabled">Enable Local Contexts Hub API <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="form-text">When enabled, TK Labels can be synchronized with the Local Contexts Hub.</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Local Contexts API Key <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="settings[local_contexts_api_key]" class="form-control" value="{{ e($settings['local_contexts_api_key'] ?? '') }}">
                <div class="form-text">API key for accessing the Local Contexts Hub. Leave blank if not using API integration.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#audit-collapse">Audit & Logging</button></h2>
          <div id="audit-collapse" class="accordion-collapse collapse">
            <div class="accordion-body">
              <div class="form-check form-switch mb-3">
                <input type="hidden" name="settings[audit_all_icip_access]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[audit_all_icip_access]" id="audit_icip" value="1" {{ ($settings['audit_all_icip_access'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="audit_icip">Log all ICIP record access <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="form-text">When enabled, all access to records flagged with ICIP content will be logged for audit purposes.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
@endsection
