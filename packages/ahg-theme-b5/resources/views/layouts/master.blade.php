@php $cspNonce = csp_nonce(); @endphp
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
    <meta property="csp-nonce" content="{{ csp_nonce() }}">

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
