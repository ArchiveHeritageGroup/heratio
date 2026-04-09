@extends('theme::layouts.2col')
@section('title', 'Text-to-Speech Settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('content')
<h2><i class="fas fa-volume-up me-2"></i>Text-to-Speech Settings</h2>
    <p class="text-muted">Configure the read-aloud accessibility feature for record detail pages.</p>

    <form method="post" action="{{ route('settings.tts') }}">
      @csrf
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-cog me-2"></i>General</div>
        <div class="card-body">
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="tts_enabled" name="tts[all][enabled]" value="1" {{ ($settings['all']['enabled'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="tts_enabled"><strong>Enable Text-to-Speech</strong> <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
            <div class="form-text">Show the read-aloud button on record detail pages.</div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="tts_rate">Speech Rate: <span id="tts_rate_val">{{ $settings['all']['default_rate'] ?? '1.0' }}</span> <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="range" class="form-range" id="tts_rate" name="tts[all][default_rate]" min="0.5" max="2.0" step="0.1" value="{{ $settings['all']['default_rate'] ?? '1.0' }}" oninput="document.getElementById('tts_rate_val').textContent=this.value">
            <div class="form-text">Playback speed (0.5 = slow, 2.0 = fast).</div>
          </div>

          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="tts_labels" name="tts[all][read_labels]" value="1" {{ ($settings['all']['read_labels'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="tts_labels">Read field labels <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
            <div class="form-text">Include field labels (e.g. "Scope and content:") when reading aloud.</div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-sliders-h me-2"></i>Voice Settings</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Default Voice <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="tts[all][default_voice]" class="form-select">
              <option value="">Browser default</option>
              <option value="en-US" {{ ($settings['all']['default_voice'] ?? '') === 'en-US' ? 'selected' : '' }}>English (US)</option>
              <option value="en-GB" {{ ($settings['all']['default_voice'] ?? '') === 'en-GB' ? 'selected' : '' }}>English (UK)</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="tts_pitch">Pitch: <span id="tts_pitch_val">{{ $settings['all']['default_pitch'] ?? '1.0' }}</span> <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="range" class="form-range" id="tts_pitch" name="tts[all][default_pitch]" min="0.5" max="2.0" step="0.1" value="{{ $settings['all']['default_pitch'] ?? '1.0' }}" oninput="document.getElementById('tts_pitch_val').textContent=this.value">
          </div>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
@endsection
