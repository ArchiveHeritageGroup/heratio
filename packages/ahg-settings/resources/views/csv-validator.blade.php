@extends('theme::layouts.1col')
@section('title', 'CSV Validator')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>CSV Validator</h1>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <form method="post" action="{{ route('settings.csv-validator') }}">
      @csrf

      <div class="accordion mb-3" id="csvValidatorAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="validator-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#validator-collapse" aria-expanded="false" aria-controls="validator-collapse">
              CSV Validator settings
            </button>
          </h2>
          <div id="validator-collapse" class="accordion-collapse collapse" aria-labelledby="validator-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">CSV Validator default behaviour when CSV Import is run</label>
                <div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="settings[csv_validator_default_import_behaviour]" id="validator_off" value="0" {{ $settings['csv_validator_default_import_behaviour'] == '0' ? 'checked' : '' }}>
                    <label class="form-check-label" for="validator_off">Off - validation is not run before CSV imports</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="settings[csv_validator_default_import_behaviour]" id="validator_permissive" value="1" {{ $settings['csv_validator_default_import_behaviour'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="validator_permissive">Permissive - validation is run; warnings are ignored</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="settings[csv_validator_default_import_behaviour]" id="validator_strict" value="2" {{ $settings['csv_validator_default_import_behaviour'] == '2' ? 'checked' : '' }}>
                    <label class="form-check-label" for="validator_strict">Strict - validation is run; warnings will halt import from running</label>
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
