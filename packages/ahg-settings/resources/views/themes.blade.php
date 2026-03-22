@extends('theme::layouts.1col')

@section('title', 'Theme Settings')
@section('body-class', 'admin settings')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-palette me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">List themes</h1>
      <span class="small text-muted">Customize colours, logo, branding, and custom CSS</span>
    </div>
  </div>


  <form method="POST" action="{{ route('settings.themes') }}">
    @csrf

    {{-- Branding --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-stamp me-2"></i>Branding</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Logo Path <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" class="form-control" name="ahg_logo_path" value="{{ e($settings['ahg_logo_path'] ?? '') }}">
            <div class="form-text">Path relative to web root, e.g. /images/logo.png</div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Footer Text <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" class="form-control" name="ahg_footer_text" value="{{ e($settings['ahg_footer_text'] ?? '') }}">
          </div>
          <div class="col-md-3 mb-3">
            <div class="form-check form-switch mt-4">
              <input class="form-check-input" type="checkbox" name="ahg_theme_enabled" value="true" {{ ($settings['ahg_theme_enabled'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label">Theme Enabled <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="form-check form-switch mt-4">
              <input class="form-check-input" type="checkbox" name="ahg_show_branding" value="true" {{ ($settings['ahg_show_branding'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label">Show Branding <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Header / Navbar --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-heading me-2"></i>Header / Navbar</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Background <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_header_bg" value="{{ $settings['ahg_header_bg'] ?? '#212529' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_header_bg'] ?? '#212529' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Text Colour <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_header_text" value="{{ $settings['ahg_header_text'] ?? '#ffffff' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_header_text'] ?? '#ffffff' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Site Description Bar --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-window-maximize me-2"></i>Site Description Bar</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Background <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_descbar_bg" value="{{ $settings['ahg_descbar_bg'] ?? '#005837' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_descbar_bg'] ?? '#005837' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Text Colour <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_descbar_text" value="{{ $settings['ahg_descbar_text'] ?? '#ffffff' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_descbar_text'] ?? '#ffffff' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Text Alignment <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="ahg_descbar_align" class="form-select">
              <option value="left" {{ ($settings['ahg_descbar_align'] ?? 'left') === 'left' ? 'selected' : '' }}>Left</option>
              <option value="center" {{ ($settings['ahg_descbar_align'] ?? '') === 'center' ? 'selected' : '' }}>Centre</option>
              <option value="right" {{ ($settings['ahg_descbar_align'] ?? '') === 'right' ? 'selected' : '' }}>Right</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    {{-- Primary Colours --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-tint me-2"></i>Primary Colours</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Primary Colour <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_primary_color" value="{{ $settings['ahg_primary_color'] ?? '#005837' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_primary_color'] ?? '#005837' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Secondary Colour <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_secondary_color" value="{{ $settings['ahg_secondary_color'] ?? '#37A07F' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_secondary_color'] ?? '#37A07F' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Link Colour <span class="badge bg-secondary ms-1">Optional</span></label>
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
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-fill-drip me-2"></i>Page Background</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Background Colour <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_body_bg" value="{{ $settings['ahg_body_bg'] ?? '#ffffff' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_body_bg'] ?? '#ffffff' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
            <div class="form-text">Background colour applied to the page body and content area</div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Text Colour <span class="badge bg-secondary ms-1">Optional</span></label>
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
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-square me-2"></i>Card Headers</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Background <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_card_header_bg" value="{{ $settings['ahg_card_header_bg'] ?? '#005837' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_card_header_bg'] ?? '#005837' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Text Colour <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_card_header_text" value="{{ $settings['ahg_card_header_text'] ?? '#ffffff' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_card_header_text'] ?? '#ffffff' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Preview <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="card">
              <div class="card-header" id="preview-header" style="background:var(--ahg-primary);color:#fff">
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
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-mouse-pointer me-2"></i>Buttons</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Button Background <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_button_bg" value="{{ $settings['ahg_button_bg'] ?? '#005837' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_button_bg'] ?? '#005837' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Button Text <span class="badge bg-secondary ms-1">Optional</span></label>
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
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-columns me-2"></i>Sidebar</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Sidebar Background <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_sidebar_bg" value="{{ $settings['ahg_sidebar_bg'] ?? '#f8f9fa' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_sidebar_bg'] ?? '#f8f9fa' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Sidebar Text <span class="badge bg-secondary ms-1">Optional</span></label>
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
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-shoe-prints me-2"></i>Footer</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Background <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_footer_bg" value="{{ $settings['ahg_footer_bg'] ?? '#005837' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_footer_bg'] ?? '#005837' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Text Colour <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_footer_text_color" value="{{ $settings['ahg_footer_text_color'] ?? '#ffffff' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_footer_text_color'] ?? '#ffffff' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Copyright Start Year <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" class="form-control" name="ahg_footer_copyright" value="{{ $settings['ahg_footer_copyright'] ?? date('Y') }}" placeholder="2019">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Disclaimer <span class="badge bg-secondary ms-1">Optional</span></label>
          <textarea class="form-control" name="ahg_footer_disclaimer" rows="2" placeholder="Research use only...">{{ $settings['ahg_footer_disclaimer'] ?? '' }}</textarea>
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">System Name <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" class="form-control" name="ahg_footer_system_name" value="{{ $settings['ahg_footer_system_name'] ?? '' }}" placeholder="Public Service Information System">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Organisation Name <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" class="form-control" name="ahg_footer_org_name" value="{{ $settings['ahg_footer_org_name'] ?? '' }}" placeholder="The Archive and Heritage Group">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Organisation URL <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="url" class="form-control" name="ahg_footer_org_url" value="{{ $settings['ahg_footer_org_url'] ?? '' }}" placeholder="https://theahg.co.za">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Standards Badges <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="text" class="form-control" name="ahg_footer_standards" value="{{ $settings['ahg_footer_standards'] ?? '' }}" placeholder="ISAD(G), RiC-O, OAIS/BagIt, WCAG 2.1 AA">
          <div class="form-text">Comma-separated list of standards. Each becomes a badge.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Policy Links <span class="badge bg-secondary ms-1">Optional</span></label>
          <textarea class="form-control font-monospace" name="ahg_footer_links" rows="4" placeholder="Privacy policy|/privacy&#10;Terms of use|/terms">{{ $settings['ahg_footer_links'] ?? '' }}</textarea>
          <div class="form-text">One per line: <code>Label|/url</code></div>
        </div>
        <div class="mb-3">
          <label class="form-label">Utility Links <span class="badge bg-secondary ms-1">Optional</span></label>
          <textarea class="form-control font-monospace" name="ahg_footer_utility_links" rows="2" placeholder="Help|/help&#10;Contact|/contact">{{ $settings['ahg_footer_utility_links'] ?? '' }}</textarea>
          <div class="form-text">One per line: <code>Label|/url</code></div>
        </div>
      </div>
    </div>

    {{-- Bootstrap Contextual Colours --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-swatchbook me-2"></i>Contextual Colours</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Success <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_success_color" value="{{ $settings['ahg_success_color'] ?? '#28a745' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_success_color'] ?? '#28a745' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Danger <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_danger_color" value="{{ $settings['ahg_danger_color'] ?? '#dc3545' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_danger_color'] ?? '#dc3545' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Warning <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_warning_color" value="{{ $settings['ahg_warning_color'] ?? '#ffc107' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_warning_color'] ?? '#ffc107' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Info <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_info_color" value="{{ $settings['ahg_info_color'] ?? '#17a2b8' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_info_color'] ?? '#17a2b8' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Light <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_light_color" value="{{ $settings['ahg_light_color'] ?? '#f8f9fa' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_light_color'] ?? '#f8f9fa' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Dark <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_dark_color" value="{{ $settings['ahg_dark_color'] ?? '#343a40' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_dark_color'] ?? '#343a40' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Muted <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" name="ahg_muted_color" value="{{ $settings['ahg_muted_color'] ?? '#6c757d' }}" oninput="this.nextElementSibling.value=this.value">
              <input type="text" class="form-control" value="{{ $settings['ahg_muted_color'] ?? '#6c757d' }}" oninput="this.previousElementSibling.value=this.value" pattern="#[0-9a-fA-F]{6}">
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Border <span class="badge bg-secondary ms-1">Optional</span></label>
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
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-code me-2"></i>Custom CSS</h5></div>
      <div class="card-body">
        <textarea class="form-control font-monospace" name="ahg_custom_css" rows="8" placeholder="/* Add custom CSS overrides here */">{{ $settings['ahg_custom_css'] ?? '' }}</textarea>
        <div class="form-text">CSS entered here will be appended to the generated theme stylesheet.</div>
      </div>
    </div>

    <div class="d-flex gap-2 mb-4">
      <input type="submit" class="btn atom-btn-outline-success" value="Save">
      <button type="button" class="btn atom-btn-white" id="btnLivePreview">
        <i class="fas fa-eye me-1"></i>Live Preview
      </button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white">Back to Settings</a>
      <button type="button" class="btn atom-btn-outline-danger ms-auto" id="btnResetDefaults"
              onclick="return confirm('Reset all theme settings to defaults? This cannot be undone.');">
        <i class="fas fa-undo me-1"></i>Reset to Defaults
      </button>
    </div>
  </form>
@endsection

@push('scripts')
<script>
// Live Preview
document.getElementById('btnLivePreview').addEventListener('click', function() {
    var headerBg = document.querySelector('[name="ahg_header_bg"]');
    var headerText = document.querySelector('[name="ahg_header_text"]');
    var primaryColor = document.querySelector('[name="ahg_primary_color"]');
    var bodyBg = document.querySelector('[name="ahg_body_bg"]');
    var bodyText = document.querySelector('[name="ahg_body_text"]');

    if (primaryColor) document.documentElement.style.setProperty('--ahg-primary', primaryColor.value);
    if (bodyBg) document.body.style.backgroundColor = bodyBg.value;
    if (bodyText) document.body.style.color = bodyText.value;

    // Update preview header
    var previewHeader = document.getElementById('preview-header');
    var cardHeaderBg = document.querySelector('[name="ahg_card_header_bg"]');
    var cardHeaderText = document.querySelector('[name="ahg_card_header_text"]');
    if (previewHeader && cardHeaderBg) previewHeader.style.background = cardHeaderBg.value;
    if (previewHeader && cardHeaderText) previewHeader.style.color = cardHeaderText.value;
});

// Reset to Defaults
document.getElementById('btnResetDefaults').addEventListener('click', function() {
    var defaults = {
        ahg_header_bg: '#212529', ahg_header_text: '#ffffff',
        ahg_descbar_bg: '#005837', ahg_descbar_text: '#ffffff', ahg_descbar_align: 'left',
        ahg_primary_color: '#005837', ahg_secondary_color: '#37A07F', ahg_link_color: '#005837',
        ahg_body_bg: '#ffffff', ahg_body_text: '#212529',
        ahg_card_header_bg: '#005837', ahg_card_header_text: '#ffffff',
        ahg_button_bg: '#005837', ahg_button_text: '#ffffff',
        ahg_sidebar_bg: '#f8f9fa', ahg_sidebar_text: '#333333',
        ahg_footer_bg: '#005837', ahg_footer_text_color: '#ffffff',
        ahg_success_color: '#28a745', ahg_danger_color: '#dc3545',
        ahg_warning_color: '#ffc107', ahg_info_color: '#17a2b8',
        ahg_light_color: '#f8f9fa', ahg_dark_color: '#343a40',
        ahg_muted_color: '#6c757d', ahg_border_color: '#dee2e6',
    };
    Object.keys(defaults).forEach(function(key) {
        var inputs = document.querySelectorAll('[name="' + key + '"]');
        inputs.forEach(function(input) {
            input.value = defaults[key];
            if (input.type === 'color') {
                var event = new Event('input', { bubbles: true });
                input.dispatchEvent(event);
            }
        });
    });
});
</script>
@endpush
