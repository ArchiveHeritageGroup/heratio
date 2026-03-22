@extends('theme::layouts.1col')
@section('title', 'Web analytics')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Web analytics</h1>

    <form method="post" action="{{ route('settings.web-analytics') }}">
      @csrf

      <div class="accordion mb-3" id="analyticsAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="ga-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ga-collapse" aria-expanded="false" aria-controls="ga-collapse">
              Google Analytics
            </button>
          </h2>
          <div id="ga-collapse" class="accordion-collapse collapse" aria-labelledby="ga-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Google Analytics tracking ID <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="settings[google_analytics_api_key]" class="form-control" value="{{ e($settings['google_analytics_api_key']) }}" placeholder="G-XXXXXXXXXX or UA-XXXXXXXX-X">
                <small class="text-muted">Enter your Google Analytics measurement ID (GA4) or tracking ID (Universal Analytics). Leave blank to disable tracking.</small>
              </div>
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header" id="gtm-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#gtm-collapse" aria-expanded="false" aria-controls="gtm-collapse">
              Google Tag Manager
            </button>
          </h2>
          <div id="gtm-collapse" class="accordion-collapse collapse" aria-labelledby="gtm-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Google Tag Manager container ID <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="settings[google_tag_manager_id]" class="form-control" value="{{ e($settings['google_tag_manager_id']) }}" placeholder="GTM-XXXXXXX">
                <small class="text-muted">Enter your Google Tag Manager container ID. Leave blank to disable.</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="actions mb-3" style="background:#495057 !important;border-radius:.375rem;padding:1rem;display:block;">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </div>

    </form>
  </div>
</div>
@endsection
