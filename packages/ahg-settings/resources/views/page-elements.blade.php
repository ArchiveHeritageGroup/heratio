@extends('theme::layouts.1col')
@section('title', 'Default page elements')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Default page elements</h1>

    <form method="post" action="{{ route('settings.page-elements') }}">
      @csrf

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header" id="default-page-elements-heading">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#default-page-elements-collapse" aria-expanded="true" aria-controls="default-page-elements-collapse">
              Default page elements settings
            </button>
          </h2>
          <div id="default-page-elements-collapse" class="accordion-collapse collapse show" aria-labelledby="default-page-elements-heading">
            <div class="accordion-body">
              <p>Enable or disable the display of certain page elements. Unless they have been overridden by a specific theme, these settings will be used site wide.</p>

              @php
                $elementLabels = [
                  'toggleLogo' => 'Logo',
                  'toggleTitle' => 'Title',
                  'toggleDescription' => 'Description',
                  'toggleLanguageMenu' => 'Language menu',
                  'toggleIoSlider' => 'Digital object carousel',
                  'toggleDigitalObjectMap' => 'Digital object map',
                  'toggleCopyrightFilter' => 'Copyright status filter',
                  'toggleMaterialFilter' => 'General material designation filter',
                ];
              @endphp

              @foreach($settings as $name => $setting)
                @php $label = $elementLabels[$name] ?? ucfirst(str_replace('_', ' ', $name)); @endphp
                <div class="form-check mb-2">
                  <input type="hidden" name="settings.{{ $setting->id }}" value="0">
                  <input class="form-check-input" type="checkbox" name="settings.{{ $setting->id }}" id="ve-{{ $setting->id }}" value="1" {{ ($setting->value ?? '0') == '1' ? 'checked' : '' }}>
                  <label class="form-check-label" for="ve-{{ $setting->id }}">{{ $label }}</label>
                </div>
              @endforeach

              @if(empty($settings['toggleDigitalObjectMap']) || !($googleMapsApiKeySet ?? false))
                <small class="text-muted d-block mt-2">Note: The Digital object map feature will not work until a Google Maps API key is specified on the <a href="{{ route('settings.global') }}">global</a> settings page.</small>
              @endif
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
