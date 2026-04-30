@extends('theme::layouts.1col')

@section('title', 'RiC Dashboard')
@section('body-class', 'admin ric')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-project-diagram me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('RiC Dashboard') }}</h1>
      <span class="small text-muted">Records in Contexts</span>
    </div>
  </div>

  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>RiC tables not configured.</strong>
    The required database tables for the RiC module have not been created yet.
    Please run the RiC migration to set up the following tables:
    <ul class="mt-2 mb-0">
      <li><code>ric_sync_status</code></li>
      <li><code>ric_sync_queue</code></li>
      <li><code>ric_orphan_tracking</code></li>
      <li><code>ric_sync_log</code></li>
    </ul>
  </div>
@endsection
