@extends('theme::layouts.2col')
@section('title', 'Privacy Notification')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>{{ __('Privacy Notification') }}</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.privacy-notification') }}">
      @csrf

      <div class="accordion mb-3" id="privacyAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="privacy-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#privacy-collapse" aria-expanded="false" aria-controls="privacy-collapse">
              {{ __('Privacy Notification Settings') }}
            </button>
          </h2>
          <div id="privacy-collapse" class="accordion-collapse collapse" aria-labelledby="privacy-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Display Privacy Notification on first visit to site <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[privacy_notification_enabled]" id="privacy_no" value="0" {{ $settings['privacy_notification_enabled'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="privacy_no">No <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[privacy_notification_enabled]" id="privacy_yes" value="1" {{ $settings['privacy_notification_enabled'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="privacy_yes">Yes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Privacy Notification Message <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <textarea name="settings[privacy_notification]" class="form-control" rows="5">{{ e($settings['privacy_notification']) }}</textarea>
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
