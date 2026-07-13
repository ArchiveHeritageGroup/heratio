{{--
  Search enhancement - saved searches.

  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
--}}
@extends('theme::layouts.1col')

@section('title', __('Saved searches'))
@section('body-class', 'search search-enhancement saved-searches')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-4">
    <i class="bi bi-bookmark me-3 fs-2" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Saved searches') }}</h1>
      <span class="small text-muted">{{ __('Your bookmarked searches for quick re-use') }}</span>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <p class="mb-3 text-muted">
        {{ __('You have no saved searches yet. Run a search and use "Save this search" to keep it here for later.') }}
      </p>
      <a href="{{ route('search') }}" class="btn btn-primary">
        <i class="bi bi-search me-1" aria-hidden="true"></i>{{ __('Start a search') }}
      </a>
    </div>
  </div>
@endsection
