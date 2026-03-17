@extends('theme::layouts.1col')
@section('title', 'Privacy Notification')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Privacy Notification</h1>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <form method="post" action="{{ route('settings.privacy-notification') }}">
      @csrf

      <div class="accordion mb-3" id="privacyAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="privacy-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#privacy-collapse" aria-expanded="false" aria-controls="privacy-collapse">
              Privacy Notification Settings
            </button>
          </h2>
          <div id="privacy-collapse" class="accordion-collapse collapse" aria-labelledby="privacy-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Display Privacy Notification on first visit to site</label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[privacy_notification_enabled]" id="privacy_no" value="0" {{ $settings['privacy_notification_enabled'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="privacy_no">No</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[privacy_notification_enabled]" id="privacy_yes" value="1" {{ $settings['privacy_notification_enabled'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="privacy_yes">Yes</label>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Privacy Notification Message</label>
                <textarea name="settings[privacy_notification]" class="form-control" rows="5">{{ e($settings['privacy_notification']) }}</textarea>
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
