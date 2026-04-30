@extends('theme::layouts.1col')
@section('title', 'Gallery')
@section('body-class', 'gallery index')
@section('title-block')<h1 class="mb-0">{{ __('Gallery') }}</h1>@endsection
@section('content')
<div class="row">
  <div class="col-md-4 mb-3"><a href="{{ route('gallery.browse') }}" class="btn atom-btn-white w-100"><i class="fas fa-images me-1"></i>{{ __('Browse Artworks') }}</a></div>
  <div class="col-md-4 mb-3"><a href="{{ route('gallery.artists') }}" class="btn atom-btn-white w-100"><i class="fas fa-palette me-1"></i>{{ __('Artists') }}</a></div>
  <div class="col-md-4 mb-3"><a href="{{ route('gallery.dashboard') }}" class="btn atom-btn-white w-100"><i class="fas fa-tachometer-alt me-1"></i>{{ __('Dashboard') }}</a></div>
</div>
@endsection
