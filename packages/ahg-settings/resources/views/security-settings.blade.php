{{--
  Security & Access Control — password policy, lockout, session security
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('security')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Security')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-shield-alt me-2"></i>Security & Access Control</h1>
<p class="text-muted">Password policy, account lockout, session security, and access control settings</p>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.security') }}">
    @csrf

    {{-- Card 1: Password Policy --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-key me-2"></i>Password Policy</div>
      <div class="card-body">
        <p class="text-muted mb-3">Configure password expiry and history requirements. These settings are enforced by the PasswordPolicyService (ISO 27001 A.9.4.3).</p>
        <div class="row">
          <div class="col-md-4">
            <label for="password_expiry_days" class="form-label"><strong>{{ __('Password Expiry (Days)') }}</strong></label>
            <input type="number" class="form-control" id="password_expiry_days"
                   name="settings[password_expiry_days]"
                   value="{{ $settings['password_expiry_days'] ?? '90' }}"
                   min="0" max="365" step="1">
            <div class="form-text">Number of days before passwords expire. Set to 0 to disable. Default: 90</div>
          </div>
          <div class="col-md-4">
            <label for="password_history_count" class="form-label"><strong>{{ __('Password History') }}</strong></label>
            <input type="number" class="form-control" id="password_history_count"
                   name="settings[password_history_count]"
                   value="{{ $settings['password_history_count'] ?? '5' }}"
                   min="0" max="24" step="1">
            <div class="form-text">Number of previous passwords to remember (prevents reuse). Default: 5</div>
          </div>
          <div class="col-md-4">
            <label for="security_password_expiry_warn_days" class="form-label"><strong>{{ __('Expiry Warning (Days)') }}</strong></label>
            <input type="number" class="form-control" id="security_password_expiry_warn_days"
                   name="settings[security_password_expiry_warn_days]"
                   value="{{ $settings['security_password_expiry_warn_days'] ?? '14' }}"
                   min="0" max="90" step="1">
            <div class="form-text">Show warning when password expires within this many days. Default: 14</div>
          </div>
        </div>
        <div class="row mt-3">
          <div class="col-md-4">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="security_password_expiry_notify"
                     name="settings[security_password_expiry_notify]" value="true"
                     {{ ($settings['security_password_expiry_notify'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="security_password_expiry_notify"><strong>{{ __('Show Expiry Notification') }}</strong></label>
            </div>
            <div class="form-text">Display a flash notification on login when the password is expiring soon or has expired.</div>
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="security_force_password_change"
                     name="settings[security_force_password_change]" value="true"
                     {{ ($settings['security_force_password_change'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="security_force_password_change"><strong>{{ __('Force Password Change') }}</strong></label>
            </div>
            <div class="form-text">Redirect users to the password change page when their password has expired.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 2: Account Lockout --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-lock me-2"></i>Account Lockout</div>
      <div class="card-body">
        <p class="text-muted mb-3">Brute force protection settings. Managed by LoginSecurityService (OWASP A07).</p>
        <div class="row">
          <div class="col-md-4">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="security_lockout_enabled"
                     name="settings[security_lockout_enabled]" value="true"
                     {{ ($settings['security_lockout_enabled'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="security_lockout_enabled"><strong>{{ __('Enable Account Lockout') }}</strong></label>
            </div>
            <div class="form-text">Lock accounts after repeated failed login attempts.</div>
          </div>
          <div class="col-md-4">
            <label for="security_lockout_max_attempts" class="form-label"><strong>{{ __('Max Failed Attempts') }}</strong></label>
            <input type="number" class="form-control" id="security_lockout_max_attempts"
                   name="settings[security_lockout_max_attempts]"
                   value="{{ $settings['security_lockout_max_attempts'] ?? '5' }}"
                   min="1" max="20" step="1">
            <div class="form-text">Number of failed attempts before lockout. Default: 5</div>
          </div>
          <div class="col-md-4">
            <label for="security_lockout_duration_minutes" class="form-label"><strong>{{ __('Lockout Duration (Minutes)') }}</strong></label>
            <input type="number" class="form-control" id="security_lockout_duration_minutes"
                   name="settings[security_lockout_duration_minutes]"
                   value="{{ $settings['security_lockout_duration_minutes'] ?? '15' }}"
                   min="1" max="1440" step="1">
            <div class="form-text">Minutes to lock the account after max failed attempts. Default: 15</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 3: Session Security --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-clock me-2"></i>Session Security</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4">
            <label for="security_session_timeout_minutes" class="form-label"><strong>{{ __('Session Timeout (Minutes)') }}</strong></label>
            <input type="number" class="form-control" id="security_session_timeout_minutes"
                   name="settings[security_session_timeout_minutes]"
                   value="{{ $settings['security_session_timeout_minutes'] ?? '30' }}"
                   min="5" max="480" step="5">
            <div class="form-text">Idle session timeout in minutes. Default: 30</div>
          </div>
          <div class="col-md-4">
            <label for="security_login_attempt_cleanup_hours" class="form-label"><strong>{{ __('Login Attempt Retention (Hours)') }}</strong></label>
            <input type="number" class="form-control" id="security_login_attempt_cleanup_hours"
                   name="settings[security_login_attempt_cleanup_hours]"
                   value="{{ $settings['security_login_attempt_cleanup_hours'] ?? '24' }}"
                   min="1" max="720" step="1">
            <div class="form-text">Hours to retain login attempt records. Default: 24</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 4: Security Status --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-info-circle me-2"></i>Security Status</div>
      <div class="card-body">
        <div class="alert alert-info mb-0">
          <h6><i class="fas fa-shield-alt me-2"></i>Active Security Features</h6>
          <ul class="mb-0 mt-2">
            <li><strong>{{ __('Session Fixation Prevention') }}</strong> — Session ID regenerated on login</li>
            <li><strong>{{ __('CSRF Protection') }}</strong> — Enforced on all state-changing requests</li>
            <li><strong>{{ __('Security Headers') }}</strong> — HSTS, X-Frame-Options, Permissions-Policy</li>
            <li><strong>{{ __('HttpOnly Cookies') }}</strong> — Session cookies inaccessible to JavaScript</li>
            <li><strong>{{ __('Bell-LaPadula MAC') }}</strong> — Simple Security + Star Property</li>
            <li><strong>{{ __('SSRF Protection') }}</strong> — DNS pre-resolution, private IP blocking</li>
            <li><strong>{{ __('XXE Protection') }}</strong> — LIBXML_NONET on all XML parsing</li>
          </ul>
          <hr>
          <p class="mb-0 small text-muted">Standards: OWASP Top 10 (2021), ISO 27001:2022, Bell-LaPadula, POPIA Section 19</p>
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
