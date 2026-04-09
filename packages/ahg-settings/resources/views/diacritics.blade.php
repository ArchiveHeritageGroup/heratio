@extends('theme::layouts.2col')
@section('title', 'Diacritics settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>Diacritics settings</h1>
@endsection

@section('content')
<div class="alert alert-info">
      <p>Please rebuild the search index after uploading diacritics mappings.</p>
      <pre>$ php artisan search:populate</pre>
    </div>

    <form method="post" action="{{ route('settings.diacritics') }}" enctype="multipart/form-data">
      @csrf

      <div class="accordion mb-3" id="diacriticsAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="diacritics-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#diacritics-collapse" aria-expanded="false" aria-controls="diacritics-collapse">
              Diacritics Settings
            </button>
          </h2>
          <div id="diacritics-collapse" class="accordion-collapse collapse" aria-labelledby="diacritics-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Diacritics <span class="badge bg-secondary ms-1">Optional</span></label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[diacritics]" id="diacritics_disabled" value="0" {{ $settings['diacritics'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="diacritics_disabled">Disabled <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[diacritics]" id="diacritics_enabled" value="1" {{ $settings['diacritics'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="diacritics_enabled">Enabled <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header" id="mappings-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mappings-collapse" aria-expanded="false" aria-controls="mappings-collapse">
              CSV Mapping YAML
            </button>
          </h2>
          <div id="mappings-collapse" class="accordion-collapse collapse" aria-labelledby="mappings-heading">
            <div class="alert alert-info m-3 mb-0">
              <p>Example CSV:</p>
              <pre>type: mapping
mappings:
  - &Agrave; => A
  - &Aacute; => A</pre>
            </div>
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Mappings YAML <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="file" name="mappings" class="form-control" accept=".yml,.yaml">
                <small class="text-muted">Upload a YAML file with diacritics mappings</small>
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
