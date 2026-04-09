@extends('theme::layouts.1col')
@section('title', 'Finding Aid settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu', ['menu' => $menu ?? []])</div>
  <div class="col-md-9">
    <h1>Finding Aid settings</h1>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="post" action="{{ route('settings.finding-aid') }}" data-cy="settings-finding-aid-form">
      @csrf

      <div class="accordion mb-3" id="finding-aid-settings">
        <div class="accordion-item">
          <h2 class="accordion-header" id="finding-aid-heading">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#finding-aid-collapse" aria-expanded="true" aria-controls="finding-aid-collapse">
              Finding Aid settings
            </button>
          </h2>
          <div id="finding-aid-collapse" class="accordion-collapse collapse show" aria-labelledby="finding-aid-heading">
            <div class="accordion-body">

              {{-- Finding Aids enabled --}}
              <div class="mb-3">
                <label class="form-label">Finding Aids enabled</label>
                <div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="settings[finding_aids_enabled]" id="fa_enabled_1" value="1" {{ $settings['finding_aids_enabled'] === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="fa_enabled_1">Enabled</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="settings[finding_aids_enabled]" id="fa_enabled_0" value="0" {{ $settings['finding_aids_enabled'] !== '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="fa_enabled_0">Disabled</label>
                  </div>
                </div>
                <div class="form-text">When disabled: Finding Aid links are not displayed, Finding Aid generation is disabled, and the 'Advanced Search &gt; Finding Aid' filter is hidden</div>
              </div>

              {{-- Finding Aid format --}}
              <div class="mb-3">
                <label for="fa_format" class="form-label">Finding Aid format</label>
                <select name="settings[finding_aid_format]" id="fa_format" class="form-select">
                  <option value="pdf" {{ $settings['finding_aid_format'] === 'pdf' ? 'selected' : '' }}>PDF</option>
                  <option value="rtf" {{ $settings['finding_aid_format'] === 'rtf' ? 'selected' : '' }}>RTF</option>
                </select>
                <div class="form-text">Choose the file format for generated Finding Aids (PDF or 'Rich Text Format')</div>
              </div>

              {{-- Finding Aid model --}}
              <div class="mb-3">
                <label for="fa_model" class="form-label">Finding Aid model</label>
                <select name="settings[finding_aid_model]" id="fa_model" class="form-select">
                  <option value="inventory-summary" {{ $settings['finding_aid_model'] === 'inventory-summary' ? 'selected' : '' }}>Inventory summary</option>
                  <option value="full-details" {{ $settings['finding_aid_model'] === 'full-details' ? 'selected' : '' }}>Full details</option>
                </select>
                <div class="form-text">
                  Finding Aid model:<br>
                  - Inventory summary: will include only key details for lower-level descriptions (file, item, part) in a table<br>
                  - Full details: includes full lower-level descriptions in the same format used throughout the finding aid
                </div>
              </div>

              {{-- Generate Finding Aid from public records --}}
              <div class="mb-3">
                <label class="form-label">Generate Finding Aid from public records</label>
                <div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="settings[public_finding_aid]" id="fa_public_1" value="1" {{ $settings['public_finding_aid'] === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="fa_public_1">Yes</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="settings[public_finding_aid]" id="fa_public_0" value="0" {{ $settings['public_finding_aid'] !== '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="fa_public_0">No</label>
                  </div>
                </div>
                <div class="form-text">When set to 'yes' generated Finding Aids will exclude unpublished records and hidden elements</div>
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
