@php $cspNonce = base64_encode(random_bytes(16)); @endphp
<!DOCTYPE html>
<html lang="{{ $themeData['culture'] ?? 'en' }}" dir="ltr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $themeData['siteTitle'] ?? 'Heratio')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Google Tag Manager (script) --}}
    @include('theme::partials.tag-manager', ['slot' => 'script'])

    {{-- Google Analytics --}}
    @include('theme::partials.google-analytics')

    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    {{-- Normalize clipboard localStorage before bundle loads (prevents indexOf crash) --}}
    <script>
    (function(){var k='clipboard',t=['informationObject','actor','repository','accession','informationobject','library','dam'],d;
    try{d=JSON.parse(localStorage.getItem(k))}catch(e){d=null}
    if(!d||typeof d!=='object')d={};
    t.forEach(function(x){if(!Array.isArray(d[x]))d[x]=[]});
    localStorage.setItem(k,JSON.stringify(d))})();
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
    <link href="{{ route('settings.dynamic-css') }}" rel="stylesheet">
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
      <div class="ahg-description-bar" style="background-color: var(--ahg-descbar-bg, var(--ahg-primary, #005837)); color: var(--ahg-descbar-text, #fff);">
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
        <nav aria-label="breadcrumb" id="breadcrumb">
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

    {{-- D3.js for visualizations --}}
    <script src="https://d3js.org/d3.v7.min.js"></script>

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
