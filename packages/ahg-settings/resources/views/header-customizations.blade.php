@extends('theme::layouts.1col')
@section('title', 'Header customizations')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Header customizations</h1>

    <form method="post" action="{{ route('settings.header-customizations') }}" enctype="multipart/form-data">
      @csrf

      <div class="accordion mb-3" id="headerAccordion">

        {{-- Upload logo --}}
        <div class="accordion-item">
          <h2 class="accordion-header" id="logo-heading">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#logo-collapse" aria-expanded="true" aria-controls="logo-collapse">
              Upload logo
            </button>
          </h2>
          <div id="logo-collapse" class="accordion-collapse collapse show" aria-labelledby="logo-heading">
            <div class="alert alert-info m-3 mb-0">
              <p>The logo file must be in "Portable Network Graphics" (PNG) format and the maximum height recommendation for a logo is 50px.</p>
              <p class="mb-0">Note that browser cache may need to be cleared after uploading a new logo.</p>
            </div>
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label" for="logo">Upload logo</label>
                <input type="file" name="logo" id="logo" class="form-control" accept=".png">
              </div>
              <div class="mb-3">
                <div class="form-check">
                  <input type="checkbox" name="restore_logo" id="restore_logo" class="form-check-input" value="1">
                  <label class="form-check-label" for="restore_logo">Restore default logo</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Upload favicon --}}
        <div class="accordion-item">
          <h2 class="accordion-header" id="favicon-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#favicon-collapse" aria-expanded="false" aria-controls="favicon-collapse">
              Upload favicon
            </button>
          </h2>
          <div id="favicon-collapse" class="accordion-collapse collapse" aria-labelledby="favicon-heading">
            <div class="alert alert-info m-3 mb-0">
              <p>The favicon file must be in ICO file format.</p>
              <p class="mb-0">Note that browser cache may need to be cleared after uploading a new favicon.</p>
            </div>
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label" for="favicon">Upload favicon</label>
                <input type="file" name="favicon" id="favicon" class="form-control" accept=".ico">
              </div>
              <div class="mb-3">
                <div class="form-check">
                  <input type="checkbox" name="restore_favicon" id="restore_favicon" class="form-check-input" value="1">
                  <label class="form-check-label" for="restore_favicon">Restore default favicon</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Change header background colour --}}
        <div class="accordion-item">
          <h2 class="accordion-header" id="background-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#background-collapse" aria-expanded="false" aria-controls="background-collapse">
              Change header background colour
            </button>
          </h2>
          <div id="background-collapse" class="accordion-collapse collapse" aria-labelledby="background-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label" for="header_background_colour">Background colour</label>
                <input type="color" name="settings[header_background_colour]" id="header_background_colour" class="form-control form-control-color" value="{{ $settings['header_background_colour'] ?? '#212529' }}">
              </div>
            </div>
          </div>
        </div>

      </div>

      <section class="actions">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>

    </form>
  </div>
</div>
@endsection
