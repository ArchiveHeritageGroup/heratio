@extends('theme::layouts.1col')
@section('title', $sectionLabel)
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>{{ $sectionLabel }}</h1>
    <p class="text-muted">{{ $settings->count() }} {{ Str::plural('setting', $settings->count()) }}</p>


    @if($settings->isEmpty())
      <div class="alert alert-info">No editable settings found in this section.</div>
    @else
      <form method="post" action="{{ route('settings.section', $section) }}">
        @csrf

        <div class="card mb-3">
          <div class="card-body">
            @foreach($settings as $setting)
              @php
                $val = $setting->value ?? '';
                $name = $setting->name;
                $label = ucfirst(str_replace('_', ' ', $name));
                // Detect boolean: value is 0/1/true/false or name contains _enabled/_disabled
                $isBoolean = in_array(strtolower($val), ['0', '1', 'true', 'false', 'yes', 'no'])
                             || str_contains($name, '_enabled')
                             || str_contains($name, '_disabled');
                $isNumeric = !$isBoolean && is_numeric($val) && $val !== '';
              @endphp

              @if($isBoolean)
                <div class="mb-3">
                  <div class="form-check form-switch">
                    <input type="hidden" name="settings[{{ $setting->id }}]" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="settings[{{ $setting->id }}]" id="setting-{{ $setting->id }}" value="1"
                           {{ in_array(strtolower($val), ['1', 'true', 'yes']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="setting-{{ $setting->id }}">
                      {{ $label }} <span class="badge bg-secondary ms-1">Optional</span>
                    </label>
                  </div>
                </div>
              @elseif($isNumeric)
                <div class="mb-3">
                  <label for="setting-{{ $setting->id }}" class="form-label fw-semibold">{{ $label }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" class="form-control" name="settings[{{ $setting->id }}]"
                         id="setting-{{ $setting->id }}" value="{{ e($val) }}" style="max-width: 300px;">
                </div>
              @else
                <div class="mb-3">
                  <label for="setting-{{ $setting->id }}" class="form-label fw-semibold">{{ $label }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" class="form-control" name="settings[{{ $setting->id }}]"
                         id="setting-{{ $setting->id }}" value="{{ e($val) }}">
                </div>
              @endif
            @endforeach
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
          <a href="{{ route('settings.index') }}" class="btn atom-btn-white">Back</a>
        </div>
      </form>
    @endif
  </div>
</div>
@endsection
