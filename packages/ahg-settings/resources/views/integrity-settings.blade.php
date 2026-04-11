{{--
  Integrity — fixity verification, notifications, and quick links
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('integrity')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Integrity')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-check-double me-2"></i>Integrity</h1>
<p class="text-muted">Fixity checking and integrity monitoring</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.integrity') }}">
    @csrf

    {{-- Integrity Verification Defaults --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Integrity Verification Defaults</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="integrity_enabled"
                     name="settings[integrity_enabled]" value="true"
                     {{ ($settings['integrity_enabled'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="integrity_enabled">
                <strong>Enable Integrity Assurance</strong>
              </label>
            </div>
            <div class="form-text mb-3">Master switch for all integrity verification functionality.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="integrity_auto_baseline"
                     name="settings[integrity_auto_baseline]" value="true"
                     {{ ($settings['integrity_auto_baseline'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="integrity_auto_baseline">
                <strong>Auto-Generate Baselines</strong>
              </label>
            </div>
            <div class="form-text mb-3">Automatically generate baseline checksums on first verification if none exist.</div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="integrity_default_algorithm">Default Algorithm</label>
            <select class="form-select" id="integrity_default_algorithm" name="settings[integrity_default_algorithm]">
              <option value="sha256" {{ ($settings['integrity_default_algorithm'] ?? 'sha256') === 'sha256' ? 'selected' : '' }}>SHA-256 (faster)</option>
              <option value="sha512" {{ ($settings['integrity_default_algorithm'] ?? '') === 'sha512' ? 'selected' : '' }}>SHA-512 (more secure)</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="integrity_default_batch_size">Default Batch Size</label>
            <input type="number" class="form-control" id="integrity_default_batch_size"
                   name="settings[integrity_default_batch_size]"
                   value="{{ e($settings['integrity_default_batch_size'] ?? '200') }}" min="0" max="50000">
            <div class="form-text">Objects per run (0 = unlimited).</div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="integrity_io_throttle_ms">IO Throttle (ms)</label>
            <input type="number" class="form-control" id="integrity_io_throttle_ms"
                   name="settings[integrity_io_throttle_ms]"
                   value="{{ e($settings['integrity_io_throttle_ms'] ?? '10') }}" min="0" max="1000">
            <div class="form-text">Millisecond pause between objects to reduce disk pressure.</div>
          </div>
        </div>
        <div class="row g-3 mt-2">
          <div class="col-md-4">
            <label class="form-label" for="integrity_default_max_runtime">Max Runtime (minutes)</label>
            <input type="number" class="form-control" id="integrity_default_max_runtime"
                   name="settings[integrity_default_max_runtime]"
                   value="{{ e($settings['integrity_default_max_runtime'] ?? '120') }}" min="1" max="1440">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="integrity_default_max_memory">Max Memory (MB)</label>
            <input type="number" class="form-control" id="integrity_default_max_memory"
                   name="settings[integrity_default_max_memory]"
                   value="{{ e($settings['integrity_default_max_memory'] ?? '512') }}" min="64" max="4096">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="integrity_dead_letter_threshold">Dead Letter Threshold</label>
            <input type="number" class="form-control" id="integrity_dead_letter_threshold"
                   name="settings[integrity_dead_letter_threshold]"
                   value="{{ e($settings['integrity_dead_letter_threshold'] ?? '3') }}" min="1" max="100">
            <div class="form-text">Consecutive failures before escalation to dead letter queue.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Notification Defaults --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Defaults</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="integrity_notify_on_failure"
                     name="settings[integrity_notify_on_failure]" value="true"
                     {{ ($settings['integrity_notify_on_failure'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="integrity_notify_on_failure">
                <strong>Notify on Run Failure</strong>
              </label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="integrity_notify_on_mismatch"
                     name="settings[integrity_notify_on_mismatch]" value="true"
                     {{ ($settings['integrity_notify_on_mismatch'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="integrity_notify_on_mismatch">
                <strong>Notify on Hash Mismatch</strong>
              </label>
            </div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="integrity_alert_email">Default Alert Email</label>
            <input type="email" class="form-control" id="integrity_alert_email"
                   name="settings[integrity_alert_email]"
                   value="{{ e($settings['integrity_alert_email'] ?? '') }}"
                   placeholder="admin@example.com">
            <div class="form-text">Default email for integrity alerts (used by new schedules and alert rules).</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="integrity_webhook_url">Default Webhook URL</label>
            <input type="url" class="form-control" id="integrity_webhook_url"
                   name="settings[integrity_webhook_url]"
                   value="{{ e($settings['integrity_webhook_url'] ?? '') }}"
                   placeholder="https://hooks.slack.com/...">
            <div class="form-text">Default webhook URL for alert notifications (Slack, Teams, PagerDuty, etc).</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Quick Links --}}
    <div class="card mb-4 border-info">
      <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h5>
      </div>
      <div class="card-body">
        <div class="row g-2">
          @if(\Route::has('integrity.index'))
          <div class="col-auto">
            <a href="{{ route('integrity.index') }}" class="btn btn-outline-primary">
              <i class="fas fa-tachometer-alt me-1"></i>Dashboard
            </a>
          </div>
          @endif
          @if(\Route::has('integrity.schedules'))
          <div class="col-auto">
            <a href="{{ route('integrity.schedules') }}" class="btn btn-outline-primary">
              <i class="fas fa-clock me-1"></i>Schedules
            </a>
          </div>
          @endif
          @if(\Route::has('integrity.policies'))
          <div class="col-auto">
            <a href="{{ route('integrity.policies') }}" class="btn btn-outline-warning">
              <i class="fas fa-archive me-1"></i>Retention Policies
            </a>
          </div>
          @endif
          @if(\Route::has('integrity.alerts'))
          <div class="col-auto">
            <a href="{{ route('integrity.alerts') }}" class="btn btn-outline-dark">
              <i class="fas fa-bell me-1"></i>Alert Rules
            </a>
          </div>
          @endif
          @if(\Route::has('integrity.export'))
          <div class="col-auto">
            <a href="{{ route('integrity.export') }}" class="btn btn-outline-success">
              <i class="fas fa-download me-1"></i>Export
            </a>
          </div>
          @endif
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
