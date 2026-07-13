{{--
  Search enhancement - admin query templates.

  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
--}}
@extends('theme::layouts.1col')

@section('title', __('Admin templates'))
@section('body-class', 'search admin search-enhancement admin-templates')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-4">
    <i class="bi bi-file-earmark-text me-3 fs-2" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Admin templates') }}</h1>
      <span class="small text-muted">{{ __('Manage reusable query templates for search enhancement') }}</span>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <p class="mb-0 text-muted">
        {{ __('No query templates have been configured yet. Templates let administrators pre-build common queries and filters that searchers can apply with a single click.') }}
      </p>
    </div>
  </div>
@endsection
