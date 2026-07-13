{{--
  Semantic search admin - test query expansion.

  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
--}}
@extends('theme::layouts.1col')

@section('title', __('Test query expansion'))
@section('body-class', 'search admin semantic-search-admin test-expand')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-4">
    <i class="bi bi-arrows-angle-expand me-3 fs-2" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Test query expansion') }}</h1>
      <span class="small text-muted">{{ __('Preview how a query is expanded with synonyms and related terms') }}</span>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <form method="post" action="{{ route('semanticSearchAdmin.testExpand') }}">
        @csrf
        <div class="mb-3">
          <label for="expand-query" class="form-label fw-semibold">{{ __('Query') }}</label>
          <input type="text" id="expand-query" name="q" class="form-control"
                 value="{{ request('q') }}" placeholder="{{ __('Enter a query to expand...') }}">
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-arrows-angle-expand me-1" aria-hidden="true"></i>{{ __('Expand') }}
        </button>
      </form>
      <p class="mt-3 mb-0 text-muted">
        {{ __('Enter a query above to preview the expanded terms used for semantic search.') }}
      </p>
    </div>
  </div>
@endsection
