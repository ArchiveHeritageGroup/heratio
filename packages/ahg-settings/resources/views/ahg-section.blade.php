@extends('theme::layouts.1col')
@section('title', $groupLabel . ' - AHG Settings')
@section('body-class', 'admin settings')

@section('content')
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Settings</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $groupLabel }}</li>
    </ol>
  </nav>

  <div class="row">
    {{-- Sidebar menu --}}
    <div class="col-md-3 col-lg-2">
      @include('ahg-settings::_ahg-menu', ['currentGroup' => $group])
    </div>

    {{-- Main content --}}
    <div class="col-md-9 col-lg-10">
      <div class="multiline-header d-flex align-items-center mb-3">
        <i class="fas fa-3x {{ $groupIcon }} me-3" aria-hidden="true"></i>
        <div class="d-flex flex-column">
          <h1 class="mb-0">{{ $groupLabel }}</h1>
          <span class="small text-muted">AHG settings &mdash; {{ $settings->count() }} {{ Str::plural('setting', $settings->count()) }}</span>
        </div>
      </div>


      @if($settings->isEmpty())
        <div class="alert alert-info">No settings found in this group.</div>
      @else
        <form method="post" action="{{ route('settings.ahg', $group) }}">
          @csrf

          <div class="card mb-3">
            <div class="card-body">
              @foreach($settings as $setting)
                @php
                  $key = $setting->setting_key;
                  $val = $setting->setting_value ?? '';
                  $isCheckbox = in_array($key, $checkboxFields);
                  $isSelect = isset($selectFields[$key]);
                  $isColor = in_array($key, $colorFields);
                  $isPassword = in_array($key, $passwordFields);
                  $isTextarea = in_array($key, $textareaFields);
                  $isNumeric = !$isCheckbox && !$isSelect && !$isColor && !$isPassword && !$isTextarea && is_numeric($val) && $val !== '';

                  // Build a human-readable label: strip common group prefixes, replace underscores, ucfirst
                  $prefixes = [
                    'general' => ['ahg_', 'enable_'],
                    'spectrum' => ['spectrum_'],
                    'media' => ['media_'],
                    'photos' => ['photo_'],
                    'data_protection' => ['dp_'],
                    'iiif' => ['iiif_'],
                    'jobs' => ['jobs_'],
                    'faces' => ['face_'],
                    'fuseki' => ['fuseki_'],
                    'ingest' => ['ingest_'],
                    'encryption' => ['encryption_'],
                    'voice_ai' => ['voice_'],
                    'integrity' => ['integrity_'],
                    'multi_tenant' => ['tenant_'],
                    'metadata' => ['meta_', 'map_'],
                    'accession' => ['accession_'],
                    'portable_export' => ['portable_export_'],
                    'security' => ['security_'],
                    'ai_condition' => ['ai_condition_'],
                    'features' => ['enable_'],
                    'email' => [],
                    'ftp' => ['ftp_'],
                    'compliance' => ['compliance_'],
                  ];
                  $label = $key;
                  foreach (($prefixes[$group] ?? []) as $pfx) {
                    if (str_starts_with($label, $pfx)) {
                      $label = substr($label, strlen($pfx));
                      break;
                    }
                  }
                  $label = ucfirst(str_replace('_', ' ', $label));
                @endphp

                @if($isCheckbox)
                  {{-- Checkbox --}}
                  <div class="mb-3">
                    <div class="form-check form-switch">
                      <input type="hidden" name="settings[{{ $key }}]" value="0">
                      <input class="form-check-input" type="checkbox" role="switch"
                             name="settings[{{ $key }}]" id="setting-{{ $key }}" value="1"
                             {{ in_array($val, ['1', 'true', 'yes']) ? 'checked' : '' }}>
                      <label class="form-check-label" for="setting-{{ $key }}">
                        {{ $label }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span>
                      </label>
                    </div>
                    @if($setting->description)
                      <small class="text-muted d-block ms-4">{{ $setting->description }}</small>
                    @endif
                  </div>

                @elseif($isSelect)
                  {{-- Select dropdown --}}
                  <div class="mb-3">
                    <label for="setting-{{ $key }}" class="form-label fw-semibold">{{ $label }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <select name="settings[{{ $key }}]" id="setting-{{ $key }}" class="form-select">
                      @foreach($selectFields[$key] as $optVal => $optLabel)
                        <option value="{{ $optVal }}" {{ $val === (string) $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                      @endforeach
                    </select>
                    @if($setting->description)
                      <small class="text-muted">{{ $setting->description }}</small>
                    @endif
                  </div>

                @elseif($isColor)
                  {{-- Color picker --}}
                  <div class="mb-3">
                    <label for="setting-{{ $key }}" class="form-label fw-semibold">{{ $label }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <div class="input-group" style="max-width: 300px;">
                      <input type="color" class="form-control form-control-color" id="setting-{{ $key }}-picker"
                             value="{{ $val ?: '#000000' }}"
                             onchange="document.getElementById('setting-{{ $key }}').value = this.value">
                      <input type="text" class="form-control" name="settings[{{ $key }}]" id="setting-{{ $key }}"
                             value="{{ e($val) }}" placeholder="{{ __('#000000') }}" pattern="^#[0-9a-fA-F]{6}$"
                             onchange="document.getElementById('setting-{{ $key }}-picker').value = this.value">
                    </div>
                    @if($setting->description)
                      <small class="text-muted">{{ $setting->description }}</small>
                    @endif
                  </div>

                @elseif($isPassword)
                  {{-- Password field --}}
                  <div class="mb-3">
                    <label for="setting-{{ $key }}" class="form-label fw-semibold">{{ $label }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <div class="input-group" style="max-width: 500px;">
                      <input type="password" class="form-control" name="settings[{{ $key }}]" id="setting-{{ $key }}"
                             value="{{ e($val) }}" autocomplete="off">
                      <button class="btn atom-btn-white" type="button"
                              onclick="var i = document.getElementById('setting-{{ $key }}'); i.type = i.type === 'password' ? 'text' : 'password';">
                        <i class="fas fa-eye"></i>
                      </button>
                    </div>
                    @if($setting->description)
                      <small class="text-muted">{{ $setting->description }}</small>
                    @endif
                  </div>

                @elseif($isTextarea)
                  {{-- Textarea --}}
                  <div class="mb-3">
                    <label for="setting-{{ $key }}" class="form-label fw-semibold">{{ $label }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <textarea name="settings[{{ $key }}]" id="setting-{{ $key }}" class="form-control font-monospace"
                              rows="8">{{ e($val) }}</textarea>
                    @if($setting->description)
                      <small class="text-muted">{{ $setting->description }}</small>
                    @endif
                  </div>

                @elseif($isNumeric)
                  {{-- Number input --}}
                  <div class="mb-3">
                    <label for="setting-{{ $key }}" class="form-label fw-semibold">{{ $label }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <input type="number" class="form-control" name="settings[{{ $key }}]" id="setting-{{ $key }}"
                           value="{{ e($val) }}" style="max-width: 300px;">
                    @if($setting->description)
                      <small class="text-muted">{{ $setting->description }}</small>
                    @endif
                  </div>

                @else
                  {{-- Default text input --}}
                  <div class="mb-3">
                    <label for="setting-{{ $key }}" class="form-label fw-semibold">{{ $label }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <input type="text" class="form-control" name="settings[{{ $key }}]" id="setting-{{ $key }}"
                           value="{{ e($val) }}">
                    @if($setting->description)
                      <small class="text-muted">{{ $setting->description }}</small>
                    @endif
                  </div>
                @endif
              @endforeach
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
            <a href="{{ route('settings.index') }}" class="btn atom-btn-white">Back</a>
          </div>
        </form>
      @endif
    </div>
  </div>
@endsection
