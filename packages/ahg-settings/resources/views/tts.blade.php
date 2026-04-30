{{--
  Text-to-Speech — read-aloud accessibility settings
  Cloned from AtoM ahgSettingsPlugin ttsSuccess.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Text-to-Speech Settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('content')
<h2><i class="fas fa-volume-up me-2"></i>Text-to-Speech Settings</h2>
<p class="text-muted">Configure the read-aloud accessibility feature for record detail pages.</p>

@if(session('notice') || session('success'))
  <div class="alert alert-success alert-dismissible fade show">
    {{ session('notice') ?? session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<form method="post" action="{{ route('settings.tts') }}">
  @csrf

  {{-- General --}}
  <div class="card mb-4">
    <div class="card-header"><i class="fas fa-cog me-2"></i>General</div>
    <div class="card-body">
      <div class="mb-3">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="tts_enabled"
                 name="tts[all][enabled]" value="1"
                 {{ ($settings['all']['enabled'] ?? '1') === '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="tts_enabled"><strong>Enable Text-to-Speech</strong></label>
        </div>
        <div class="form-text">Show the read-aloud button on record detail pages.</div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="tts_rate">Speech Rate: <span id="tts_rate_val">{{ $settings['all']['default_rate'] ?? '1.0' }}</span></label>
        <input type="range" class="form-range" id="tts_rate"
               name="tts[all][default_rate]"
               min="0.5" max="2.0" step="0.1"
               value="{{ $settings['all']['default_rate'] ?? '1.0' }}"
               oninput="document.getElementById('tts_rate_val').textContent=this.value">
        <div class="form-text">Playback speed (0.5 = slow, 2.0 = fast).</div>
      </div>

      <div class="mb-3">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="tts_labels"
                 name="tts[all][read_labels]" value="1"
                 {{ ($settings['all']['read_labels'] ?? '1') === '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="tts_labels">{{ __('Read field labels') }}</label>
        </div>
        <div class="form-text">Include field labels (e.g. "Scope and content:") when reading aloud.</div>
      </div>

      <div class="mb-3">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="tts_shortcuts"
                 name="tts[all][keyboard_shortcuts]" value="1"
                 {{ ($settings['all']['keyboard_shortcuts'] ?? '1') === '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="tts_shortcuts">{{ __('Keyboard shortcuts') }}</label>
        </div>
        <div class="form-text">Enable keyboard shortcuts for play/pause/stop (Alt+P, Alt+S).</div>
      </div>
    </div>
  </div>

  {{-- Fields to Read per Sector --}}
  <div class="card mb-4">
    <div class="card-header"><i class="fas fa-list-check me-2"></i>Fields to Read per Sector</div>
    <div class="card-body">
      <p class="text-muted mb-3">Select which metadata fields the TTS engine will read for each GLAM/DAM sector.</p>

      <ul class="nav nav-tabs" role="tablist">
        @foreach (['archive', 'library', 'museum', 'gallery', 'dam'] as $idx => $sector)
          <li class="nav-item">
            <a class="nav-link {{ $idx === 0 ? 'active' : '' }}"
               data-bs-toggle="tab" href="#sector_{{ $sector }}" role="tab">
              {{ ucfirst($sector) }}
            </a>
          </li>
        @endforeach
      </ul>

      <div class="tab-content p-3 border border-top-0 rounded-bottom">
        @foreach (['archive', 'library', 'museum', 'gallery', 'dam'] as $idx => $sector)
          @php
            $currentFields = [];
            if (!empty($settings[$sector]['fields_to_read'])) {
                $decoded = json_decode($settings[$sector]['fields_to_read'], true);
                if (is_array($decoded)) { $currentFields = $decoded; }
            }
          @endphp
          <div class="tab-pane fade {{ $idx === 0 ? 'show active' : '' }}" id="sector_{{ $sector }}">
            <div class="row">
              @foreach ($availableFieldsList ?? ['title','scope_and_content','archival_history','acquisition','arrangement','access_conditions','reproduction_conditions','physical_characteristics','finding_aids','location_of_originals','location_of_copies','related_units_of_description','extent_and_medium'] as $field)
                <div class="col-md-4 mb-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                           id="tts_{{ $sector }}_{{ $field }}"
                           name="tts[{{ $sector }}][fields_to_read][]"
                           value="{{ $field }}"
                           {{ in_array($field, $currentFields) ? 'checked' : '' }}>
                    <label class="form-check-label" for="tts_{{ $sector }}_{{ $field }}">{{ $field }}</label>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Voice Settings (Heratio extra) --}}
  <div class="card mb-4">
    <div class="card-header"><i class="fas fa-sliders-h me-2"></i>Voice Settings</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">{{ __('Default Voice') }}</label>
        <select name="tts[all][default_voice]" class="form-select">
          <option value="">{{ __('Browser default') }}</option>
          <option value="en-US" {{ ($settings['all']['default_voice'] ?? '') === 'en-US' ? 'selected' : '' }}>{{ __('English (US)') }}</option>
          <option value="en-GB" {{ ($settings['all']['default_voice'] ?? '') === 'en-GB' ? 'selected' : '' }}>{{ __('English (UK)') }}</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="tts_pitch">Pitch: <span id="tts_pitch_val">{{ $settings['all']['default_pitch'] ?? '1.0' }}</span></label>
        <input type="range" class="form-range" id="tts_pitch"
               name="tts[all][default_pitch]"
               min="0.5" max="2.0" step="0.1"
               value="{{ $settings['all']['default_pitch'] ?? '1.0' }}"
               oninput="document.getElementById('tts_pitch_val').textContent=this.value">
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
    <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
@endsection
