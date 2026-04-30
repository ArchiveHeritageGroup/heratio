@extends('theme::layouts.master')

@section('layout-content')
  <style>
    #main-column { font-size: var(--ahg-font-body, 0.95rem); }
    #left-column, #right-column { font-size: var(--ahg-font-sidebar, 0.85rem); }
    #left-column .card-header, #right-column .card-header { font-size: var(--ahg-font-sidebar-header, 0.82rem); padding: 0.4rem 0.6rem; }
    #left-column .list-group-item, #right-column .list-group-item { padding: 0.35rem 0.6rem; font-size: var(--ahg-font-sidebar, 0.85rem); }
    #left-column .card-body, #right-column .card-body { padding: 0.5rem 0.6rem; }
    #left-column h5, #left-column h6, #right-column h5, #right-column h6 { font-size: var(--ahg-font-sidebar, 0.85rem); }
    /* Right column: shrunk twice (16.67% → 11.11% → 7.44%, ~33% each), then
       user asked for +1cm back. Using calc() so it scales with viewport.
       Main absorbs the inverse; left stays at 16.67%. Below md, stacks. */
    @media (min-width: 768px) {
      #right-column { flex: 0 0 calc(7.44%  + 2cm); max-width: calc(7.44%  + 2cm); }
      #main-column  { flex: 0 0 calc(75.89% - 2cm); max-width: calc(75.89% - 2cm); }
    }
  </style>
  <div class="row">
    <div id="left-column" class="col-md-2">
      @yield('sidebar')
    </div>
    <div id="main-column" class="col-md-8" role="main">
      @yield('title-block')
      @yield('before-content')
      <div id="content">
        @yield('content')
      </div>
      @yield('after-content')
    </div>
    <div id="right-column" class="col-md-2">
      @yield('right')
    </div>
  </div>
@endsection
