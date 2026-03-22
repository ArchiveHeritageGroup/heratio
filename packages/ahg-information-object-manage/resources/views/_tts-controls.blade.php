@php /**
 * TTS Controls Partial
 *
 * Include with: include_partial('informationobject/ttsControls', ['target' => '#content-area'])
 *
 * Options:
 *   - target: CSS selector for content to read (default: '[data-tts-content]')
 *   - style: 'full' (with speed control), 'compact', 'icon' (default: 'compact')
 *   - position: 'inline', 'floating', 'bar' (default: 'inline')
 */

// Check if TTS is enabled in settings
$ttsEnabled = sfConfig::get('app_tts_enabled', true);
if (!$ttsEnabled) return;

$target = $target ?? '[data-tts-content]';
$style = $style ?? 'compact';
$position = $position ?? 'inline';
$culture = sfContext::getInstance()->getUser()->getCulture();

// Include TTS assets (only once)
$response = sfContext::getInstance()->getResponse();
$response->addStylesheet('/plugins/ahgCorePlugin/web/css/tts.css', 'last');
$response->addJavascript('/plugins/ahgCorePlugin/web/js/tts.js', 'last'); @endphp

@if($position === 'floating')
<!-- Floating TTS Button -->
<div class="tts-floating">
  <button type="button"
          class="tts-btn"
          data-tts-action="toggle"
          data-tts-target="{{ $target }}"
          title="{{ __('Read aloud (Alt+P)') }}"
          aria-label="{{ __('Read page content aloud') }}">
    <i class="bi bi-play-fill"></i>
    <span class="tts-sr-only">{{ __('Read aloud') }}</span>
  </button>
</div>

@elseif($position === 'bar')
<!-- TTS Control Bar -->
<div class="tts-bar" id="tts-bar">
  <div class="d-flex align-items-center gap-3">
    <button type="button" class="tts-btn tts-btn-sm" data-tts-action="toggle" data-tts-target="{{ $target }}">
      <i class="bi bi-play-fill"></i>
    </button>
    <button type="button" class="tts-btn tts-btn-sm" data-tts-action="stop">
      <i class="bi bi-stop-fill"></i>
    </button>
    <div class="tts-speed-control">
      <label>{{ __('Speed') }}:</label>
      <input type="range" min="0.5" max="2" step="0.1" value="1" data-tts-speed>
      <span class="tts-speed-value" data-tts-rate-display>1.0x</span>
    </div>
  </div>
  <div class="tts-status">
    <i class="bi bi-volume-up me-1"></i>
    <span>{{ __('Reading...') }}</span>
  </div>
</div>

@elseif($style === 'full')
<!-- Full TTS Controls -->
<div class="tts-controls mb-3">
  <button type="button"
          class="tts-btn"
          data-tts-action="toggle"
          data-tts-target="{{ $target }}"
          title="{{ __('Play/Pause (Alt+P)') }}">
    <i class="bi bi-play-fill"></i>
    <span>{{ __('Read aloud') }}</span>
  </button>
  <button type="button"
          class="tts-btn"
          data-tts-action="stop"
          title="{{ __('Stop (Alt+S)') }}">
    <i class="bi bi-stop-fill"></i>
  </button>
  <div class="tts-speed-control">
    <label for="tts-speed">{{ __('Speed') }}:</label>
    <input type="range" id="tts-speed" min="0.5" max="2" step="0.1" value="1" data-tts-speed>
    <span class="tts-speed-value" data-tts-rate-display>1.0x</span>
  </div>
</div>

@elseif($style === 'icon')
<!-- Icon-only TTS Button -->
<button type="button"
        class="tts-btn tts-btn-icon tts-inline"
        data-tts-action="toggle"
        data-tts-target="{{ $target }}"
        title="{{ __('Read aloud (Alt+P)') }}"
        aria-label="{{ __('Read content aloud') }}">
  <i class="bi bi-volume-up"></i>
</button>

@else
<!-- Compact TTS Button (default) -->
<button type="button"
        class="tts-btn tts-btn-sm"
        data-tts-action="toggle"
        data-tts-target="{{ $target }}"
        title="{{ __('Read aloud (Alt+P)') }}">
  <i class="bi bi-volume-up"></i>
  <span>{{ __('Listen') }}</span>
</button>
@endif

<script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? 'nonce="'.htmlspecialchars(str_replace('nonce=', '', $n)).'"' : ''; @endphp>
// Configure TTS for current language
window.AHG_TTS_CONFIG = window.AHG_TTS_CONFIG || {};
window.AHG_TTS_CONFIG.lang = '@php echo $culture; @endphp';

@if($position === 'bar')
// Show/hide TTS bar based on playback state
document.addEventListener('tts:start', function() {
  document.getElementById('tts-bar').classList.add('active');
});
document.addEventListener('tts:stop', function() {
  document.getElementById('tts-bar').classList.remove('active');
});
@endif
</script>
