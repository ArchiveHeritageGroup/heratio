{{--
    Heratio media-player component (Phase 1: audio).

    Single source of truth for the .ahg-media-player UI that previously
    lived inline in _digital-object-viewer.blade.php. Including this
    partial renders identical chrome (gradient background, scrubber, time
    display, +/-10s skip, speed, volume, file-info badges, optional
    download anchor) wherever it's needed.

    Required variables:
      $type        - 'audio' (Phase 1; 'video' will be added in Phase 2)
      $playerId    - unique DOM id base, e.g. 'ahg-audio-' . $io->id
      $src         - primary <source src=""> URL
      $mime        - MIME type for the primary source
      $name        - filename for the badge (passed through stripExtensions helper)
      $masterUrl   - canonical master file URL for the download anchor
      $masterMime  - MIME type for the optional fallback <source>
      $byteSize    - integer bytes for the file-size badge (0 / null = hide)

    Optional variables:
      $needsStreaming  - bool; when true and $src !== $masterUrl emits a
                         second <source> for the master so old browsers
                         that can't stream the reference fall back cleanly
      $showDownload    - bool; honour media_show_download (default true)

    JS bindings: scoped by $playerId so multiple players on the same
    page don't cross-talk. Inline <script> uses csp_nonce() so the
    Spatie CSP middleware's nonce-allowlist accepts it.
--}}

@php
    $type ??= 'audio';
    $needsStreaming ??= false;
    $showDownload ??= true;
@endphp

<div class="ahg-media-player rounded p-3" style="background:linear-gradient(135deg,#1a1a2e,#16213e);">
    <audio id="{{ $playerId }}" preload="metadata" style="display:none;">
        <source src="{{ $src }}" type="{{ $mime }}">
        @if($needsStreaming && $src !== $masterUrl)
            <source src="{{ $masterUrl }}" type="{{ $masterMime }}">
        @endif
    </audio>

    {{-- Waveform / progress bar (also the click-to-seek surface;
         WaveSurfer overlay attaches here when media_show_waveform=true) --}}
    <div id="{{ $playerId }}-progress" class="mb-3" style="cursor:pointer;height:60px;background:rgba(255,255,255,0.05);border-radius:6px;position:relative;overflow:hidden;">
        <div id="{{ $playerId }}-fill" style="height:100%;width:0%;background:linear-gradient(90deg,rgba(13,110,253,0.4),rgba(13,110,253,0.15));position:absolute;transition:width 0.1s;"></div>
        <div class="d-flex align-items-center justify-content-center h-100 position-relative">
            <i class="fas fa-music fa-2x text-white" style="opacity:0.15;"></i>
        </div>
    </div>

    {{-- Time display --}}
    <div class="d-flex justify-content-between text-white mb-2" style="font-size:0.8rem;opacity:0.7;">
        <span id="{{ $playerId }}-current">0:00</span>
        <span id="{{ $playerId }}-duration">0:00</span>
    </div>

    {{-- Controls --}}
    <div class="d-flex align-items-center justify-content-center gap-2">
        <button class="btn btn-sm btn-outline-light" id="{{ $playerId }}-back" title="{{ __('Back 10s') }}">
            <i class="fas fa-backward"></i> 10s
        </button>
        <button class="btn btn-lg btn-light rounded-circle" id="{{ $playerId }}-play" title="{{ __('Play/Pause') }}" style="width:50px;height:50px;">
            <i class="fas fa-play" id="{{ $playerId }}-play-icon"></i>
        </button>
        <button class="btn btn-sm btn-outline-light" id="{{ $playerId }}-fwd" title="{{ __('Forward 10s') }}">
            10s <i class="fas fa-forward"></i>
        </button>
        <div class="ms-3 d-flex align-items-center gap-1">
            <span class="text-white small">{{ __('Speed:') }}</span>
            <select id="{{ $playerId }}-speed" class="form-select form-select-sm" style="width:70px;background:rgba(255,255,255,0.1);color:#fff;border-color:rgba(255,255,255,0.2);">
                <option value="0.5">0.5x</option>
                <option value="0.75">0.75x</option>
                <option value="1" selected>1x</option>
                <option value="1.25">1.25x</option>
                <option value="1.5">1.5x</option>
                <option value="2">2x</option>
            </select>
        </div>
        <div class="ms-2 d-flex align-items-center gap-1">
            <i class="fas fa-volume-up text-white" style="opacity:0.7;"></i>
            <input type="range" id="{{ $playerId }}-vol" class="form-range" style="width:80px;" min="0" max="1" step="0.05" value="1">
        </div>
    </div>

    {{-- File info + download --}}
    <div class="mt-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="badge bg-secondary">{{ \AhgCore\Support\GlobalSettings::displayFilename($name) ?? '' }}</span>
            <span class="badge" style="background:rgba(255,255,255,0.1);color:#ccc;">{{ $mime }}</span>
            @if(!empty($byteSize))
                <span class="badge" style="background:rgba(255,255,255,0.1);color:#ccc;">{{ \AhgCore\Services\DigitalObjectService::formatFileSize($byteSize) }}</span>
            @endif
        </div>
        @if($showDownload)
            @auth
                <a href="{{ $masterUrl }}" download class="btn btn-sm btn-outline-light">
                    <i class="fas fa-download me-1"></i>{{ __('Download') }}
                </a>
            @endauth
        @endif
    </div>
</div>

<script nonce="{{ csp_nonce() }}">
(function () {
    var audio   = document.getElementById('{{ $playerId }}');
    var playBtn = document.getElementById('{{ $playerId }}-play');
    var playIcon= document.getElementById('{{ $playerId }}-play-icon');
    var backBtn = document.getElementById('{{ $playerId }}-back');
    var fwdBtn  = document.getElementById('{{ $playerId }}-fwd');
    var speedSel= document.getElementById('{{ $playerId }}-speed');
    var volRange= document.getElementById('{{ $playerId }}-vol');
    var progress= document.getElementById('{{ $playerId }}-progress');
    var fill    = document.getElementById('{{ $playerId }}-fill');
    var curTime = document.getElementById('{{ $playerId }}-current');
    var durTime = document.getElementById('{{ $playerId }}-duration');

    if (!audio) return;

    function fmt(s) { var m = Math.floor(s/60); return m + ':' + String(Math.floor(s%60)).padStart(2,'0'); }

    audio.addEventListener('loadedmetadata', function () { durTime.textContent = fmt(audio.duration); });
    audio.addEventListener('timeupdate', function () {
        curTime.textContent = fmt(audio.currentTime);
        if (audio.duration) fill.style.width = (audio.currentTime / audio.duration * 100) + '%';
    });
    audio.addEventListener('ended', function () { playIcon.className = 'fas fa-play'; });

    playBtn.addEventListener('click', function () {
        if (audio.paused) { audio.play(); playIcon.className = 'fas fa-pause'; }
        else              { audio.pause(); playIcon.className = 'fas fa-play'; }
    });
    backBtn.addEventListener('click', function () { audio.currentTime = Math.max(0, audio.currentTime - 10); });
    fwdBtn.addEventListener('click',  function () { audio.currentTime = Math.min(audio.duration || 0, audio.currentTime + 10); });
    speedSel.addEventListener('change', function () { audio.playbackRate = parseFloat(this.value); });
    volRange.addEventListener('input',  function () { audio.volume = parseFloat(this.value); });
    progress.addEventListener('click', function (e) {
        var rect = this.getBoundingClientRect();
        var pct = (e.clientX - rect.left) / rect.width;
        if (audio.duration) audio.currentTime = pct * audio.duration;
    });
})();
</script>
