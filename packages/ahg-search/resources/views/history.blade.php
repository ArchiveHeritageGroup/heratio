{{--
  Search enhancement - search history.

  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
--}}
@extends('theme::layouts.1col')

@section('title', __('Search history'))
@section('body-class', 'search search-enhancement search-history')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-4">
    <i class="bi bi-clock-history me-3 fs-2" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Search history') }}</h1>
      <span class="small text-muted">{{ __('A record of your recent searches') }}</span>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <p class="mb-0 text-muted">
        {{ __('No recent searches to show. Your search history will appear here as you use the search tools.') }}
      </p>
    </div>
  </div>
@endsection
