{{--
  Search enhancement - save a search.

  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
--}}
@extends('theme::layouts.1col')

@section('title', __('Save search'))
@section('body-class', 'search search-enhancement save-search')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-4">
    <i class="bi bi-bookmark-plus me-3 fs-2" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Save search') }}</h1>
      <span class="small text-muted">{{ __('Give this search a name so you can return to it later') }}</span>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <form method="post" action="{{ route('searchEnhancement.saveSearch') }}">
        @csrf
        <div class="mb-3">
          <label for="save-name" class="form-label fw-semibold">{{ __('Name') }}</label>
          <input type="text" id="save-name" name="name" class="form-control"
                 placeholder="{{ __('e.g. Land claims - Eastern Cape') }}">
        </div>
        <input type="hidden" name="q" value="{{ request('q') }}">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-bookmark-plus me-1" aria-hidden="true"></i>{{ __('Save') }}
        </button>
        <a href="{{ route('searchEnhancement.savedSearches') }}" class="btn btn-secondary">
          {{ __('View saved searches') }}
        </a>
      </form>
    </div>
  </div>
@endsection
