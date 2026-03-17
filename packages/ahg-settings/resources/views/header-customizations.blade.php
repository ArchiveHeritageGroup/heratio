@extends('theme::layouts.1col')
@section('title', 'Header customizations')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Header customizations</h1>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <form method="post" action="{{ route('settings.header-customizations') }}">
      @csrf

      <div class="accordion mb-3" id="headerAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="header-colors-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#header-colors-collapse" aria-expanded="false" aria-controls="header-colors-collapse">
              Header colors
            </button>
          </h2>
          <div id="header-colors-collapse" class="accordion-collapse collapse" aria-labelledby="header-colors-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Header background color</label>
                <div class="input-group">
                  <input type="color" class="form-control form-control-color" value="{{ $settings['header_background_color'] ?: '#ffffff' }}" onchange="document.getElementById('header_bg_text').value=this.value">
                  <input type="text" name="settings[header_background_color]" id="header_bg_text" class="form-control" value="{{ e($settings['header_background_color']) }}" placeholder="#ffffff">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Header text color</label>
                <div class="input-group">
                  <input type="color" class="form-control form-control-color" value="{{ $settings['header_text_color'] ?: '#000000' }}" onchange="document.getElementById('header_text_text').value=this.value">
                  <input type="text" name="settings[header_text_color]" id="header_text_text" class="form-control" value="{{ e($settings['header_text_color']) }}" placeholder="#000000">
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header" id="header-html-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#header-html-collapse" aria-expanded="false" aria-controls="header-html-collapse">
              Custom header HTML
            </button>
          </h2>
          <div id="header-html-collapse" class="accordion-collapse collapse" aria-labelledby="header-html-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Custom HTML to insert in the header</label>
                <textarea name="settings[header_custom_html]" class="form-control" rows="6">{{ e($settings['header_custom_html']) }}</textarea>
                <small class="text-muted">HTML will be inserted into the header area of all pages. Use this for custom branding, logos, or announcements.</small>
              </div>
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header" id="header-css-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#header-css-collapse" aria-expanded="false" aria-controls="header-css-collapse">
              Custom header CSS
            </button>
          </h2>
          <div id="header-css-collapse" class="accordion-collapse collapse" aria-labelledby="header-css-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Custom CSS for header styling</label>
                <textarea name="settings[header_custom_css]" class="form-control font-monospace" rows="8">{{ e($settings['header_custom_css']) }}</textarea>
                <small class="text-muted">CSS will be applied to the header area. Use this to customize header layout and styling.</small>
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
