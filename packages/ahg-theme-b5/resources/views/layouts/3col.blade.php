@extends('theme::layouts.master')

@section('layout-content')
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
