@php
/**
 * TTS Controls Partial (Web Speech API)
 *
 * Include with: @include('ahg-io-manage::_tts-controls', ['target' => '#content-area'])
 *
 * Options:
 *   - target: CSS selector for content to read (default: '[data-tts-content]')
 *   - style: 'full' (with speed control), 'compact', 'icon' (default: 'full')
 *   - position: 'inline', 'floating', 'bar' (default: 'inline')
 */

$ttsEnabled = \AhgCore\Services\SettingHelper::get('tts_enabled', '1');
if (!$ttsEnabled) return;

$target   = $target ?? '[data-tts-content]';
$style    = $style ?? 'full';
$position = $position ?? 'inline';
$culture  = app()->getLocale();
@endphp

@if($position === 'floating')
{{-- Floating TTS Button --}}
<div class="tts-floating" style="position:fixed;bottom:2rem;right:2rem;z-index:1050;">
  <button type="button"
          class="btn atom-btn-white shadow"
          id="tts-toggle-btn"
          data-tts-action="toggle"
          data-tts-target="{{ $target }}"
          title="{{ __('Read aloud (Alt+P)') }}"
          aria-label="{{ __('Read page content aloud') }}">
    <i class="fas fa-play" id="tts-toggle-icon"></i>
    <span class="visually-hidden">{{ __('Read aloud') }}</span>
  </button>
</div>

@elseif($position === 'bar')
{{-- TTS Control Bar --}}
<div class="tts-bar d-none border rounded p-2 mb-3" id="tts-bar" style="background:var(--ahg-primary,#0d6efd);color:#fff;">
  <div class="d-flex align-items-center gap-3">
    <button type="button" class="btn btn-sm btn-light" id="tts-toggle-btn" data-tts-action="toggle" data-tts-target="{{ $target }}">
      <i class="fas fa-play" id="tts-toggle-icon"></i>
    </button>
    <button type="button" class="btn btn-sm btn-light" data-tts-action="stop">
      <i class="fas fa-stop"></i>
    </button>
    <div class="d-flex align-items-center gap-1">
      <label class="mb-0 small">{{ __('Speed') }}:</label>
      <input type="range" min="0.5" max="2" step="0.1" value="1" data-tts-speed class="form-range" style="width:80px;">
      <span class="small" data-tts-rate-display>1.0x</span>
    </div>
  </div>
  <div class="tts-status d-none">
    <i class="fas fa-volume-up me-1"></i>
    <span>{{ __('Reading...') }}</span>
  </div>
</div>

@elseif($style === 'full')
{{-- Full TTS Controls --}}
<div class="tts-controls d-flex flex-wrap align-items-center gap-2 mb-3 p-2 border rounded" style="background:var(--bs-light,#f8f9fa);">
  <button type="button"
          class="btn btn-sm atom-btn-white"
          id="tts-toggle-btn"
          data-tts-action="toggle"
          data-tts-target="{{ $target }}"
          title="{{ __('Play/Pause (Alt+P)') }}">
    <i class="fas fa-play" id="tts-toggle-icon"></i>
    <span>{{ __('Read aloud') }}</span>
  </button>
  <button type="button"
          class="btn btn-sm atom-btn-white"
          data-tts-action="stop"
          title="{{ __('Stop (Alt+S)') }}">
    <i class="fas fa-stop"></i>
    <span>{{ __('Stop') }}</span>
  </button>
  <div class="d-flex align-items-center gap-1">
    <label for="tts-speed" class="mb-0 small text-muted">{{ __('Speed') }}:</label>
    <input type="range" id="tts-speed" min="0.5" max="2" step="0.1" value="1" data-tts-speed class="form-range" style="width:80px;">
    <span class="small text-muted" data-tts-rate-display>1.0x</span>
  </div>
</div>

@elseif($style === 'icon')
{{-- Icon-only TTS Button --}}
<button type="button"
        class="btn btn-sm atom-btn-white"
        id="tts-toggle-btn"
        data-tts-action="toggle"
        data-tts-target="{{ $target }}"
        title="{{ __('Read aloud (Alt+P)') }}"
        aria-label="{{ __('Read content aloud') }}">
  <i class="fas fa-volume-up" id="tts-toggle-icon"></i>
</button>

@else
{{-- Compact TTS Button (default) --}}
<button type="button"
        class="btn btn-sm atom-btn-white"
        id="tts-toggle-btn"
        data-tts-action="toggle"
        data-tts-target="{{ $target }}"
        title="{{ __('Read aloud (Alt+P)') }}">
  <i class="fas fa-volume-up" id="tts-toggle-icon"></i>
  <span>{{ __('Listen') }}</span>
</button>
@endif

@once
@push('js')
<script>
(function() {
  'use strict';

  if (!('speechSynthesis' in window)) {
    // Hide TTS controls if not supported
    document.querySelectorAll('[data-tts-action]').forEach(function(el) {
      el.closest('.tts-controls, .tts-floating, .tts-bar')
        ? el.closest('.tts-controls, .tts-floating, .tts-bar').style.display = 'none'
        : el.style.display = 'none';
    });
    return;
  }

  var synth = window.speechSynthesis;
  var currentUtterance = null;
  var isPaused = false;
  var rate = 1.0;
  var lang = '{{ $culture }}';

  function getTextContent(selector) {
    var el = document.querySelector(selector);
    if (!el) {
      // Fallback: try the main content section
      el = document.querySelector('#content, [role="main"], main');
    }
    return el ? el.innerText || el.textContent : '';
  }

  function updateIcon(state) {
    var icon = document.getElementById('tts-toggle-icon');
    if (!icon) return;
    icon.className = state === 'playing' ? 'fas fa-pause' : 'fas fa-play';
    // Also update the button text if present
    var btn = document.getElementById('tts-toggle-btn');
    if (btn) {
      var span = btn.querySelector('span');
      if (span && !span.classList.contains('visually-hidden')) {
        span.textContent = state === 'playing' ? '{{ __("Pause") }}' : '{{ __("Read aloud") }}';
      }
    }
  }

  function stopSpeech() {
    synth.cancel();
    currentUtterance = null;
    isPaused = false;
    updateIcon('stopped');
    var bar = document.getElementById('tts-bar');
    if (bar) bar.classList.add('d-none');
    var status = document.querySelector('.tts-status');
    if (status) status.classList.add('d-none');
  }

  function toggleSpeech(target) {
    if (synth.speaking && !isPaused) {
      // Pause
      synth.pause();
      isPaused = true;
      updateIcon('paused');
      return;
    }

    if (isPaused) {
      // Resume
      synth.resume();
      isPaused = false;
      updateIcon('playing');
      return;
    }

    // Start new speech
    var text = getTextContent(target);
    if (!text.trim()) return;

    // Split into chunks (speechSynthesis has limits on text length)
    var chunks = [];
    var sentences = text.match(/[^.!?\n]+[.!?\n]*/g) || [text];
    var chunk = '';
    for (var i = 0; i < sentences.length; i++) {
      if ((chunk + sentences[i]).length > 200) {
        if (chunk) chunks.push(chunk);
        chunk = sentences[i];
      } else {
        chunk += sentences[i];
      }
    }
    if (chunk) chunks.push(chunk);

    var chunkIndex = 0;

    function speakNext() {
      if (chunkIndex >= chunks.length) {
        stopSpeech();
        return;
      }
      var utterance = new SpeechSynthesisUtterance(chunks[chunkIndex]);
      utterance.lang = lang;
      utterance.rate = rate;
      utterance.onend = function() {
        chunkIndex++;
        speakNext();
      };
      utterance.onerror = function() {
        stopSpeech();
      };
      currentUtterance = utterance;
      synth.speak(utterance);
    }

    speakNext();
    updateIcon('playing');

    var bar = document.getElementById('tts-bar');
    if (bar) bar.classList.remove('d-none');
    var status = document.querySelector('.tts-status');
    if (status) status.classList.remove('d-none');
  }

  // Bind toggle buttons
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-tts-action]');
    if (!btn) return;
    var action = btn.getAttribute('data-tts-action');
    if (action === 'toggle') {
      var target = btn.getAttribute('data-tts-target') || '[data-tts-content]';
      toggleSpeech(target);
    } else if (action === 'stop') {
      stopSpeech();
    }
  });

  // Bind speed sliders
  document.addEventListener('input', function(e) {
    if (!e.target.hasAttribute('data-tts-speed')) return;
    rate = parseFloat(e.target.value);
    var display = document.querySelector('[data-tts-rate-display]');
    if (display) display.textContent = rate.toFixed(1) + 'x';
    // If currently speaking, update the rate for the next chunk
  });

  // Keyboard shortcuts
  document.addEventListener('keydown', function(e) {
    if (e.altKey && e.key === 'p') {
      e.preventDefault();
      var btn = document.querySelector('[data-tts-action="toggle"]');
      if (btn) btn.click();
    }
    if (e.altKey && e.key === 's') {
      e.preventDefault();
      stopSpeech();
    }
  });

  // Stop speech on page unload
  window.addEventListener('beforeunload', function() {
    synth.cancel();
  });
})();
</script>
@endpush
@endonce
