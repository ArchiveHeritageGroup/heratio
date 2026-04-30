@extends('theme::layouts.2col')
@section('title', 'Email Settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-envelope me-2"></i>Email Settings</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.email') }}">
      @csrf
      <div class="row">
        <div class="col-md-6">
          <div class="card mb-4">
            <div class="card-header bg-primary text-white"><i class="fas fa-server me-2"></i>SMTP Configuration</div>
            <div class="card-body">
              @foreach ($smtpSettings as $setting)
                <div class="mb-3">
                  <label class="form-label">{{ ucwords(str_replace('_', ' ', str_replace('smtp_', '', $setting->setting_key))) }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  @if ($setting->setting_type === 'boolean')
                    <select name="settings[{{ $setting->setting_key }}]" class="form-select">
                      <option value="0" {{ $setting->setting_value == '0' ? 'selected' : '' }}>Disabled</option>
                      <option value="1" {{ $setting->setting_value == '1' ? 'selected' : '' }}>Enabled</option>
                    </select>
                  @elseif ($setting->setting_type === 'password')
                    <input type="password" name="settings[{{ $setting->setting_key }}]" class="form-control" value="{{ e($setting->setting_value ?? '') }}">
                  @elseif ($setting->setting_type === 'number')
                    <input type="number" name="settings[{{ $setting->setting_key }}]" class="form-control" value="{{ e($setting->setting_value ?? '') }}">
                  @else
                    <input type="{{ $setting->setting_type === 'email' ? 'email' : 'text' }}" name="settings[{{ $setting->setting_key }}]" class="form-control" value="{{ e($setting->setting_value ?? '') }}">
                  @endif
                  @if ($setting->description)<small class="text-muted">{{ e($setting->description) }}</small>@endif
                </div>
              @endforeach
            </div>
          </div>

          {{-- Test Email --}}
          <div class="card mb-4">
            <div class="card-header"><i class="fas fa-paper-plane me-2"></i>Test Email</div>
            <div class="card-body">
              <p class="small text-muted">Save settings first, then send a test email to verify configuration.</p>
              <div class="input-group">
                <input type="email" name="test_email" class="form-control" placeholder="{{ __('test@example.com') }}" id="testEmailInput">
                <button type="button" class="btn atom-btn-white" id="btnSendTest">
                  <i class="fas fa-paper-plane me-1"></i>Send Test
                </button>
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header bg-info text-white"><i class="fas fa-bell me-2"></i>Notification Recipients</div>
            <div class="card-body">
              @foreach ($notificationSettings as $setting)
                <div class="mb-3">
                  <label class="form-label">{{ ucwords(str_replace('_', ' ', str_replace('notify_', '', $setting->setting_key))) }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="email" name="settings[{{ $setting->setting_key }}]" class="form-control" value="{{ e($setting->setting_value ?? '') }}" placeholder="{{ __('admin@example.com') }}">
                  @if ($setting->description)<small class="text-muted">{{ e($setting->description) }}</small>@endif
                </div>
              @endforeach
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card mb-4">
            <div class="card-header bg-success text-white"><i class="fas fa-file-alt me-2"></i>Email Templates</div>
            <div class="card-body">
              <div class="alert alert-info small">
                <strong>Available placeholders:</strong><br>
                <code>{name}</code> Recipient name, <code>{email}</code> Recipient email,
                <code>{institution}</code> Institution, <code>{login_url}</code> Login URL,
                <code>{reset_url}</code> Reset URL, <code>{date}</code> / <code>{time}</code> Booking details
              </div>
              <div class="accordion" id="templateAccordion">
                @php $index = 0; @endphp
                @foreach ($templateSettings as $setting)
                  @if (str_ends_with($setting->setting_key, '_subject'))
                    @php
                      $index++;
                      $baseKey = str_replace('_subject', '', $setting->setting_key);
                      $bodyKey = $baseKey . '_body';
                      $bodySetting = $templateSettings->firstWhere('setting_key', $bodyKey);
                      $label = ucwords(str_replace('_', ' ', str_replace('email_', '', $baseKey)));
                    @endphp
                    <div class="accordion-item">
                      <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tpl{{ $index }}">{{ $label }}</button></h2>
                      <div id="tpl{{ $index }}" class="accordion-collapse collapse" data-bs-parent="#templateAccordion">
                        <div class="accordion-body">
                          <div class="mb-3">
                            <label class="form-label">Subject <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" name="settings[{{ $setting->setting_key }}]" class="form-control" value="{{ e($setting->setting_value ?? '') }}">
                          </div>
                          @if($bodySetting)
                          <div class="mb-3">
                            <label class="form-label">Body <span class="badge bg-secondary ms-1">Optional</span></label>
                            <textarea name="settings[{{ $bodyKey }}]" class="form-control" rows="5">{{ e($bodySetting->setting_value ?? '') }}</textarea>
                          </div>
                          @endif
                        </div>
                      </div>
                    </div>
                  @endif
                @endforeach
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Error Alert Configuration --}}
      <div class="card mb-4">
        <div class="card-header bg-warning text-dark"><i class="fas fa-exclamation-triangle me-2"></i>Error Alert Configuration</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Enable Error Alerts <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="error_alert[error_alert_enabled]" class="form-select">
              <option value="0" {{ (isset($errorAlertSettings['error_alert_enabled']) && $errorAlertSettings['error_alert_enabled'] === '0') ? 'selected' : '' }}>{{ __('Disabled') }}</option>
              <option value="1" {{ (!isset($errorAlertSettings['error_alert_enabled']) || $errorAlertSettings['error_alert_enabled'] === '1') ? 'selected' : '' }}>{{ __('Enabled') }}</option>
            </select>
            <small class="text-muted">Send email alerts when unhandled exceptions occur.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Throttle TTL (seconds) <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" name="error_alert[error_alert_throttle_ttl]" class="form-control" min="30" max="86400"
                   value="{{ e($errorAlertSettings['error_alert_throttle_ttl'] ?? '300') }}">
            <small class="text-muted">Minimum seconds between duplicate error alerts. Default: 300 (5 min).</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Daily Cap <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" name="error_alert[error_alert_daily_cap]" class="form-control" min="0" max="1000"
                   value="{{ e($errorAlertSettings['error_alert_daily_cap'] ?? '50') }}">
            <small class="text-muted">Maximum alert emails per day. 0 = unlimited. Default: 50.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Production Only <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="error_alert[error_alert_env_gate]" class="form-select">
              <option value="0" {{ (isset($errorAlertSettings['error_alert_env_gate']) && $errorAlertSettings['error_alert_env_gate'] === '0') ? 'selected' : '' }}>{{ __('Send in all environments') }}</option>
              <option value="1" {{ (!isset($errorAlertSettings['error_alert_env_gate']) || $errorAlertSettings['error_alert_env_gate'] === '1') ? 'selected' : '' }}>{{ __('Production only') }}</option>
            </select>
            <small class="text-muted">When enabled, suppresses alerts in debug/development mode.</small>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header bg-dark text-white"><i class="fas fa-bell me-2"></i>Notification Settings</div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <i class="fas fa-layer-group me-2 text-muted"></i>Spectrum Email Notifications
              <br><small class="text-muted">Task assignments and state transitions</small>
            </div>
            <a href="{{ route('settings.ahg.spectrum') }}" class="btn btn-sm btn-outline-primary">Configure</a>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <i class="fas fa-book-reader me-2 text-muted"></i>Research Notifications
              <br><small class="text-muted">Researcher registration, approval, booking emails</small>
            </div>
            <div class="form-check form-switch">
              <input type="hidden" name="notif_toggles[research_email_notifications]" value="0">
              <input class="form-check-input" type="checkbox" name="notif_toggles[research_email_notifications]" value="1" id="research_email_notifications" {{ ($notifToggles['research_email_notifications'] ?? '1') == '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="research_email_notifications">{{ __('Enabled') }}</label>
            </div>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <i class="fas fa-shield-alt me-2 text-muted"></i>Access Request Notifications
              <br><small class="text-muted">Approver notifications, request status emails</small>
            </div>
            <div class="form-check form-switch">
              <input type="hidden" name="notif_toggles[access_request_email_notifications]" value="0">
              <input class="form-check-input" type="checkbox" name="notif_toggles[access_request_email_notifications]" value="1" id="access_request_email_notifications" {{ ($notifToggles['access_request_email_notifications'] ?? '1') == '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="access_request_email_notifications">{{ __('Enabled') }}</label>
            </div>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <i class="fas fa-project-diagram me-2 text-muted"></i>Workflow Notifications
              <br><small class="text-muted">Task assignment, approval, rejection emails</small>
            </div>
            <div class="form-check form-switch">
              <input type="hidden" name="notif_toggles[workflow_email_notifications]" value="0">
              <input class="form-check-input" type="checkbox" name="notif_toggles[workflow_email_notifications]" value="1" id="workflow_email_notifications" {{ ($notifToggles['workflow_email_notifications'] ?? '1') == '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="workflow_email_notifications">{{ __('Enabled') }}</label>
            </div>
          </li>
        </ul>
      </div>

      <hr>
      <div class="d-flex justify-content-between">
        <a href="{{ route('settings.index') }}" class="btn btn-secondary">
          <i class="fas fa-arrow-left me-1"></i>Back to Settings
        </a>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save me-1"></i>Save Settings
        </button>
      </div>
    </form>

@push('js')
<script>
document.getElementById('btnSendTest').addEventListener('click', function() {
    var email = document.getElementById('testEmailInput').value;
    if (email) {
        window.location.href = '{{ route("settings.email") }}?test_email=' + encodeURIComponent(email);
    } else {
        alert('Please enter an email address');
    }
});
</script>
@endpush
@endsection
