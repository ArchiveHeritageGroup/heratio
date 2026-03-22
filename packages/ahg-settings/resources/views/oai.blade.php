@extends('theme::layouts.1col')
@section('title', 'OAI Repository Settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-cloud me-2"></i>OAI Repository Settings</h1>

    <form method="post" action="{{ route('settings.oai') }}">
      @csrf
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">OAI-PMH settings</div>
        <div class="card-body">
          <p class="text-muted">The OAI-PMH API can be secured by requiring API requests authenticate using API keys.</p>
          <div class="mb-3">
            <label class="form-label">Enable OAI authentication</label>
            <select name="settings[oai_authentication_enabled]" class="form-select">
              <option value="0" {{ ($settings['oai_authentication_enabled'] ?? '') == '0' ? 'selected' : '' }}>No</option>
              <option value="1" {{ ($settings['oai_authentication_enabled'] ?? '') == '1' ? 'selected' : '' }}>Yes</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Repository code</label>
            <input type="text" name="settings[oai_repository_code]" class="form-control" value="{{ e($settings['oai_repository_code'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Admin email(s)</label>
            <input type="text" name="settings[oai_admin_emails]" class="form-control" value="{{ e($settings['oai_admin_emails'] ?? '') }}">
            <small class="text-muted">Comma-separated list of admin emails</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Repository identifier</label>
            <input type="text" name="settings[oai_repository_identifier]" class="form-control" value="{{ e($settings['oai_repository_identifier'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Sample OAI identifier</label>
            <input type="text" name="settings[sample_oai_identifier]" class="form-control" value="{{ e($settings['sample_oai_identifier'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Resumption token limit</label>
            <input type="number" name="settings[resumption_token_limit]" class="form-control" value="{{ e($settings['resumption_token_limit'] ?? '100') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Enable additional sets</label>
            <select name="settings[oai_additional_sets_enabled]" class="form-select">
              <option value="0" {{ ($settings['oai_additional_sets_enabled'] ?? '') == '0' ? 'selected' : '' }}>No</option>
              <option value="1" {{ ($settings['oai_additional_sets_enabled'] ?? '') == '1' ? 'selected' : '' }}>Yes</option>
            </select>
          </div>
        </div>
      </div>
      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
