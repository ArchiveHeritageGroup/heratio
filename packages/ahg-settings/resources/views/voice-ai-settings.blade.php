{{--
  Voice & AI — voice commands and AI image description settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('voice_ai')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Voice & AI')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-microphone me-2"></i>Voice &amp; AI</h1>
<p class="text-muted">Voice interface and AI assistant settings</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.voice_ai') }}">
    @csrf

    {{-- Voice Commands --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-microphone me-2"></i>Voice Commands</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="voice_enabled"
                     name="settings[voice_enabled]" value="true"
                     {{ ($settings['voice_enabled'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="voice_enabled">
                <strong>{{ __('Enable Voice Commands') }}</strong>
              </label>
            </div>
            <div class="form-text">Allow users to navigate and control the application using voice commands.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="voice_language">{{ __('Voice Language') }}</label>
            <select class="form-select" id="voice_language" name="settings[voice_language]">
              <option value="en-US" {{ ($settings['voice_language'] ?? 'en-US') === 'en-US' ? 'selected' : '' }}>{{ __('English (US)') }}</option>
              <option value="en-GB" {{ ($settings['voice_language'] ?? '') === 'en-GB' ? 'selected' : '' }}>{{ __('English (UK)') }}</option>
              <option value="af-ZA" {{ ($settings['voice_language'] ?? '') === 'af-ZA' ? 'selected' : '' }}>{{ __('Afrikaans') }}</option>
              <option value="zu-ZA" {{ ($settings['voice_language'] ?? '') === 'zu-ZA' ? 'selected' : '' }}>isiZulu</option>
              <option value="xh-ZA" {{ ($settings['voice_language'] ?? '') === 'xh-ZA' ? 'selected' : '' }}>isiXhosa</option>
              <option value="st-ZA" {{ ($settings['voice_language'] ?? '') === 'st-ZA' ? 'selected' : '' }}>{{ __('Sesotho') }}</option>
              <option value="fr-FR" {{ ($settings['voice_language'] ?? '') === 'fr-FR' ? 'selected' : '' }}>{{ __('French') }}</option>
              <option value="pt-PT" {{ ($settings['voice_language'] ?? '') === 'pt-PT' ? 'selected' : '' }}>{{ __('Portuguese') }}</option>
              <option value="es-ES" {{ ($settings['voice_language'] ?? '') === 'es-ES' ? 'selected' : '' }}>{{ __('Spanish') }}</option>
              <option value="de-DE" {{ ($settings['voice_language'] ?? '') === 'de-DE' ? 'selected' : '' }}>{{ __('German') }}</option>
            </select>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <label class="form-label" for="voice_confidence_threshold">Confidence Threshold: <span id="voice_confidence_threshold_val">{{ $settings['voice_confidence_threshold'] ?? '0.4' }}</span></label>
            <input type="range" class="form-range" id="voice_confidence_threshold"
                   name="settings[voice_confidence_threshold]"
                   min="0.3" max="0.95" step="0.05"
                   value="{{ $settings['voice_confidence_threshold'] ?? '0.4' }}"
                   oninput="document.getElementById('voice_confidence_threshold_val').textContent=this.value">
            <div class="form-text">Minimum confidence score for voice recognition (0.3 = lenient, 0.95 = strict).</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="voice_speech_rate">Speech Rate: <span id="voice_speech_rate_val">{{ $settings['voice_speech_rate'] ?? '1.0' }}</span></label>
            <input type="range" class="form-range" id="voice_speech_rate"
                   name="settings[voice_speech_rate]"
                   min="0.5" max="2.0" step="0.1"
                   value="{{ $settings['voice_speech_rate'] ?? '1.0' }}"
                   oninput="document.getElementById('voice_speech_rate_val').textContent=this.value">
            <div class="form-text">Text-to-speech playback rate (0.5 = slow, 2.0 = fast).</div>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="voice_continuous_listening"
                     name="settings[voice_continuous_listening]" value="true"
                     {{ ($settings['voice_continuous_listening'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="voice_continuous_listening">
                <strong>{{ __('Continuous Listening') }}</strong>
              </label>
            </div>
            <div class="form-text">Keep microphone active after each command (no need to re-activate).</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="voice_show_floating_btn"
                     name="settings[voice_show_floating_btn]" value="true"
                     {{ ($settings['voice_show_floating_btn'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="voice_show_floating_btn">
                <strong>{{ __('Show Floating Mic Button') }}</strong>
              </label>
            </div>
            <div class="form-text">Display a floating microphone button on all pages for quick voice activation.</div>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="voice_hover_read_enabled"
                     name="settings[voice_hover_read_enabled]" value="true"
                     {{ ($settings['voice_hover_read_enabled'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="voice_hover_read_enabled">
                <strong>{{ __('Mouseover Read-Aloud') }}</strong>
              </label>
            </div>
            <div class="form-text">Read button and link text aloud when hovering with the mouse (when voice mode is active).</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="voice_hover_read_delay">Hover Read Delay: <span id="voice_hover_read_delay_val">{{ $settings['voice_hover_read_delay'] ?? '400' }}</span>ms</label>
            <input type="range" class="form-range" id="voice_hover_read_delay"
                   name="settings[voice_hover_read_delay]"
                   min="100" max="1000" step="50"
                   value="{{ $settings['voice_hover_read_delay'] ?? '400' }}"
                   oninput="document.getElementById('voice_hover_read_delay_val').textContent=this.value">
            <div class="form-text">Milliseconds to wait before reading (100 = instant, 1000 = slow). Lower values are more responsive.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- AI Image Description --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-brain me-2"></i>AI Image Description</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="voice_llm_provider">{{ __('LLM Provider') }}</label>
            <select class="form-select" id="voice_llm_provider" name="settings[voice_llm_provider]">
              <option value="local" {{ ($settings['voice_llm_provider'] ?? '') === 'local' ? 'selected' : '' }}>{{ __('Local Only') }}</option>
              <option value="cloud" {{ ($settings['voice_llm_provider'] ?? '') === 'cloud' ? 'selected' : '' }}>{{ __('Cloud Only') }}</option>
              <option value="hybrid" {{ ($settings['voice_llm_provider'] ?? 'hybrid') === 'hybrid' ? 'selected' : '' }}>{{ __('Hybrid (Local + Cloud Fallback)') }}</option>
            </select>
            <div class="form-text">Choose where AI image descriptions are processed.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="voice_daily_cloud_limit">{{ __('Daily Cloud Limit') }}</label>
            <input type="number" class="form-control" id="voice_daily_cloud_limit"
                   name="settings[voice_daily_cloud_limit]"
                   value="{{ e($settings['voice_daily_cloud_limit'] ?? '50') }}" min="0" max="10000">
            <div class="form-text">Maximum cloud API calls per day (0 = unlimited).</div>
          </div>
        </div>

        <hr>
        <h6 class="mb-3">{{ __('Local LLM Settings') }}</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="voice_local_llm_url">{{ __('Local LLM URL') }}</label>
            <input type="text" class="form-control" id="voice_local_llm_url"
                   name="settings[voice_local_llm_url]"
                   value="{{ e($settings['voice_local_llm_url'] ?? 'http://localhost:11434') }}"
                   placeholder="{{ __('http://localhost:11434') }}">
            <div class="form-text">Ollama or compatible API endpoint.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="voice_local_llm_model">{{ __('Local LLM Model') }}</label>
            <input type="text" class="form-control" id="voice_local_llm_model"
                   name="settings[voice_local_llm_model]"
                   value="{{ e($settings['voice_local_llm_model'] ?? 'llava:7b') }}"
                   placeholder="{{ __('llava:7b') }}">
            <div class="form-text">Vision-capable model name (e.g. llava:7b, bakllava).</div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="voice_local_llm_timeout">{{ __('Timeout (seconds)') }}</label>
            <input type="number" class="form-control" id="voice_local_llm_timeout"
                   name="settings[voice_local_llm_timeout]"
                   value="{{ e($settings['voice_local_llm_timeout'] ?? '30') }}" min="5" max="300">
            <div class="form-text">Request timeout for local LLM API calls.</div>
          </div>
        </div>

        <hr>
        <h6 class="mb-3">{{ __('Cloud LLM Settings') }}</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="voice_anthropic_api_key">{{ __('Anthropic API Key') }}</label>
            <input type="password" class="form-control" id="voice_anthropic_api_key"
                   name="settings[voice_anthropic_api_key]"
                   value="{{ e($settings['voice_anthropic_api_key'] ?? '') }}"
                   placeholder="{{ __('sk-ant-...') }}">
            <div class="form-text">API key for Claude cloud vision. Stored encrypted.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="voice_cloud_model">{{ __('Cloud Model') }}</label>
            <input type="text" class="form-control" id="voice_cloud_model"
                   name="settings[voice_cloud_model]"
                   value="{{ e($settings['voice_cloud_model'] ?? 'claude-sonnet-4-20250514') }}"
                   placeholder="{{ __('claude-sonnet-4-20250514') }}">
            <div class="form-text">Anthropic model ID for image descriptions.</div>
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch mt-4">
              <input class="form-check-input" type="checkbox" id="voice_audit_ai_calls"
                     name="settings[voice_audit_ai_calls]" value="true"
                     {{ ($settings['voice_audit_ai_calls'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="voice_audit_ai_calls">
                <strong>{{ __('Audit AI Calls') }}</strong>
              </label>
            </div>
            <div class="form-text">Log all AI image description requests to the audit trail.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Save --}}
    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>{{ __('Save') }}
      </button>
    </div>
  </form>
@endsection
