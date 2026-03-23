@extends('theme::layouts.1col')
@section('title', 'Clipboard settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Clipboard settings</h1>

    <form method="post" action="{{ route('settings.clipboard') }}">
      @csrf

      <div class="accordion mb-3" id="clipboardAccordion">

        {{-- Clipboard saving --}}
        <div class="accordion-item">
          <h2 class="accordion-header" id="saving-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#saving-collapse" aria-expanded="false" aria-controls="saving-collapse">
              Clipboard saving
            </button>
          </h2>
          <div id="saving-collapse" class="accordion-collapse collapse" aria-labelledby="saving-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Saved clipboard maximum age (in days) <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" name="settings[clipboard_save_max_age]" class="form-control" value="{{ e($settings['clipboard_save_max_age']) }}" min="0">
                <small class="text-muted">The number of days a saved clipboard should be retained before it is eligible for deletion</small>
              </div>
            </div>
          </div>
        </div>

        {{-- Clipboard sending --}}
        <div class="accordion-item">
          <h2 class="accordion-header" id="sending-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sending-collapse" aria-expanded="false" aria-controls="sending-collapse">
              Clipboard sending
            </button>
          </h2>
          <div id="sending-collapse" class="accordion-collapse collapse" aria-labelledby="sending-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Enable clipboard send functionality <span class="badge bg-secondary ms-1">Optional</span></label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[clipboard_send_enabled]" id="send_enabled_no" value="0" {{ $settings['clipboard_send_enabled'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="send_enabled_no">No <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[clipboard_send_enabled]" id="send_enabled_yes" value="1" {{ $settings['clipboard_send_enabled'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="send_enabled_yes">Yes <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">External URL to send clipboard contents to <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="settings[clipboard_send_url]" class="form-control" value="{{ e($settings['clipboard_send_url']) }}">
              </div>

              <div class="mb-3">
                <label class="form-label">Send button text <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="settings[clipboard_send_button_text]" class="form-control" value="{{ e($settings['clipboard_send_button_text']) }}">
              </div>

              <div class="mb-3">
                <label class="form-label">Text or HTML to display when sending clipboard contents <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea name="settings[clipboard_send_message_html]" class="form-control" rows="3">{{ e($settings['clipboard_send_message_html']) }}</textarea>
              </div>

              <div class="mb-3">
                <label class="form-label">HTTP method to use when sending clipboard contents <span class="badge bg-secondary ms-1">Optional</span></label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[clipboard_send_http_method]" id="method_post" value="POST" {{ $settings['clipboard_send_http_method'] != 'GET' ? 'checked' : '' }}>
                    <label class="form-check-label" for="method_post">POST <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[clipboard_send_http_method]" id="method_get" value="GET" {{ $settings['clipboard_send_http_method'] == 'GET' ? 'checked' : '' }}>
                    <label class="form-check-label" for="method_get">GET <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Clipboard export --}}
        <div class="accordion-item">
          <h2 class="accordion-header" id="export-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#export-collapse" aria-expanded="false" aria-controls="export-collapse">
              Clipboard export
            </button>
          </h2>
          <div id="export-collapse" class="accordion-collapse collapse" aria-labelledby="export-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Enable digital object export <span class="badge bg-secondary ms-1">Optional</span></label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[clipboard_export_digitalobjects_enabled]" id="export_no" value="0" {{ $settings['clipboard_export_digitalobjects_enabled'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="export_no">No <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[clipboard_export_digitalobjects_enabled]" id="export_yes" value="1" {{ $settings['clipboard_export_digitalobjects_enabled'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="export_yes">Yes <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <section class="actions mb-3">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>

    </form>
  </div>
</div>
@endsection
