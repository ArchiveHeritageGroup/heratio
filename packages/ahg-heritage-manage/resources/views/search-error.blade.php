@extends('theme::layouts.1col')
@section('title', 'Search Error')
@section('body-class', 'heritage')

@section('content')
<div class="container py-5">
  <div class="alert alert-warning">
    <h4><i class="fas fa-exclamation-circle me-2"></i>{{ __('Search Error') }}</h4>
    <p>{{ $error ?? 'An error occurred during your search. Please try again.' }}</p>
    <div class="mt-3">
      <a href="{{ route('heritage.search') }}" class="btn atom-btn-secondary"><i class="fas fa-search me-1"></i>{{ __('Try Again') }}</a>
      <a href="{{ route('heritage.landing') }}" class="btn atom-btn-white ms-2"><i class="fas fa-home me-1"></i>{{ __('Heritage Home') }}</a>
    </div>
  </div>
</div>
@endsection
