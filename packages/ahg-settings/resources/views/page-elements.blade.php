@extends('theme::layouts.1col')
@section('title', 'Default page elements')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-settings::_menu')
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-eye me-2"></i>Default page elements</h1>

    <form method="post" action="{{ route('settings.visible-elements') }}">
      @csrf
      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#elements-collapse">Default page elements settings</button></h2>
          <div id="elements-collapse" class="accordion-collapse collapse show">
            <div class="accordion-body">
              <p>Enable or disable the display of certain page elements. Unless they have been overridden by a specific theme, these settings will be used site wide.</p>
              @foreach($settings as $name => $setting)
                <div class="form-check mb-2">
                  <input type="hidden" name="settings.{{ $setting->id }}" value="0">
                  <input class="form-check-input" type="checkbox" name="settings.{{ $setting->id }}" id="ve-{{ $setting->id }}" value="1" {{ ($setting->value ?? '0') == '1' ? 'checked' : '' }}>
                  <label class="form-check-label" for="ve-{{ $setting->id }}">{{ ucfirst(str_replace('_', ' ', $name)) }}</label>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
