@extends('theme::layouts.2col')
@section('title', 'OAI Repository Settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>OAI repository settings</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.oai') }}">
      @csrf
      <div class="accordion" id="settingsAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOai">
              OAI-PMH settings
            </button>
          </h2>
          <div id="collapseOai" class="accordion-collapse collapse show" data-bs-parent="#settingsAccordion">
            <div class="accordion-body">
              <p class="text-muted">The OAI-PMH API can be secured by requiring API requests authenticate using API keys.</p>
              <div class="mb-3">
                <label class="form-label">Enable OAI authentication <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="settings[oai_authentication_enabled]" class="form-select">
                  <option value="0" {{ ($settings['oai_authentication_enabled'] ?? '') == '0' ? 'selected' : '' }}>No</option>
                  <option value="1" {{ ($settings['oai_authentication_enabled'] ?? '') == '1' ? 'selected' : '' }}>Yes</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Repository code <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="settings[oai_repository_code]" class="form-control" value="{{ e($settings['oai_repository_code'] ?? '') }}">
              </div>
              <div class="mb-3">
                <label class="form-label">Admin email(s) <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="settings[oai_admin_emails]" class="form-control" value="{{ e($settings['oai_admin_emails'] ?? '') }}">
                <small class="text-muted">Comma-separated list of admin emails</small>
              </div>
              <div class="mb-3">
                <label class="form-label">Repository identifier <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="settings[oai_repository_identifier]" class="form-control" value="{{ e($settings['oai_repository_identifier'] ?? '') }}">
              </div>
              <div class="mb-3">
                <label class="form-label">Sample OAI identifier <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="settings[sample_oai_identifier]" class="form-control" value="{{ e($settings['sample_oai_identifier'] ?? '') }}">
              </div>
              <div class="mb-3">
                <label class="form-label">Resumption token limit <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" name="settings[resumption_token_limit]" class="form-control" value="{{ e($settings['resumption_token_limit'] ?? '100') }}">
              </div>
              <div class="mb-3">
                <label class="form-label">Enable additional sets <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="settings[oai_additional_sets_enabled]" class="form-select">
                  <option value="0" {{ ($settings['oai_additional_sets_enabled'] ?? '') == '0' ? 'selected' : '' }}>No</option>
                  <option value="1" {{ ($settings['oai_additional_sets_enabled'] ?? '') == '1' ? 'selected' : '' }}>Yes</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>
      <section class="actions mb-3">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>
    </form>
@endsection
