@extends('theme::layouts.1col')
@section('title', 'Finding Aid settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Finding Aid settings</h1>

    <form method="post" action="{{ route('settings.finding-aid') }}">
      @csrf

      <div class="accordion mb-3" id="finding-aid-settings">
        <div class="accordion-item">
          <h2 class="accordion-header" id="finding-aid-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#finding-aid-collapse" aria-expanded="false" aria-controls="finding-aid-collapse">
              Finding Aid settings
            </button>
          </h2>
          <div id="finding-aid-collapse" class="accordion-collapse collapse" aria-labelledby="finding-aid-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Enable finding aids <span class="badge bg-secondary ms-1">Optional</span></label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[finding_aids_enabled]" id="fa_enabled_no" value="0" {{ $settings['finding_aids_enabled'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="fa_enabled_no">No</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[finding_aids_enabled]" id="fa_enabled_yes" value="1" {{ $settings['finding_aids_enabled'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="fa_enabled_yes">Yes</label>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Finding aid format <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="settings[finding_aid_format]" class="form-select">
                  <option value="pdf" {{ $settings['finding_aid_format'] == 'pdf' ? 'selected' : '' }}>PDF</option>
                  <option value="rtf" {{ $settings['finding_aid_format'] == 'rtf' ? 'selected' : '' }}>RTF</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Finding aid model <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="settings[finding_aid_model]" class="form-select">
                  <option value="inventory-summary" {{ $settings['finding_aid_model'] == 'inventory-summary' ? 'selected' : '' }}>Inventory summary</option>
                  <option value="full-details" {{ $settings['finding_aid_model'] == 'full-details' ? 'selected' : '' }}>Full details</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Public finding aid <span class="badge bg-secondary ms-1">Optional</span></label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[public_finding_aid]" id="public_fa_no" value="0" {{ $settings['public_finding_aid'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="public_fa_no">No</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[public_finding_aid]" id="public_fa_yes" value="1" {{ $settings['public_finding_aid'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="public_fa_yes">Yes</label>
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
