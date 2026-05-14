@php
  // spatie/laravel-csp ships csp_nonce(); on forks where the package isn't
  // installed (e.g. legacy atom v1.0.5) we still want the layout to render
  // — fall back to an empty string and skip nonce-tagging.
  $cspNonce = function_exists('csp_nonce') ? csp_nonce() : '';
@endphp
<!DOCTYPE html>
<html lang="{{ $themeData['culture'] ?? 'en' }}" dir="ltr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Document title. Some legacy views set @section('title') to HTML
         intended for the visible page header (which should live in
         @section('title-block') on the 1col layout). Strip tags here so
         leaked HTML never reaches the browser tab. --}}
    @php
      $__rawTitle = trim(\Illuminate\Support\Facades\View::yieldContent('title') ?: '');
      $__siteTitle = $themeData['siteTitle'] ?? 'Heratio';
      $__docTitle = $__rawTitle !== '' ? trim(preg_replace('/\s+/', ' ', strip_tags($__rawTitle))) : $__siteTitle;
    @endphp
    <title>{{ $__docTitle }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- CSS-in-JS libraries (Material-UI / JSS, used by Mirador 3) discover the
         per-request CSP nonce from this meta tag and tag their dynamically
         injected <style> blocks with it. Without this, browsers ignore
         'unsafe-inline' (because a nonce is present in style-src) and the
         library renders unstyled. --}}
    <meta property="csp-nonce" content="{{ $cspNonce }}">

    {{-- Google Tag Manager (script) --}}
    @include('theme::partials.tag-manager', ['slot' => 'script'])

    {{-- Google Analytics --}}
    @include('theme::partials.google-analytics')

    {{-- Favicon: prefer ahg_favicon_path setting; fall back to ahg_logo_path; final fallback /favicon.ico --}}
    @php
      $__favicon = trim((string) \AhgCore\Services\AhgSettingsService::get('ahg_favicon_path', ''));
      if ($__favicon === '') {
          $__favicon = trim((string) \AhgCore\Services\AhgSettingsService::get('ahg_logo_path', ''));
      }
      $__favicon = preg_replace('#^public/#', '', $__favicon);
      $__faviconType = match (strtolower(pathinfo($__favicon, PATHINFO_EXTENSION) ?: 'ico')) {
          'svg'  => 'image/svg+xml',
          'png'  => 'image/png',
          'gif'  => 'image/gif',
          'jpg', 'jpeg' => 'image/jpeg',
          default => 'image/x-icon',
      };
    @endphp
    @if($__favicon !== '' && (str_starts_with($__favicon, 'http') || file_exists(public_path(ltrim($__favicon, '/')))))
      <link rel="icon" type="{{ $__faviconType }}" href="{{ str_starts_with($__favicon, 'http') ? $__favicon : asset(ltrim($__favicon, '/')) }}">
    @else
      <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @endif

    {{-- Normalize clipboard localStorage before bundle loads (prevents indexOf crash + stale phantom badge) --}}
    <script>
    (function(){
      var k='clipboard';
      var t=['informationObject','actor','repository','accession','informationobject','library','dam'];
      var d;
      try{d=JSON.parse(localStorage.getItem(k))}catch(e){d=null}
      if(!d||typeof d!=='object')d={};
      // Ensure each type is an array, AND strip falsy/empty entries that would
      // falsely inflate the bundle's clipboard-count badge.
      t.forEach(function(x){
        if(!Array.isArray(d[x])){d[x]=[];return}
        d[x]=d[x].filter(function(v){
          if(v===null||v===undefined||v===false||v===0||v==='')return false;
          if(typeof v==='string'&&v.trim()==='')return false;
          return true;
        });
      });
      // Strip any extra junk keys that aren't in our known type list
      Object.keys(d).forEach(function(k2){if(t.indexOf(k2)===-1)delete d[k2]});
      localStorage.setItem(k,JSON.stringify(d));
    })();
    </script>

    {{-- IIIF viewer settings (closes audit issue #81). Exposes the 9 keys
         from /admin/ahgSettings/iiif as window.AHG_IIIF so the bundled
         ahg-iiif-viewer.js (and any future consumer) can apply them at
         viewer construction without an extra round-trip. Values are
         JSON-encoded server-side so booleans + numerics survive the
         boundary intact. Mirrors the voice/tts settings injection. --}}
    <script nonce="{{ $cspNonce }}">
      window.AHG_IIIF = {!! json_encode(\App\Support\IiifSettings::payload(), JSON_UNESCAPED_SLASHES) !!};
    </script>

    {{-- Media Player settings (closes audit issue #85). Exposes
         autoplay/loop/volume/controls/download from /admin/ahgSettings/media.
         A small inline init applies media_default_volume to every
         <audio>/<video> element once it has loaded enough metadata to
         know its initial volume — server-side blade attributes can't
         set volume since it's a JS property, not an HTML attribute.

         Issue #103: when media_player_type is 'plyr' or 'videojs', the
         matching vendor bundle (built by tools/plyr-build/) is loaded
         and the inline init wraps every <audio>/<video> in a Plyr or
         Video.js instance. If the vendor script fails to load (404,
         CSP block), window.Plyr / window.videojs stays undefined and
         the init falls back to native HTML5 — the existing controls /
         autoplay / loop / volume attributes still apply.

         Issue #106: media_player_type now accepts five values —
         'heratio' (default Heratio-branded UI), 'heratio-minimal'
         (small native UI), 'plyr', 'videojs', 'native'. The shared
         theme::components.media-player Blade component is the single
         source of truth for the rich / minimal layouts. The Plyr /
         Video.js wrap branches below are only entered when the
         operator explicitly chose those values; everything else
         (heratio / heratio-minimal / native / unknown) falls through
         to the native-attributes path so the component's own UI is
         preserved.

         Issue #101: when media_show_waveform is true, the WaveSurfer.js
         bundle (built by tools/wavesurfer-build/) is loaded and a
         separate init pass walks every .ahg-media-player wrapper (the
         AHG custom audio UI in _digital-object-viewer.blade.php),
         finds the hidden <audio> element + the placeholder progress
         div (#{audioId}-progress), and replaces the placeholder fill
         bar with a real WaveSurfer canvas. WaveSurfer is configured
         with media: <existingAudio> so the existing custom buttons
         (play/back/fwd/speed/volume) keep driving the same <audio> —
         the waveform is purely a visual upgrade. If the vendor script
         404s, window.WaveSurfer stays undefined and the init leaves
         the original placeholder in place. --}}
    @php
        $__mediaPlayer   = \App\Support\MediaSettings::playerType();
        $__mediaWaveform = \App\Support\MediaSettings::showWaveform();
    @endphp
    @if($__mediaPlayer === 'plyr')
      <link href="{{ asset('vendor/plyr/plyr.css') }}" rel="stylesheet">
      <script defer src="{{ asset('vendor/plyr/plyr.min.js') }}"></script>
    @elseif($__mediaPlayer === 'videojs')
      <link href="{{ asset('vendor/videojs/video-js.min.css') }}" rel="stylesheet">
      <script defer src="{{ asset('vendor/videojs/video.min.js') }}"></script>
    @endif
    @if($__mediaWaveform)
      <script defer src="{{ asset('vendor/wavesurfer/wavesurfer.min.js') }}"></script>
    @endif
    <script nonce="{{ $cspNonce }}">
      window.AHG_MEDIA = {!! json_encode(\App\Support\MediaSettings::payload(), JSON_UNESCAPED_SLASHES) !!};
      (function () {
        var cfg = window.AHG_MEDIA || {};
        var vol = typeof cfg.default_volume === 'number' ? cfg.default_volume : 1.0;
        var applyNative = function (el) {
          if (!el || el.dataset.ahgMediaApplied === '1') return;
          el.dataset.ahgMediaApplied = '1';
          // Issue #85: apply autoplay / loop / show_controls / volume from
          // /admin/ahgSettings/media. Properties are set dynamically so the
          // hardcoded blade markup in _digital-object-viewer.blade.php (which
          // ships with controls + no autoplay/loop) honours the operator
          // setting without per-page wiring.
          try { el.volume = vol; } catch (e) { /* iOS Safari blocks volume set; ignore */ }
          if (typeof cfg.show_controls === 'boolean') el.controls = !!cfg.show_controls;
          if (cfg.loop) el.loop = true;
          if (cfg.autoplay) {
            // .play() must be called after the user gesture in some browsers;
            // muting first is the standard workaround for unmuted-autoplay
            // policies. We don't force-mute though — let the browser refuse
            // and the user click play instead.
            try { var p = el.play(); if (p && typeof p.catch === 'function') p.catch(function(){}); } catch (e) {}
          }
        };
        var enhance = function () {
          // Some IO show pages (audio path in _digital-object-viewer.blade.php)
          // wrap a hidden <audio> in their own custom-controls UI inside a
          // .ahg-media-player block. Skip those — wrapping with Plyr/Video.js
          // would clobber the existing UI. data-no-ahg-media is the explicit
          // opt-out for any future markup that wants to skip the enhancement.
          var els = Array.prototype.filter.call(
            document.querySelectorAll('audio, video'),
            function (el) {
              if (el.dataset.noAhgMedia === '1' || el.hasAttribute('data-no-ahg-media')) return false;
              if (el.closest && el.closest('.ahg-media-player')) return false;
              return true;
            }
          );
          if (cfg.player_type === 'plyr' && typeof window.Plyr === 'function') {
            els.forEach(function (el) {
              if (el.dataset.ahgMediaApplied === '1') return;
              el.dataset.ahgMediaApplied = '1';
              try {
                new window.Plyr(el, {
                  iconUrl: '{{ asset('vendor/plyr/plyr.svg') }}',
                  volume: vol,
                  autoplay: !!cfg.autoplay,
                  loop: { active: !!cfg.loop },
                  controls: cfg.show_controls ? undefined
                    : ['play-large']
                });
              } catch (e) {
                /* Plyr init failed — fall back to native attributes already on el. */
                try { el.volume = vol; } catch (_) {}
              }
            });
            return;
          }
          if (cfg.player_type === 'videojs' && typeof window.videojs === 'function') {
            els.forEach(function (el) {
              if (el.dataset.ahgMediaApplied === '1') return;
              el.dataset.ahgMediaApplied = '1';
              try {
                if (!el.classList.contains('video-js')) el.classList.add('video-js');
                var p = window.videojs(el, {
                  controls: !!cfg.show_controls,
                  autoplay: !!cfg.autoplay,
                  loop: !!cfg.loop,
                  preload: 'metadata'
                });
                p.ready(function () { try { p.volume(vol); } catch (_) {} });
              } catch (e) {
                /* Video.js init failed — fall back to native attributes already on el. */
                try { el.volume = vol; } catch (_) {}
              }
            });
            return;
          }
          /* basic / unknown / vendor-script-unavailable: native HTML5 path. */
          els.forEach(function (el) {
            if (el.readyState >= 1) applyNative(el);
            else el.addEventListener('loadedmetadata', function () { applyNative(el); }, { once: true });
          });

          // Issue #85: also apply the basic settings to <audio> elements
          // inside .ahg-media-player wrappers (the AHG custom audio UI on
          // /sound etc.). enhance() filters those out because Plyr/Video.js
          // would clobber the custom controls — but applyNative just sets
          // properties on the underlying element, which is what the custom
          // UI's existing JS reads. Sync the visible volume slider too.
          // (#106: rich-mode video takes a different path — the component
          // applies window.AHG_MEDIA itself in its own IIFE so we don't
          // accidentally re-enable native browser controls on top of the
          // Heratio chrome.)
          document.querySelectorAll('.ahg-media-player audio').forEach(function (audio) {
            if (audio.dataset.ahgMediaApplied === '1') return;
            applyNative(audio);
            var wrapper = audio.closest('.ahg-media-player');
            if (!wrapper) return;
            var slider = wrapper.querySelector('input[type="range"][id$="-vol"]');
            if (slider) {
              slider.value = String(vol);
              // The custom UI's inline JS listens for 'input'; dispatch one
              // so the badge / fill colour stay consistent with the new value.
              try { slider.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
            }
          });

          // Issue #85: gate media-block download buttons by media_show_download.
          // The hardcoded download anchors in _digital-object-viewer.blade.php
          // (one per audio + video block) sit inside an auth-only Blade gate so
          // logged-in users see them by default. Hide them via JS when
          // show_download is off.
          if (!cfg.show_download) {
            // Audio: download anchor lives inside the .ahg-media-player wrapper.
            document.querySelectorAll('.ahg-media-player a[download]').forEach(function (a) {
              a.style.display = 'none';
            });
            // Video: download anchor sits in the next sibling .d-flex of the
            // <video> element. Walk parents one level up + grab any a[download].
            document.querySelectorAll('video').forEach(function (v) {
              var sib = v.nextElementSibling;
              while (sib) {
                sib.querySelectorAll('a[download]').forEach(function (a) { a.style.display = 'none'; });
                if (sib.tagName === 'DIV' && sib.classList.contains('mt-2')) break;
                sib = sib.nextElementSibling;
              }
            });
          }
        };
        // Issue #101: separate pass that decorates the AHG custom audio UI
        // with a real WaveSurfer waveform when media_show_waveform=true.
        // Runs independently of the player_type branches so it composes
        // with player_type='basic'. The custom UI's hidden <audio> is
        // bound via WaveSurfer's `media` option so the existing buttons
        // (play/pause/back/fwd/speed/volume) keep driving playback.
        var enhanceWaveform = function () {
          if (!cfg.show_waveform) return;
          if (typeof window.WaveSurfer === 'undefined' || !window.WaveSurfer || typeof window.WaveSurfer.create !== 'function') {
            // Vendor script failed to load — leave the placeholder
            // progress bar in place. The native custom-controls UX
            // continues to work (it doesn't depend on WaveSurfer).
            return;
          }
          // WaveSurfer 7 needs `url` to fetch+decode the audio for the
          // waveform; `media: audio` only binds playback to the
          // existing <audio>. Pass both so the waveform draws AND
          // the original audio element (and any sibling controls)
          // keep driving the same audio source.
          var wsCreate = function (container, audio, sourceEl) {
            return window.WaveSurfer.create({
              container: container,
              waveColor: 'rgba(13,110,253,0.30)',
              progressColor: 'rgba(13,110,253,0.85)',
              cursorColor: 'rgba(13,110,253,0.85)',
              cursorWidth: 2,
              height: 60,
              barWidth: 2,
              barGap: 1,
              normalize: true,
              media: audio,
              url: sourceEl.src
            });
          };

          // Path 1: AHG custom audio UI (hidden <audio> behind a custom
          // controls block in _digital-object-viewer.blade.php). Replace
          // the placeholder progress bar with the WaveSurfer canvas; the
          // existing buttons keep driving the same <audio>.
          document.querySelectorAll('.ahg-media-player').forEach(function (wrapper) {
            if (wrapper.dataset.ahgWaveformApplied === '1') return;
            var audio = wrapper.querySelector('audio');
            if (!audio) return;
            var progressDiv = wrapper.querySelector('[id$="-progress"]');
            if (!progressDiv) return;
            var sourceEl = audio.querySelector('source');
            if (!sourceEl || !sourceEl.src) return;

            wrapper.dataset.ahgWaveformApplied = '1';
            var originalHTML = progressDiv.innerHTML;
            progressDiv.innerHTML = '';
            progressDiv.style.cursor = 'pointer';
            // The wrapper has waveColor='rgba(255,255,255,0.30)' against the
            // dark gradient background it's painted on; override the default
            // primary-blue tint so bars are visible on the dark backdrop.
            try {
              wsCreate(progressDiv, audio, sourceEl).setOptions({
                waveColor: 'rgba(255,255,255,0.30)',
                cursorColor: 'rgba(255,255,255,0.85)'
              });
            } catch (e) {
              progressDiv.innerHTML = originalHTML;
              wrapper.dataset.ahgWaveformApplied = '0';
            }
          });

          // Path 2: plain <audio> elements (e.g. the digital-object
          // component on museum/actor/repository show pages). Supplement
          // them with a sibling WaveSurfer canvas inserted above; native
          // controls stay in place so play/pause UX is unchanged.
          document.querySelectorAll('audio').forEach(function (audio) {
            if (audio.dataset.ahgWaveformApplied === '1') return;
            // Skip elements already handled by Path 1 (inside .ahg-media-player)
            // and any explicit opt-out.
            if (audio.closest && audio.closest('.ahg-media-player')) return;
            if (audio.dataset.noAhgMedia === '1' || audio.hasAttribute('data-no-ahg-media')) return;
            var sourceEl = audio.querySelector('source');
            if (!sourceEl || !sourceEl.src) return;

            audio.dataset.ahgWaveformApplied = '1';
            var canvasDiv = document.createElement('div');
            canvasDiv.className = 'ahg-waveform mb-2';
            canvasDiv.style.cursor = 'pointer';
            audio.parentNode.insertBefore(canvasDiv, audio);

            try {
              wsCreate(canvasDiv, audio, sourceEl);
            } catch (e) {
              // WaveSurfer init failed — pull the empty sibling back out so
              // the page looks identical to the pre-#101 state.
              if (canvasDiv.parentNode) canvasDiv.parentNode.removeChild(canvasDiv);
              audio.dataset.ahgWaveformApplied = '0';
            }
          });
        };
        var run = function () { enhance(); enhanceWaveform(); };
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', run);
        } else {
          run();
        }
      })();
    </script>

    {{-- Webpack bundles --}}
    @if($themeData['vendorJsBundle'] ?? null)
      <script defer src="{{ $themeData['vendorJsBundle'] }}"></script>
    @endif
    @if($themeData['themeJsBundle'] ?? null)
      <script defer src="{{ $themeData['themeJsBundle'] }}"></script>
    @endif

    {{-- jQuery CSRF setup — must load after jQuery (vendor bundle) and before DOMContentLoaded
         so that the AtoM theme bundle's jQuery AJAX calls include the CSRF token --}}
    <script defer src="{{ asset('vendor/ahg-theme-b5/js/jquery-csrf-setup.js') }}"></script>

    @if($themeData['themeCssBundle'] ?? null)
      <link href="{{ $themeData['themeCssBundle'] }}" rel="stylesheet">
    @endif

    {{-- Theme CSS --}}
    <link href="{{ asset('vendor/ahg-theme-b5/css/ahg-theme.css') }}" rel="stylesheet">
    {{-- Dynamic theme CSS — only when "Theme Enabled" is checked at /admin/ahgSettings/themes.
         When off, the bundled theme defaults take over — useful as an instant undo if the
         admin breaks their colour palette. --}}
    @if((string) \AhgCore\Services\AhgSettingsService::get('ahg_theme_enabled', 'true') === 'true')
      <link href="{{ route('settings.dynamic-css') }}" rel="stylesheet">
    @endif
    <link href="{{ asset('vendor/ahg-theme-b5/css/custom.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/ahg-theme-b5/css/style.css') }}" rel="stylesheet">

    {{-- Operator-overridable header background colour from
         settings.header_background_colour (legacy AtoM scope=NULL setting).
         Empty value means "use the theme default" baked into custom.css. --}}
    @php
      $__headerBg = \AhgCore\Support\GlobalSettings::headerBackgroundColour();
    @endphp
    @if ($__headerBg !== '')
      <style nonce="{{ $cspNonce }}">
        :root { --ahg-primary: {{ $__headerBg }}; }
      </style>
    @endif

    @stack('css')
  </head>
  <body class="d-flex flex-column min-vh-100 @yield('body-class')">

    {{-- Google Tag Manager (noscript) --}}
    @include('theme::partials.tag-manager', ['slot' => 'noscript'])

    {{-- Header --}}
    @include('theme::partials.header')

    {{-- Privacy notification banner --}}
    @include('theme::partials.privacy-message')

    {{-- Accessibility helpers (WCAG 2.1 AA) --}}
    @include('theme::partials.accessibility-helpers')

    {{-- Print preview bar --}}
    @include('theme::partials.print-preview-bar')

    {{-- Site description bar --}}
    @if(($themeData['toggleDescription'] ?? false) && !empty($themeData['siteDescription']))
      <div class="ahg-description-bar">
        <div class="container-xl py-1">
          {{ $themeData['siteDescription'] }}
        </div>
      </div>
    @endif

    {{-- Admin notifications --}}
    @include('theme::partials.admin-notifications')

    {{-- Main content wrapper --}}
    <div id="wrapper" class="container-xxl pt-3 flex-grow-1 pb-4">

      {{-- Flash messages --}}
      @include('theme::partials.alerts')

      {{-- Breadcrumbs --}}
      @hasSection('breadcrumbs')
        <nav aria-label="{{ __('breadcrumb') }}" id="breadcrumb">
          <ol class="breadcrumb">
            @yield('breadcrumbs')
          </ol>
        </nav>
      @endif

      {{-- Page content --}}
      @yield('layout-content')
    </div>

    {{-- Footer --}}
    @include('theme::partials.footer')

    {{-- D3.js for visualizations. Served from jsdelivr because d3js.org is not
         in our CSP script-src allowlist (and jsdelivr is) — see HeratioCspPreset. --}}
    <script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>

    {{-- Base JS (display-mode + voiceCommands already in theme bundle — don't load standalone) --}}
    <script src="{{ asset('vendor/ahg-theme-b5/js/base.js') }}"></script>
    {{-- Clipboard toggle handled by AtoM theme bundle; this adds server sync --}}
    <script src="{{ asset('vendor/ahg-core/js/clipboard-sync.js') }}?v={{ time() }}"></script>

    {{-- Voice Commands (CSS + data partials only — JS class is in the theme bundle) --}}
    @include('theme::partials.voice-commands')
    <link rel="stylesheet" href="{{ asset('vendor/ahg-theme-b5/css/voiceCommands.css') }}">

    @stack('js')

    {{-- CSP-safe replacements for inline onchange="this.form.submit()" / onchange="location=this.value".
         Tag a select with data-csp-auto-submit (auto-submits its enclosing form) or
         data-csp-go (navigates to the option value, blank to ignore). --}}
    <script nonce="{{ $cspNonce }}">
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('[data-csp-auto-submit]').forEach(function (el) {
        el.addEventListener('change', function () { if (el.form) el.form.submit(); });
      });
      document.querySelectorAll('[data-csp-go]').forEach(function (el) {
        el.addEventListener('change', function () { if (el.value) window.location.href = el.value; });
      });
      document.querySelectorAll('[data-csp-go-prefix]').forEach(function (el) {
        el.addEventListener('change', function () {
          var p = el.getAttribute('data-csp-go-prefix') || '';
          window.location.href = p + el.value;
        });
      });
    });
    </script>

    {{-- Auto-open first accordion block on every page --}}
    {{-- Auto-open first accordion block on every page (skip accordions marked data-default-closed) --}}
    <script nonce="{{ $cspNonce }}">
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.accordion').forEach(function(acc) {
        if (acc.dataset.defaultClosed !== undefined) return;
        var firstCollapse = acc.querySelector('.accordion-collapse');
        var firstButton = acc.querySelector('.accordion-button');
        if (firstCollapse && !firstCollapse.classList.contains('show')) {
          firstCollapse.classList.add('show');
          if (firstButton) {
            firstButton.classList.remove('collapsed');
            firstButton.setAttribute('aria-expanded', 'true');
          }
        }
      });
    });
    </script>

    {{-- Carousel / imageflow / thumb fallback: when an <img.img-thumbnail>
         points at a non-image asset (mp3/glb/zip/pdf/docx/etc.) the browser
         renders a broken-image icon. Catch the error in the capture phase and
         swap the img for a Font Awesome icon picked by URL extension, so the
         "any file type, any sector" GLAM/DAM mix presents cleanly without
         touching the dozens of carousel partials across locked packages. --}}
    <script nonce="{{ $cspNonce }}">
    (function () {
      'use strict';
      var ICON_MAP = {
        mp3: 'fa-music', wav: 'fa-music', m4a: 'fa-music', flac: 'fa-music', ogg: 'fa-music', aac: 'fa-music',
        mp4: 'fa-film', mov: 'fa-film', webm: 'fa-film', avi: 'fa-film', mkv: 'fa-film', mxf: 'fa-film', rm: 'fa-film',
        pdf: 'fa-file-pdf',
        zip: 'fa-file-archive', rar: 'fa-file-archive', gz: 'fa-file-archive', tgz: 'fa-file-archive', tar: 'fa-file-archive', '7z': 'fa-file-archive',
        glb: 'fa-cube', gltf: 'fa-cube', obj: 'fa-cube', stl: 'fa-cube', fbx: 'fa-cube', dae: 'fa-cube',
        docx: 'fa-file-word', doc: 'fa-file-word', odt: 'fa-file-word',
        xlsx: 'fa-file-excel', xls: 'fa-file-excel', csv: 'fa-file-excel', ods: 'fa-file-excel',
        pptx: 'fa-file-powerpoint', ppt: 'fa-file-powerpoint', odp: 'fa-file-powerpoint',
        txt: 'fa-file-alt', rtf: 'fa-file-alt', md: 'fa-file-alt',
        swf: 'fa-bolt',
        xml: 'fa-code', json: 'fa-code', yaml: 'fa-code', yml: 'fa-code'
      };
      function extOf(url) {
        if (!url) return '';
        var u = url.split('?')[0].split('#')[0];
        var slash = u.lastIndexOf('/');
        if (slash >= 0) u = u.slice(slash + 1);
        var dot = u.lastIndexOf('.');
        return dot < 0 ? '' : u.slice(dot + 1).toLowerCase();
      }
      function replaceWithIcon(img) {
        if (img.__ahgIconSwapped) return;
        img.__ahgIconSwapped = true;
        var cls = ICON_MAP[extOf(img.getAttribute('src') || '')] || 'fa-file';
        var w = img.offsetWidth || img.width || parseInt(img.style.width, 10) || 90;
        var h = img.offsetHeight || img.height || parseInt(img.style.height, 10) || 68;
        var span = document.createElement('span');
        span.className = 'ahg-thumb-fallback img-thumbnail d-inline-flex align-items-center justify-content-center text-muted';
        span.style.width = w + 'px';
        span.style.height = h + 'px';
        span.style.background = '#f1f3f5';
        span.style.fontSize = Math.max(16, Math.min(w, h) * 0.45) + 'px';
        span.style.verticalAlign = 'middle';
        span.title = img.getAttribute('alt') || '';
        var ic = document.createElement('i');
        ic.className = 'fas ' + cls;
        ic.setAttribute('aria-hidden', 'true');
        span.appendChild(ic);
        if (img.parentNode) img.parentNode.replaceChild(span, img);
      }
      function init() {
        // Capture-phase listener: error doesn't bubble, so capture: true on document
        // catches it on the way down from window to the img.
        document.addEventListener('error', function (e) {
          var t = e.target;
          if (t && t.tagName === 'IMG' && t.classList && t.classList.contains('img-thumbnail')) {
            replaceWithIcon(t);
          }
        }, true);
        // Sweep already-broken images (load failed before listener attached).
        document.querySelectorAll('img.img-thumbnail').forEach(function (img) {
          if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) {
            replaceWithIcon(img);
          }
        });
      }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
      } else { init(); }
    })();
    </script>

    {{-- CSP-safe rebind of inline onsubmit="return confirm(...)" / onclick="return confirm(...)".
         Strict CSP (script-src nonce) blocks inline event-handler attributes — extract the
         confirm() message from the attribute and re-attach as a proper event listener. --}}
    <script nonce="{{ $cspNonce }}">
    (function () {
      'use strict';
      var rx = /confirm\(\s*(['"`])([\s\S]*?)\1\s*\)/;
      function unescape(s) { return s.replace(/\\n/g, '\n').replace(/\\'/g, "'").replace(/\\"/g, '"').replace(/\\\\/g, '\\'); }
      function bind(selector, eventName) {
        document.querySelectorAll(selector).forEach(function (el) {
          var attr = el.getAttribute('on' + eventName);
          if (!attr) return;
          var m = attr.match(rx);
          if (!m) return;
          var msg = unescape(m[2]);
          el.removeAttribute('on' + eventName);
          el.addEventListener(eventName, function (ev) {
            if (!window.confirm(msg)) { ev.preventDefault(); ev.stopPropagation(); return false; }
          });
        });
        // Also support data-confirm="msg" as the modern alternative
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
          if (el.__dataConfirmBound) return;
          el.__dataConfirmBound = true;
          var ev = (el.tagName === 'FORM') ? 'submit' : 'click';
          el.addEventListener(ev, function (e) {
            if (!window.confirm(el.getAttribute('data-confirm'))) { e.preventDefault(); e.stopPropagation(); return false; }
          });
        });
      }
      function run() { bind('form[onsubmit]', 'submit'); bind('button[onclick],a[onclick],input[onclick]', 'click'); }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
      } else {
        run();
      }
    })();
    </script>

    {{-- Floating Cart Tab — only visible when cart has items --}}
    @include('theme::partials.cart-tab')

    {{-- Floating Feedback Tab --}}
    @include('theme::partials.feedback-tab')

    {{-- Global JS error logger — sends client errors to Laravel log --}}
    <script nonce="{{ $cspNonce }}">
    window.onerror = function(msg, url, line, col, err) {
      try {
        fetch('/api/log-error', {
          method: 'POST',
          headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},
          body: JSON.stringify({message:msg, url:url, line:line, col:col, stack:err?.stack||''})
        }).catch(function(){});
      } catch(e) {}
    };
    window.addEventListener('unhandledrejection', function(ev) {
      try {
        var msg = ev.reason?.message || String(ev.reason || 'Unhandled promise rejection');
        fetch('/api/log-error', {
          method: 'POST',
          headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},
          body: JSON.stringify({message:msg, url:location.href, stack:ev.reason?.stack||''})
        }).catch(function(){});
      } catch(e) {}
    });
    </script>
  </body>
</html>
