@extends('theme::layouts.1col')
@section('title', 'Branding Configuration')
@section('body-class', 'admin heritage')

@php
$branding = (array) ($branding ?? []);
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._admin-sidebar')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-palette me-2"></i>Branding Configuration</h1>
    </div>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('heritage.admin-branding') }}" method="post" enctype="multipart/form-data">
      @csrf

      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ __('Colors') }}</h5></div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="primary_color" class="form-label">Primary Color <span class="badge bg-secondary ms-1">Optional</span></label>
              <div class="input-group">
                <input type="color" class="form-control form-control-color" id="primary_color_picker"
                       value="{{ $branding['primary_color'] ?? '#0d6efd' }}"
                       onchange="document.getElementById('primary_color').value = this.value;">
                <input type="text" class="form-control" id="primary_color" name="primary_color"
                       value="{{ $branding['primary_color'] ?? '#0d6efd' }}" pattern="#[0-9A-Fa-f]{6}">
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="secondary_color" class="form-label">Secondary Color <span class="badge bg-secondary ms-1">Optional</span></label>
              <div class="input-group">
                <input type="color" class="form-control form-control-color" id="secondary_color_picker"
                       value="{{ $branding['secondary_color'] ?? '#6c757d' }}"
                       onchange="document.getElementById('secondary_color').value = this.value;">
                <input type="text" class="form-control" id="secondary_color" name="secondary_color"
                       value="{{ $branding['secondary_color'] ?? '' }}" pattern="#[0-9A-Fa-f]{6}">
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="accent_color" class="form-label">Accent Color <span class="badge bg-secondary ms-1">Optional</span></label>
              <div class="input-group">
                <input type="color" class="form-control form-control-color" id="accent_color_picker"
                       value="{{ $branding['accent_color'] ?? '#198754' }}"
                       onchange="document.getElementById('accent_color').value = this.value;">
                <input type="text" class="form-control" id="accent_color" name="accent_color"
                       value="{{ $branding['accent_color'] ?? '' }}" pattern="#[0-9A-Fa-f]{6}">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ __('Logos') }}</h5></div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="logo_path" class="form-label">Logo URL <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="logo_path" name="logo_path"
                     value="{{ $branding['logo_path'] ?? '' }}" placeholder="{{ __('/uploads/logo.png') }}">
              <div class="form-text">Path to main logo image.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="favicon_path" class="form-label">Favicon URL <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="favicon_path" name="favicon_path"
                     value="{{ $branding['favicon_path'] ?? '' }}" placeholder="{{ __('/uploads/favicon.ico') }}">
              <div class="form-text">Path to favicon image.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ __('Text') }}</h5></div>
        <div class="card-body">
          <div class="mb-3">
            <label for="banner_text" class="form-label">Banner Text <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" class="form-control" id="banner_text" name="banner_text"
                   value="{{ $branding['banner_text'] ?? '' }}" placeholder="{{ __('Optional announcement banner') }}">
          </div>
          <div class="mb-3">
            <label for="footer_text" class="form-label">Footer Text <span class="badge bg-secondary ms-1">Optional</span></label>
            <textarea class="form-control" id="footer_text" name="footer_text" rows="2">{{ $branding['footer_text'] ?? '' }}</textarea>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ __('Social Links') }}</h5></div>
        <div class="card-body">
          @php $socialLinks = $branding['social_links'] ?? []; @endphp
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="social_facebook" class="form-label"><i class="fab fa-facebook me-2"></i>Facebook <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="url" class="form-control" id="social_facebook" name="social_facebook" value="{{ $socialLinks['facebook'] ?? '' }}">
            </div>
            <div class="col-md-6 mb-3">
              <label for="social_twitter" class="form-label"><i class="fab fa-twitter me-2"></i>Twitter/X <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="url" class="form-control" id="social_twitter" name="social_twitter" value="{{ $socialLinks['twitter'] ?? '' }}">
            </div>
            <div class="col-md-6 mb-3">
              <label for="social_instagram" class="form-label"><i class="fab fa-instagram me-2"></i>Instagram <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="url" class="form-control" id="social_instagram" name="social_instagram" value="{{ $socialLinks['instagram'] ?? '' }}">
            </div>
            <div class="col-md-6 mb-3">
              <label for="social_linkedin" class="form-label"><i class="fab fa-linkedin me-2"></i>LinkedIn <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="url" class="form-control" id="social_linkedin" name="social_linkedin" value="{{ $socialLinks['linkedin'] ?? '' }}">
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ __('Custom CSS') }}</h5></div>
        <div class="card-body">
          <textarea class="form-control font-monospace" id="custom_css" name="custom_css" rows="6"
                    placeholder="{{ __('/* Add custom CSS styles here */') }}">{{ $branding['custom_css'] ?? '' }}</textarea>
          <div class="form-text">Custom CSS to apply across the site.</div>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('heritage.admin') }}" class="btn atom-btn-white">Cancel</a>
        <button type="submit" class="btn atom-btn-secondary"><i class="fas fa-check me-2"></i>Save Branding</button>
      </div>
    </form>
  </div>
</div>
@endsection
