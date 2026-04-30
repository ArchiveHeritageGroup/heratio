{{--
  Carousel Settings — cloned from AtoM ahgIiifPlugin/iiif/settingsSuccess.php
  Copyright (C) 2026 Johan Pieterse — Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Carousel Settings')
@section('body-class', 'admin iiif settings')

@section('sidebar')
<div class="sidebar-content">
  <div class="card mb-3">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('About') }}</h5>
    </div>
    <div class="card-body">
      <p class="small text-muted mb-0">Configure IIIF image display and homepage featured collections.</p>
    </div>
  </div>
  <div class="card">
    <div class="card-header bg-light">
      <h5 class="mb-0"><i class="fas fa-link me-2"></i>{{ __('Quick Links') }}</h5>
    </div>
    <div class="list-group list-group-flush">
      @if(\Route::has('iiif-collection.index'))
      <a href="{{ route('iiif-collection.index') }}" class="list-group-item list-group-item-action">
        <i class="fas fa-layer-group me-2"></i>{{ __('Manage Collections') }}
      </a>
      @endif
      <a href="{{ url('/') }}" class="list-group-item list-group-item-action" target="_blank">
        <i class="fas fa-home me-2"></i>{{ __('View Homepage') }}
      </a>
    </div>
  </div>
</div>
@endsection

@section('title-block')
  <h1><i class="fas fa-images me-2"></i>{{ __('Carousel Settings') }}</h1>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="post" action="{{ route('iiif.settings.update') }}">
    @csrf

    {{-- Homepage Featured Collection --}}
    <div class="card mb-4">
      <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="fas fa-home me-2"></i>{{ __('Homepage Featured Collection') }}</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" name="homepage_collection_enabled" value="1" id="homepageEnabled"
                     {{ ($settings['homepage_collection_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="homepageEnabled"><strong>{{ __('Enable homepage carousel') }}</strong></label>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Select Collection to Feature') }}</label>
              <select name="homepage_collection_id" class="form-select">
                <option value="">-- Select a collection --</option>
                @foreach($collections ?? [] as $col)
                  <option value="{{ $col->id }}" {{ ($settings['homepage_collection_id'] ?? '') == $col->id ? 'selected' : '' }}>
                    {{ $col->name }} ({{ $col->item_count ?? 0 }} items){{ !empty($col->is_public) ? '' : ' [Private]' }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">Choose which collection to display on the homepage.</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">{{ __('Carousel Height') }}</label>
              <select name="homepage_carousel_height" class="form-select">
                @foreach(['300px' => '300px (Small)', '400px' => '400px (Medium)', '450px' => '450px (Default)', '500px' => '500px (Large)', '600px' => '600px (Extra Large)', '70vh' => '70% Viewport'] as $val => $label)
                  <option value="{{ $val }}" {{ ($settings['homepage_carousel_height'] ?? '450px') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Max Items to Display') }}</label>
              <input type="number" name="homepage_max_items" class="form-control"
                     value="{{ $settings['homepage_max_items'] ?? '12' }}" min="1" max="50">
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-4">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="homepage_carousel_autoplay" value="1" id="homepageAutoplay"
                     {{ ($settings['homepage_carousel_autoplay'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="homepageAutoplay">{{ __('Auto-rotate slides') }}</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="homepage_show_captions" value="1" id="homepageCaptions"
                     {{ ($settings['homepage_show_captions'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="homepageCaptions">{{ __('Show image captions') }}</label>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Rotation Speed (ms)') }}</label>
            <input type="number" name="homepage_carousel_interval" class="form-control form-control-sm"
                   value="{{ $settings['homepage_carousel_interval'] ?? '5000' }}" min="1000" max="15000" step="500">
          </div>
        </div>
      </div>
    </div>

    {{-- Record Page Viewer --}}
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-tv me-2"></i>{{ __('Record Page Viewer') }}</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <label class="form-label">{{ __('Display Type') }}</label>
            <select name="viewer_type" class="form-select" id="viewerType">
              <option value="carousel" {{ ($settings['viewer_type'] ?? '') === 'carousel' ? 'selected' : '' }}>{{ __('Carousel (Bootstrap 5)') }}</option>
              <option value="single" {{ ($settings['viewer_type'] ?? '') === 'single' ? 'selected' : '' }}>{{ __('Single Image with Zoom') }}</option>
              <option value="openseadragon" {{ ($settings['viewer_type'] ?? '') === 'openseadragon' ? 'selected' : '' }}>{{ __('OpenSeadragon (Deep Zoom)') }}</option>
              <option value="mirador" {{ ($settings['viewer_type'] ?? 'mirador') === 'mirador' ? 'selected' : '' }}>{{ __('Mirador (Full IIIF Viewer)') }}</option>
            </select>
            <div class="form-text">Choose how images are displayed on record pages.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Viewer Height') }}</label>
            <select name="viewer_height" class="form-select">
              @foreach(['300px' => '300px (Small)', '400px' => '400px (Medium)', '500px' => '500px (Default)', '600px' => '600px (Large)', '700px' => '700px (Extra Large)', '80vh' => '80% Viewport'] as $val => $label)
                <option value="{{ $val }}" {{ ($settings['viewer_height'] ?? '500px') === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
    </div>

    {{-- Carousel Options --}}
    <div class="card mb-4" id="carouselOptions">
      <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>{{ __('Carousel Options') }}</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" name="carousel_autoplay" value="1" id="autoplay"
                     {{ ($settings['carousel_autoplay'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="autoplay">{{ __('Auto-rotate slides') }}</label>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Rotation Interval (ms)') }}</label>
              <input type="number" name="carousel_interval" class="form-control"
                     value="{{ $settings['carousel_interval'] ?? '5000' }}" min="1000" max="15000" step="500">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" name="carousel_show_thumbnails" value="1" id="showThumbs"
                     {{ ($settings['carousel_show_thumbnails'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="showThumbs">{{ __('Show thumbnail navigation') }}</label>
            </div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" name="carousel_show_controls" value="1" id="showControls"
                     {{ ($settings['carousel_show_controls'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="showControls">{{ __('Show prev/next controls') }}</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Appearance --}}
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-palette me-2"></i>{{ __('Appearance') }}</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4">
            <label class="form-label">{{ __('Background Color') }}</label>
            <input type="color" name="background_color" class="form-control form-control-color w-100"
                   value="{{ $settings['background_color'] ?? '#000000' }}">
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch mt-4">
              <input class="form-check-input" type="checkbox" name="enable_fullscreen" value="1" id="fullscreen"
                     {{ ($settings['enable_fullscreen'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="fullscreen">{{ __('Enable fullscreen button') }}</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch mt-4">
              <input class="form-check-input" type="checkbox" name="show_zoom_controls" value="1" id="zoomControls"
                     {{ ($settings['show_zoom_controls'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="zoomControls">{{ __('Show zoom controls') }}</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Display Locations --}}
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-eye me-2"></i>{{ __('Display Locations') }}</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="show_on_view" value="1" id="showOnView"
                     {{ ($settings['show_on_view'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="showOnView">{{ __('Show on record view page') }}</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="show_on_browse" value="1" id="showOnBrowse"
                     {{ ($settings['show_on_browse'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="showOnBrowse">{{ __('Show on browse page (cards)') }}</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>{{ __('Save Settings') }}</button>
      <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary btn-lg"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a>
    </div>
  </form>

  <script>
  document.getElementById('viewerType').addEventListener('change', function() {
    document.getElementById('carouselOptions').style.display = (this.value === 'carousel') ? 'block' : 'none';
  });
  document.getElementById('viewerType').dispatchEvent(new Event('change'));
  </script>
@endsection
