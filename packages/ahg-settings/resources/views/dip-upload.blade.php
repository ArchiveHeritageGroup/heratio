@extends('theme::layouts.1col')
@section('title', 'DIP upload settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>DIP upload settings</h1>

    <form method="post" action="{{ route('settings.dip-upload') }}">
      @csrf

      <div class="accordion mb-3" id="dipUploadAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="dip-upload-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dip-upload-collapse" aria-expanded="false" aria-controls="dip-upload-collapse">
              DIP Upload settings
            </button>
          </h2>
          <div id="dip-upload-collapse" class="accordion-collapse collapse" aria-labelledby="dip-upload-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Strip file extensions from information object names <span class="badge bg-secondary ms-1">Optional</span></label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[stripExtensions]" id="strip_no" value="0" {{ $settings['stripExtensions'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="strip_no">No <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[stripExtensions]" id="strip_yes" value="1" {{ $settings['stripExtensions'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="strip_yes">Yes <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                </div>
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
