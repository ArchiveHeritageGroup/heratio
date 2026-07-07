@extends('theme::layouts.2col')
@section('title', 'Archivematica')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>{{ __('Archivematica') }}</h1>
@endsection

@section('content')

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <p class="text-muted">
    {{ __('Connect Heratio to an Archivematica instance. The Storage Service holds AIPs/DIPs; the Dashboard drives processing. Credentials are stored in the settings table only and are never committed.') }}
  </p>

  <form method="post" action="{{ route('archivematica.settings.update') }}">
    @csrf

    <div class="accordion mb-3" id="amAccordion">

      {{-- Storage Service --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="am-ss-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#am-ss-collapse" aria-expanded="true" aria-controls="am-ss-collapse">
            {{ __('Storage Service (SS)') }}
          </button>
        </h2>
        <div id="am-ss-collapse" class="accordion-collapse collapse show" aria-labelledby="am-ss-heading" data-bs-parent="#amAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label" for="am_ss_url">{{ __('Storage Service URL') }}</label>
              <input type="url" name="settings[am_ss_url]" id="am_ss_url" class="form-control" value="{{ $settings['am_ss_url'] ?? '' }}" placeholder="https://archivematica-ss.example.org">
              <small class="text-muted">{{ __('Base URL of the Archivematica Storage Service API.') }}</small>
            </div>
            <div class="mb-3">
              <label class="form-label" for="am_ss_username">{{ __('Storage Service username') }}</label>
              <input type="text" name="settings[am_ss_username]" id="am_ss_username" class="form-control" value="{{ $settings['am_ss_username'] ?? '' }}">
            </div>
            <div class="mb-3">
              <label class="form-label" for="am_ss_api_key">{{ __('Storage Service API key') }}</label>
              <input type="password" name="settings[am_ss_api_key]" id="am_ss_api_key" class="form-control" value="{{ $settings['am_ss_api_key'] ?? '' }}" autocomplete="new-password">
              <small class="text-muted">{{ __('Stored in the settings table only.') }}</small>
            </div>
          </div>
        </div>
      </div>

      {{-- Dashboard --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="am-dash-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#am-dash-collapse" aria-expanded="false" aria-controls="am-dash-collapse">
            {{ __('Dashboard') }}
          </button>
        </h2>
        <div id="am-dash-collapse" class="accordion-collapse collapse" aria-labelledby="am-dash-heading" data-bs-parent="#amAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label" for="am_dashboard_url">{{ __('Dashboard URL') }}</label>
              <input type="url" name="settings[am_dashboard_url]" id="am_dashboard_url" class="form-control" value="{{ $settings['am_dashboard_url'] ?? '' }}" placeholder="https://archivematica.example.org">
              <small class="text-muted">{{ __('Base URL of the Archivematica Dashboard API.') }}</small>
            </div>
            <div class="mb-3">
              <label class="form-label" for="am_dashboard_username">{{ __('Dashboard username') }}</label>
              <input type="text" name="settings[am_dashboard_username]" id="am_dashboard_username" class="form-control" value="{{ $settings['am_dashboard_username'] ?? '' }}">
            </div>
            <div class="mb-3">
              <label class="form-label" for="am_dashboard_api_key">{{ __('Dashboard API key') }}</label>
              <input type="password" name="settings[am_dashboard_api_key]" id="am_dashboard_api_key" class="form-control" value="{{ $settings['am_dashboard_api_key'] ?? '' }}" autocomplete="new-password">
              <small class="text-muted">{{ __('Stored in the settings table only.') }}</small>
            </div>
          </div>
        </div>
      </div>

      {{-- Transfer defaults --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="am-transfer-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#am-transfer-collapse" aria-expanded="false" aria-controls="am-transfer-collapse">
            {{ __('Transfer defaults & matching') }}
          </button>
        </h2>
        <div id="am-transfer-collapse" class="accordion-collapse collapse" aria-labelledby="am-transfer-heading" data-bs-parent="#amAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label" for="am_default_pipeline_uuid">{{ __('Default pipeline UUID') }}</label>
              <input type="text" name="settings[am_default_pipeline_uuid]" id="am_default_pipeline_uuid" class="form-control" value="{{ $settings['am_default_pipeline_uuid'] ?? '' }}" placeholder="00000000-0000-0000-0000-000000000000">
            </div>
            <div class="mb-3">
              <label class="form-label" for="am_transfer_source_path">{{ __('Transfer source path') }}</label>
              <input type="text" name="settings[am_transfer_source_path]" id="am_transfer_source_path" class="form-control" value="{{ $settings['am_transfer_source_path'] ?? '' }}" placeholder="/transfer-source">
              <small class="text-muted">{{ __('Path Archivematica reads transfers from.') }}</small>
            </div>
            <div class="mb-3">
              <label class="form-label" for="am_dip_match_strategy">{{ __('DIP match strategy') }}</label>
              <select name="settings[am_dip_match_strategy]" id="am_dip_match_strategy" class="form-select">
                <option value="uuid" {{ ($settings['am_dip_match_strategy'] ?? '') === 'uuid' ? 'selected' : '' }}>{{ __('UUID (AIP/description UUID in METS)') }}</option>
                <option value="identifier" {{ ($settings['am_dip_match_strategy'] ?? 'identifier') === 'identifier' ? 'selected' : '' }}>{{ __('Identifier') }}</option>
                <option value="slug" {{ ($settings['am_dip_match_strategy'] ?? '') === 'slug' ? 'selected' : '' }}>{{ __('Slug') }}</option>
              </select>
              <small class="text-muted">{{ __('How an incoming DIP is matched to a Heratio description.') }}</small>
            </div>
          </div>
        </div>
      </div>

    </div>

    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
  </form>

@endsection
