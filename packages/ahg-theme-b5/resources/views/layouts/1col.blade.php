@extends('theme::layouts.master')

@section('layout-content')
  <div id="main-column" role="main">
    @yield('title-block')
    @yield('before-content')
    <div id="content">
      @yield('content')
    </div>
    @yield('after-content')
  </div>
@endsection
