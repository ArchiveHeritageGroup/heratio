@extends('theme::layouts.1col')
@section('title', 'Default page elements')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu', ['menu' => $menu ?? []])</div>
  <div class="col-md-9">
    <h1>Default page elements</h1>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

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

              @foreach($settings as $name => $setting)
                @php
                  $disabled = ($name === 'toggleDigitalObjectMap' && $setting->value !== '1' && !$googleMapsApiKeySet);
                @endphp
                <div class="form-check mb-2">
                  <input type="hidden" name="{{ $name }}_present" value="1">
                  <input class="form-check-input"
                         type="checkbox"
                         name="{{ $name }}"
                         id="pe-{{ $name }}"
                         value="1"
                         {{ $setting->value === '1' ? 'checked' : '' }}
                         {{ $disabled ? 'disabled' : '' }}>
                  <label class="form-check-label" for="pe-{{ $name }}">{{ $setting->label }}</label>
                  @if($disabled)
                    <div class="form-text text-muted small">
                      This feature will not work until a Google Maps API key is specified on the
                      <a href="{{ route('settings.global') }}">global</a> settings page.
                    </div>
                  @endif
                </div>
              @endforeach
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
