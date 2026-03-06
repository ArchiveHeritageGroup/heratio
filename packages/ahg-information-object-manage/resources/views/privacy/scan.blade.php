@extends('theme::layouts.1col')
@section('title', 'Scan for PII — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-search',
    'featureTitle' => 'Scan for PII',
    'featureDescription' => 'Detect personally identifiable information in description text and digital objects',
  ])

  @if(isset($io->scope_and_content) && $io->scope_and_content)
    <div class="card mb-3">
      <div class="card-header fw-bold">Text to scan</div>
      <div class="card-body">
        <p>{{ $io->scope_and_content }}</p>
      </div>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-header fw-bold">Scan results</div>
    <div class="card-body" id="pii-results">
      <p class="text-muted">Click the button below to scan for PII.</p>
    </div>
  </div>

  <button class="btn btn-warning" id="pii-scan-btn" data-object-id="{{ $io->id }}">
    <i class="fas fa-user-shield me-1"></i> Run PII scan
  </button>
@endsection
