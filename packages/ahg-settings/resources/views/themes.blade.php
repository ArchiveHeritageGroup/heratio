@extends('theme::layouts.1col')

@section('title', 'Theme Settings')
@section('body-class', 'admin settings')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-palette me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Theme Settings</h1>
      <span class="small text-muted">Customize colours, logo, branding, and custom CSS</span>
    </div>
  </div>


  <form method="POST" action="{{ route('settings.themes') }}">
    @csrf

    {{-- Branding --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-stamp me-2"></i>Branding</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Logo Path</label>
            <input type="text" class="form-control" name="ahg_logo_path" value="{{ e($settings['ahg_logo_path'] ?? '') }}">
            <div class="form-text">Path relative to web root, e.g. /images/logo.png</div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Footer Text</label>
            <input type="text" class="form-control" name="ahg_footer_text" value="{{ e($settings['ahg_footer_text'] ?? '') }}">
          </div>
          <div class="col-md-3 mb-3">
            <div class="form-check form-switch mt-4">
              <input class="form-check-input" type="checkbox" name="ahg_theme_enabled" value="true" {{ ($settings['ahg_theme_enabled'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label">Theme Enabled</label>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="form-check form-switch mt-4">
              <input class="form-check-input" type="checkbox" name="ahg_show_branding" value="true" {{ ($settings['ahg_show_branding'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label">Show Branding</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Site Description Bar --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-window-maximize me-2"></i>Site Description Bar</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Background</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_descbar_bg" value="{{ $settings['ahg_descbar_bg'] ?? '#005837' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_descbar_bg'] ?? '#005837' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Text Colour</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_descbar_text" value="{{ $settings['ahg_descbar_text'] ?? '#ffffff' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_descbar_text'] ?? '#ffffff' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Primary Colours --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-tint me-2"></i>Primary Colours</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Primary Colour</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_primary_color" value="{{ $settings['ahg_primary_color'] ?? '#005837' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_primary_color'] ?? '#005837' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Secondary Colour</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_secondary_color" value="{{ $settings['ahg_secondary_color'] ?? '#37A07F' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_secondary_color'] ?? '#37A07F' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Link Colour</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_link_color" value="{{ $settings['ahg_link_color'] ?? '#005837' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_link_color'] ?? '#005837' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Page Background --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-fill-drip me-2"></i>Page Background</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Background Colour</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_body_bg" value="{{ $settings['ahg_body_bg'] ?? '#ffffff' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_body_bg'] ?? '#ffffff' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
            <div class="form-text">Background colour applied to the page body and content area</div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Text Colour</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_body_text" value="{{ $settings['ahg_body_text'] ?? '#212529' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_body_text'] ?? '#212529' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Card Header --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-square me-2"></i>Card Headers</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Background</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_card_header_bg" value="{{ $settings['ahg_card_header_bg'] ?? '#005837' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_card_header_bg'] ?? '#005837' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Text Colour</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_card_header_text" value="{{ $settings['ahg_card_header_text'] ?? '#ffffff' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_card_header_text'] ?? '#ffffff' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Preview</label>
            <div class="card">
              <div class="card-header" id="preview-header" style="background-color: {{ $settings['ahg_card_header_bg'] ?? '#005837' }}; color: {{ $settings['ahg_card_header_text'] ?? '#ffffff' }};">
                <h5 class="mb-0" style="color: inherit !important;">Sample Card Header</h5>
              </div>
              <div class="card-body"><p class="mb-0 text-muted">Card body content</p></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Buttons --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-mouse-pointer me-2"></i>Buttons</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Button Background</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_button_bg" value="{{ $settings['ahg_button_bg'] ?? '#005837' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_button_bg'] ?? '#005837' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Button Text</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_button_text" value="{{ $settings['ahg_button_text'] ?? '#ffffff' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_button_text'] ?? '#ffffff' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Sidebar --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-columns me-2"></i>Sidebar</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Sidebar Background</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_sidebar_bg" value="{{ $settings['ahg_sidebar_bg'] ?? '#f8f9fa' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_sidebar_bg'] ?? '#f8f9fa' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Sidebar Text</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_sidebar_text" value="{{ $settings['ahg_sidebar_text'] ?? '#333333' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_sidebar_text'] ?? '#333333' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Footer --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-shoe-prints me-2"></i>Footer</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Footer Background</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_footer_bg" value="{{ $settings['ahg_footer_bg'] ?? '#005837' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_footer_bg'] ?? '#005837' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Footer Text</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_footer_text_color" value="{{ $settings['ahg_footer_text_color'] ?? '#ffffff' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_footer_text_color'] ?? '#ffffff' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Bootstrap Contextual Colours --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-swatchbook me-2"></i>Contextual Colours</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Success</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_success_color" value="{{ $settings['ahg_success_color'] ?? '#28a745' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_success_color'] ?? '#28a745' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Danger</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_danger_color" value="{{ $settings['ahg_danger_color'] ?? '#dc3545' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_danger_color'] ?? '#dc3545' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Warning</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_warning_color" value="{{ $settings['ahg_warning_color'] ?? '#ffc107' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_warning_color'] ?? '#ffc107' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Info</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_info_color" value="{{ $settings['ahg_info_color'] ?? '#17a2b8' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_info_color'] ?? '#17a2b8' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Light</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_light_color" value="{{ $settings['ahg_light_color'] ?? '#f8f9fa' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_light_color'] ?? '#f8f9fa' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Dark</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_dark_color" value="{{ $settings['ahg_dark_color'] ?? '#343a40' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_dark_color'] ?? '#343a40' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Muted</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_muted_color" value="{{ $settings['ahg_muted_color'] ?? '#6c757d' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_muted_color'] ?? '#6c757d' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Border</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_border_color" value="{{ $settings['ahg_border_color'] ?? '#dee2e6' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_border_color'] ?? '#dee2e6' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Custom CSS --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-code me-2"></i>Custom CSS</h5></div>
      <div class="card-body">
        <textarea class="form-control font-monospace" name="ahg_custom_css" rows="8" placeholder="/* Add custom CSS overrides here */">{{ $settings['ahg_custom_css'] ?? '' }}</textarea>
        <div class="form-text">CSS entered here will be appended to the generated theme stylesheet.</div>
      </div>
    </div>

    <div class="d-flex gap-2 mb-4">
      <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Save Theme Settings</button>
      <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">Back to Settings</a>
    </div>
  </form>
@endsection
