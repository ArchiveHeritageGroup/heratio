{{--
    Heratio media-player component (#106).

    Single source of truth for every audio/video render surface in
    Heratio. The operator's media_player_type setting on
    /admin/ahgSettings/media drives the layout this component emits:

      heratio          - rich Heratio-branded UI (default).
      heratio-minimal  - native controls + minimal badge row + progress
                          readout. Also used as the JS fallthrough when
                          rich init throws.
      plyr             - bare native; master.blade.php wraps with Plyr.
      videojs          - bare native; master.blade.php wraps with Video.js.
      native           - bare native; no wrapper.

    For plyr/videojs/native modes the wrapper class is intentionally NOT
    .ahg-media-player so master.blade.php's enhance() filter does not skip
    the element. For heratio + heratio-minimal the .ahg-media-player class
    protects the custom UI from being wrapped by Plyr/Video.js even if
    those bundles end up loaded.

    Required variables:
      $type        - 'audio' | 'video'
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
      $poster          - video-only poster URL (default null)
      $tracks          - video-only array of caption/subtitle tracks. Each
                         entry is an associative array with keys src, kind
                         ('subtitles' | 'captions' | 'descriptions' |
                         'chapters' | 'metadata'), srclang (BCP-47), label
                         (display name), default (bool). Empty array = no
                         tracks. (default [])
--}}

@php
    $type           ??= 'audio';
    $needsStreaming ??= false;
    $showDownload   ??= true;
    $poster         ??= null;
    $tracks         ??= [];

    $__playerType = \App\Support\MediaSettings::playerType();
    $__media      = \App\Support\MediaSettings::payload();
    $__isHeratio  = $__playerType === 'heratio';
    $__isMinimal  = $__playerType === 'heratio-minimal';
    $__isBare     = in_array($__playerType, ['plyr', 'videojs', 'native'], true);
    $__isVideo    = $type === 'video';
@endphp

@if($__isBare)
    {{-- ── Bare native: master.blade.php's enhance() may wrap with Plyr or Video.js. ── --}}
    <div class="ahg-media-bare">
        @if($__isVideo)
            <video id="{{ $playerId }}" class="w-100" style="max-height:500px;background:#000;" preload="metadata"
                   @if($__media['show_controls']) controls @endif
                   @if($__media['autoplay']) autoplay @endif
                   @if($__media['loop']) loop @endif
                   @if($poster) poster="{{ $poster }}" @endif>
                <source src="{{ $src }}" type="{{ $mime }}">
                @if($needsStreaming && $src !== $masterUrl)
                    <source src="{{ $masterUrl }}" type="{{ $masterMime }}">
                @endif
                @foreach($tracks as $__t)
                    <track src="{{ $__t['src'] }}"
                           kind="{{ $__t['kind'] ?? 'subtitles' }}"
                           srclang="{{ $__t['srclang'] ?? 'en' }}"
                           label="{{ $__t['label'] ?? '' }}"
                           @if(!empty($__t['default'])) default @endif>
                @endforeach
                {{ __('Your browser does not support video playback.') }}
            </video>
        @else
            <audio id="{{ $playerId }}" class="w-100" preload="metadata"
                   @if($__media['show_controls']) controls @endif
                   @if($__media['autoplay']) autoplay @endif
                   @if($__media['loop']) loop @endif>
                <source src="{{ $src }}" type="{{ $mime }}">
                @if($needsStreaming && $src !== $masterUrl)
                    <source src="{{ $masterUrl }}" type="{{ $masterMime }}">
                @endif
            </audio>
        @endif
        <div class="mt-2 d-flex justify-content-between align-items-center">
            <div>
                <span class="badge bg-secondary">{{ \AhgCore\Support\GlobalSettings::displayFilename($name) ?? '' }}</span>
                <span class="badge bg-light text-dark">{{ $mime }}</span>
                @if(!empty($byteSize))
                    <span class="badge bg-light text-dark">{{ \AhgCore\Services\DigitalObjectService::formatFileSize($byteSize) }}</span>
                @endif
            </div>
            @if($showDownload && ($__media['show_download'] ?? false))
                @auth
                    <a href="{{ $masterUrl }}" download class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download me-1"></i>{{ __('Download') }}
                    </a>
                @endauth
            @endif
        </div>
    </div>

@elseif($__isMinimal)
    {{-- ── Heratio-minimal: native controls + tiny progress readout. ──
         Also rendered automatically when rich init throws (see fallthrough
         block below for heratio mode). The .ahg-media-player class keeps
         master.blade.php's enhance() from wrapping these. --}}
    <div class="ahg-media-player ahg-media-player-minimal rounded p-2"
         id="{{ $playerId }}-wrapper"
         style="background:#f5f5f5;border:1px solid #ddd;">
        @if($__isVideo)
            <video id="{{ $playerId }}" class="w-100 mb-2"
                   style="max-height:500px;background:#000;" preload="metadata" controls
                   @if($__media['autoplay']) autoplay @endif
                   @if($__media['loop']) loop @endif
                   @if($poster) poster="{{ $poster }}" @endif>
                <source src="{{ $src }}" type="{{ $mime }}">
                @if($needsStreaming && $src !== $masterUrl)
                    <source src="{{ $masterUrl }}" type="{{ $masterMime }}">
                @endif
                @foreach($tracks as $__t)
                    <track src="{{ $__t['src'] }}"
                           kind="{{ $__t['kind'] ?? 'subtitles' }}"
                           srclang="{{ $__t['srclang'] ?? 'en' }}"
                           label="{{ $__t['label'] ?? '' }}"
                           @if(!empty($__t['default'])) default @endif>
                @endforeach
            </video>
        @else
            <audio id="{{ $playerId }}" class="w-100 mb-2" preload="metadata" controls
                   @if($__media['autoplay']) autoplay @endif
                   @if($__media['loop']) loop @endif>
                <source src="{{ $src }}" type="{{ $mime }}">
                @if($needsStreaming && $src !== $masterUrl)
                    <source src="{{ $masterUrl }}" type="{{ $masterMime }}">
                @endif
            </audio>
        @endif
        <div class="d-flex justify-content-between align-items-center small">
            <div>
                <span class="badge bg-secondary">{{ \AhgCore\Support\GlobalSettings::displayFilename($name) ?? '' }}</span>
                <span class="badge bg-light text-dark">{{ $mime }}</span>
                <span id="{{ $playerId }}-current" class="text-muted ms-1">0:00</span>
            </div>
            @if($showDownload && ($__media['show_download'] ?? false))
                @auth
                    <a href="{{ $masterUrl }}" download class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download me-1"></i>{{ __('Download') }}
                    </a>
                @endauth
            @endif
        </div>
    </div>
    <script nonce="{{ csp_nonce() }}">
    (function () {
        var media = document.getElementById('{{ $playerId }}');
        var curEl = document.getElementById('{{ $playerId }}-current');
        if (!media || !curEl) return;
        function fmt(s) { var m = Math.floor(s/60); return m + ':' + String(Math.floor(s%60)).padStart(2,'0'); }
        media.addEventListener('timeupdate', function () { curEl.textContent = fmt(media.currentTime); });
    })();
    </script>

@else
    {{-- ── Heratio rich UI (default). ──
         Custom chrome: gradient background, scrubber, time, ±10s skip,
         speed, volume, fullscreen + PiP (video only), file-info badges,
         optional download anchor. Inline init wraps in try/catch and
         falls through to minimal markup if anything throws. --}}
    <div class="ahg-media-player rounded p-3"
         id="{{ $playerId }}-wrapper"
         data-player-type="{{ $type }}"
         style="background:linear-gradient(135deg,#1a1a2e,#16213e);">

        @if($__isVideo)
            <video id="{{ $playerId }}" class="w-100 mb-2 rounded"
                   style="max-height:500px;background:#000;" preload="metadata"
                   data-no-ahg-media
                   @if($poster) poster="{{ $poster }}" @endif>
                <source src="{{ $src }}" type="{{ $mime }}">
                @if($needsStreaming && $src !== $masterUrl)
                    <source src="{{ $masterUrl }}" type="{{ $masterMime }}">
                @endif
                @foreach($tracks as $__t)
                    <track src="{{ $__t['src'] }}"
                           kind="{{ $__t['kind'] ?? 'subtitles' }}"
                           srclang="{{ $__t['srclang'] ?? 'en' }}"
                           label="{{ $__t['label'] ?? '' }}"
                           @if(!empty($__t['default'])) default @endif>
                @endforeach
                {{ __('Your browser does not support video playback.') }}
            </video>
        @else
            <audio id="{{ $playerId }}" preload="metadata" style="display:none;" data-no-ahg-media>
                <source src="{{ $src }}" type="{{ $mime }}">
                @if($needsStreaming && $src !== $masterUrl)
                    <source src="{{ $masterUrl }}" type="{{ $masterMime }}">
                @endif
            </audio>
        @endif

        {{-- Scrubber + click-to-seek surface. WaveSurfer overlay attaches
             here when media_show_waveform=true (audio only). --}}
        <div id="{{ $playerId }}-progress" class="mb-3"
             style="cursor:pointer;height:{{ $__isVideo ? '14px' : '60px' }};background:rgba(255,255,255,0.05);border-radius:6px;position:relative;overflow:hidden;">
            <div id="{{ $playerId }}-fill"
                 style="height:100%;width:0%;background:linear-gradient(90deg,rgba(13,110,253,0.4),rgba(13,110,253,0.15));position:absolute;transition:width 0.1s;"></div>
            @if(!$__isVideo)
                <div class="d-flex align-items-center justify-content-center h-100 position-relative">
                    <i class="fas fa-music fa-2x text-white" style="opacity:0.15;"></i>
                </div>
            @endif
        </div>

        {{-- Time display --}}
        <div class="d-flex justify-content-between text-white mb-2" style="font-size:0.8rem;opacity:0.7;">
            <span id="{{ $playerId }}-current">0:00</span>
            <span id="{{ $playerId }}-duration">0:00</span>
        </div>

        {{-- Controls row (hidden when media_show_controls=false). --}}
        @if($__media['show_controls'])
        <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-outline-light" id="{{ $playerId }}-back" title="{{ __('Back 10s') }}">
                <i class="fas fa-backward"></i> 10s
            </button>
            <button type="button" class="btn btn-lg btn-light rounded-circle" id="{{ $playerId }}-play" title="{{ __('Play/Pause') }}" style="width:50px;height:50px;">
                <i class="fas fa-play" id="{{ $playerId }}-play-icon"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-light" id="{{ $playerId }}-fwd" title="{{ __('Forward 10s') }}">
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
            @if($__isVideo)
                <button type="button" class="btn btn-sm btn-outline-light ms-2" id="{{ $playerId }}-pip" title="{{ __('Picture-in-Picture') }}">
                    <i class="fas fa-clone"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-light" id="{{ $playerId }}-fs" title="{{ __('Fullscreen') }}">
                    <i class="fas fa-expand"></i>
                </button>
            @endif
        </div>
        @endif

        {{-- File info + download --}}
        <div class="mt-3 d-flex justify-content-between align-items-center">
            <div>
                <span class="badge bg-secondary">{{ \AhgCore\Support\GlobalSettings::displayFilename($name) ?? '' }}</span>
                <span class="badge" style="background:rgba(255,255,255,0.1);color:#ccc;">{{ $mime }}</span>
                @if(!empty($byteSize))
                    <span class="badge" style="background:rgba(255,255,255,0.1);color:#ccc;">{{ \AhgCore\Services\DigitalObjectService::formatFileSize($byteSize) }}</span>
                @endif
            </div>
            @if($showDownload && ($__media['show_download'] ?? false))
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
        try {
            var media   = document.getElementById('{{ $playerId }}');
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
            var pipBtn  = document.getElementById('{{ $playerId }}-pip');
            var fsBtn   = document.getElementById('{{ $playerId }}-fs');
            var wrapper = document.getElementById('{{ $playerId }}-wrapper');

            if (!media) return;

            // #106 phase 5: apply operator settings (default_volume / loop /
            // autoplay) from window.AHG_MEDIA. media_show_controls is
            // intentionally NOT applied in rich mode — we provide our own
            // chrome and the @if($__media['show_controls']) guard around
            // the controls row already hides our custom controls when the
            // setting is off; setting el.controls=true would re-enable the
            // native overlay on top of the Heratio chrome.
            var __cfg = window.AHG_MEDIA || {};
            var __vol = typeof __cfg.default_volume === 'number' ? __cfg.default_volume : 1.0;
            try { media.volume = __vol; } catch (e) { /* iOS Safari blocks; ignore */ }
            if (volRange) volRange.value = String(__vol);
            if (__cfg.loop) media.loop = true;
            if (__cfg.autoplay) {
                try { var __p = media.play(); if (__p && typeof __p.catch === 'function') __p.catch(function(){}); } catch (e) {}
            }

            function fmt(s) { var m = Math.floor(s/60); return m + ':' + String(Math.floor(s%60)).padStart(2,'0'); }

            media.addEventListener('loadedmetadata', function () { if (durTime) durTime.textContent = fmt(media.duration); });
            media.addEventListener('timeupdate', function () {
                if (curTime) curTime.textContent = fmt(media.currentTime);
                if (media.duration && fill) fill.style.width = (media.currentTime / media.duration * 100) + '%';
            });
            media.addEventListener('ended', function () { if (playIcon) playIcon.className = 'fas fa-play'; });
            media.addEventListener('play',  function () { if (playIcon) playIcon.className = 'fas fa-pause'; });
            media.addEventListener('pause', function () { if (playIcon) playIcon.className = 'fas fa-play'; });

            if (playBtn) playBtn.addEventListener('click', function () {
                if (media.paused) media.play(); else media.pause();
            });
            if (backBtn) backBtn.addEventListener('click', function () { media.currentTime = Math.max(0, media.currentTime - 10); });
            if (fwdBtn)  fwdBtn.addEventListener('click',  function () { media.currentTime = Math.min(media.duration || 0, media.currentTime + 10); });
            if (speedSel) speedSel.addEventListener('change', function () { media.playbackRate = parseFloat(this.value); });
            if (volRange) volRange.addEventListener('input',  function () { media.volume = parseFloat(this.value); });
            if (progress) progress.addEventListener('click', function (e) {
                var rect = this.getBoundingClientRect();
                var pct = (e.clientX - rect.left) / rect.width;
                if (media.duration) media.currentTime = pct * media.duration;
            });
            if (pipBtn && typeof media.requestPictureInPicture === 'function') {
                pipBtn.addEventListener('click', function () {
                    try {
                        if (document.pictureInPictureElement) {
                            document.exitPictureInPicture();
                        } else {
                            media.requestPictureInPicture();
                        }
                    } catch (e) { /* PiP unavailable; ignore */ }
                });
            } else if (pipBtn) {
                pipBtn.style.display = 'none';
            }
            if (fsBtn) {
                fsBtn.addEventListener('click', function () {
                    try {
                        if (document.fullscreenElement) {
                            document.exitFullscreen();
                        } else if (typeof media.requestFullscreen === 'function') {
                            media.requestFullscreen();
                        } else if (typeof wrapper.requestFullscreen === 'function') {
                            wrapper.requestFullscreen();
                        }
                    } catch (e) { /* fullscreen unavailable; ignore */ }
                });
            }
        } catch (err) {
            // #106 Phase 3: rich init failed — swap the wrapper to the
            // Heratio-minimal layout so the page still plays media.
            if (window.console && console.warn) {
                console.warn('Heratio media player ({{ $playerId }}) init failed; falling back to minimal:', err);
            }
            try {
                var w = document.getElementById('{{ $playerId }}-wrapper');
                var src = '{{ $src }}';
                var mime = '{{ $mime }}';
                if (w) {
                    w.className = 'ahg-media-player ahg-media-player-minimal rounded p-2';
                    w.style.cssText = 'background:#f5f5f5;border:1px solid #ddd;';
                    w.innerHTML = ({!! $__isVideo ? 'true' : 'false' !!})
                        ? '<video class="w-100 mb-2" style="max-height:500px;background:#000;" controls preload="metadata"><source src="' + src + '" type="' + mime + '"></video>'
                        : '<audio class="w-100 mb-2" controls preload="metadata"><source src="' + src + '" type="' + mime + '"></audio>';
                }
            } catch (_) { /* nothing more to do */ }
        }
    })();
    </script>
@endif
