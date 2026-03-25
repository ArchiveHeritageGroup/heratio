<!DOCTYPE html>
<html lang="{{ $themeData['culture'] ?? 'en' }}" dir="ltr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $themeData['siteTitle'] ?? 'Heratio')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    {{-- Webpack bundles --}}
    @if($themeData['vendorJsBundle'] ?? null)
      <script defer src="{{ $themeData['vendorJsBundle'] }}"></script>
    @endif
    @if($themeData['themeJsBundle'] ?? null)
      <script defer src="{{ $themeData['themeJsBundle'] }}"></script>
    @endif
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

    {{-- Header --}}
    @include('theme::partials.header')

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

    {{-- Base JS --}}
    <script src="{{ asset('vendor/ahg-theme-b5/js/display-mode.js') }}"></script>
    <script src="{{ asset('vendor/ahg-theme-b5/js/base.js') }}"></script>
    <script src="{{ asset('vendor/ahg-core/js/clipboard.js') }}"></script>

    {{-- Voice Commands --}}
    @include('theme::partials.voice-commands')
    <link rel="stylesheet" href="{{ asset('vendor/ahg-theme-b5/css/voiceCommands.css') }}">
    <script src="{{ asset('vendor/ahg-theme-b5/js/voiceCommandRegistry.js') }}"></script>
    <script src="{{ asset('vendor/ahg-theme-b5/js/voiceCommandTranslations.js') }}"></script>
    <script src="{{ asset('vendor/ahg-theme-b5/js/voiceCommands.js') }}"></script>

    @stack('js')

    {{-- Auto-open first accordion block on every page --}}
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.accordion').forEach(function(acc) {
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

    {{-- Floating Feedback Tab --}}
    @include('theme::partials.feedback-tab')
  </body>
</html>
