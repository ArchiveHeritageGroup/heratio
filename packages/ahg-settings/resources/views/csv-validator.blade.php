@extends('theme::layouts.2col')
@section('title', 'CSV Validator')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>{{ __('CSV Validator') }}</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.csv-validator') }}">
      @csrf

      <div class="accordion mb-3" id="csvValidatorAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="validator-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#validator-collapse" aria-expanded="false" aria-controls="validator-collapse">
              {{ __('CSV Validator settings') }}
            </button>
          </h2>
          <div id="validator-collapse" class="accordion-collapse collapse" aria-labelledby="validator-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">CSV Validator default behaviour when CSV Import is run <span class="badge bg-secondary ms-1">Optional</span></label>
                <div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="settings[csv_validator_default_import_behaviour]" id="validator_off" value="0" {{ $settings['csv_validator_default_import_behaviour'] == '0' ? 'checked' : '' }}>
                    <label class="form-check-label" for="validator_off">Off - validation is not run before CSV imports <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="settings[csv_validator_default_import_behaviour]" id="validator_permissive" value="1" {{ $settings['csv_validator_default_import_behaviour'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="validator_permissive">Permissive - validation is run; warnings are ignored <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="settings[csv_validator_default_import_behaviour]" id="validator_strict" value="2" {{ $settings['csv_validator_default_import_behaviour'] == '2' ? 'checked' : '' }}>
                    <label class="form-check-label" for="validator_strict">Strict - validation is run; warnings will halt import from running <span class="badge bg-secondary ms-1">Optional</span></label>
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
@endsection
