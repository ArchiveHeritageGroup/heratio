@extends('theme::layouts.1col')

@section('title', 'Duplicate Detection')
@section('body-class', 'admin dedupe')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-clone me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Duplicate Detection') }}</h1>
      <span class="small text-muted">{{ __('Configuration required') }}</span>
    </div>
  </div>

  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>{{ __('Duplicate detection tables not configured.') }}</strong>
    <p class="mb-0 mt-2">
      The required database tables (<code>ahg_duplicate_detection</code>, <code>ahg_duplicate_rule</code>,
      <code>ahg_dedupe_scan</code>) do not exist. Please run the appropriate migrations to set up duplicate detection.
    </p>
  </div>
@endsection
