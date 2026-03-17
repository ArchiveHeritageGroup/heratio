@extends('theme::layouts.master')

@section('layout-content')
  <div class="row">
    <div id="sidebar" class="col-md-3" role="complementary" aria-label="Filters and navigation">
      @yield('sidebar')
    </div>
    <div id="main-column" class="col-md-9" role="main">
      @yield('title-block')
      @yield('before-content')
      <div id="content">
        @yield('content')
      </div>
      @yield('after-content')
    </div>
  </div>
@endsection
