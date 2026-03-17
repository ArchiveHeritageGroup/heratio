{{-- Skip to main content (accessibility) --}}
<div class="visually-hidden-focusable p-3 border-bottom">
  <a class="btn btn-sm btn-secondary" href="#main-column">Skip to main content</a>
</div>

<header id="top-bar" class="navbar navbar-expand-lg navbar-dark bg-dark" role="navigation" aria-label="Main navigation">
  <div class="container-fluid">
    {{-- Brand / Logo --}}
    @if(($themeData['toggleLogo'] ?? true) || ($themeData['toggleTitle'] ?? true))
      <a class="navbar-brand d-flex flex-wrap flex-lg-nowrap align-items-center py-0 me-0" href="{{ url('/') }}" title="Home" rel="home">
        @if($themeData['toggleLogo'] ?? true)
          @if($themeData['customLogo'] ?? null)
            <img src="{{ $themeData['customLogo'] }}" alt="Logo" class="d-inline-block my-2 me-3" height="50">
          @else
            <img src="{{ asset('vendor/ahg-theme-b5/images/image.png') }}" alt="Logo" class="d-inline-block my-2 me-3" height="50">
          @endif
        @endif
        @if(($themeData['toggleTitle'] ?? true) && !empty($themeData['siteTitle']))
          <span class="text-wrap my-1 me-3">{{ $themeData['siteTitle'] }}</span>
        @endif
      </a>
    @endif

    {{-- Hamburger toggle --}}
    <button class="navbar-toggler atom-btn-secondary my-2 me-1 px-1" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-content" aria-controls="navbar-content" aria-expanded="false">
      <i class="fas fa-2x fa-fw fa-bars" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Toggle navigation" aria-hidden="true"></i>
      <span class="visually-hidden">Toggle navigation</span>
    </button>

    <div class="collapse navbar-collapse flex-wrap justify-content-end me-1" id="navbar-content">
      <div class="d-flex flex-wrap flex-lg-nowrap flex-grow-1">
        {{-- Browse menu --}}
        @include('theme::partials.menus.browse-menu')

        {{-- Search box --}}
        @include('theme::partials.menus.search-box')
      </div>

      <div class="d-flex flex-nowrap flex-column flex-lg-row align-items-strech align-items-lg-center">
        <ul class="navbar-nav mx-lg-2">
          {{-- Main menu (Add / Manage / Import / Admin) --}}
          @if($themeData['isAuthenticated'] ?? false)
            @include('theme::partials.menus.main-menu')
          @endif

          {{-- Help Center --}}
          <li class="nav-item d-flex flex-column">
            <a class="nav-link d-flex align-items-center p-0" href="{{ url('/help') }}" id="help-center-menu">
              <i class="fas fa-2x fa-fw fa-question-circle px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="Help Center" aria-hidden="true"></i>
              <span class="d-lg-none mx-1" aria-hidden="true">Help Center</span>
              <span class="visually-hidden">Help Center</span>
            </a>
          </li>

          {{-- Clipboard --}}
          @include('theme::partials.menus.clipboard-menu')

          {{-- Language switcher --}}
          @include('theme::partials.menus.language-menu')

          {{-- Quick links --}}
          @include('theme::partials.menus.quick-links-menu')

          {{-- AHG Admin menu --}}
          @if($themeData['isAdmin'] ?? false)
            @include('theme::partials.menus.ahg-admin-menu')
          @endif
        </ul>

        {{-- User menu --}}
        @include('theme::partials.menus.user-menu')
      </div>
    </div>
  </div>
</header>
