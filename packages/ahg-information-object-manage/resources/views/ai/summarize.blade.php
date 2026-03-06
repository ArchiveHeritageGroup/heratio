@extends('theme::layouts.1col')
@section('title', 'Generate Summary — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-file-alt',
    'featureTitle' => 'Generate Summary',
    'featureDescription' => 'AI-generated summary of the archival description',
  ])

  @if(isset($io->scope_and_content) && $io->scope_and_content)
    <div class="card mb-3">
      <div class="card-header fw-bold">Current description</div>
      <div class="card-body">
        <p>{{ $io->scope_and_content }}</p>
      </div>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-header fw-bold">Generated summary</div>
    <div class="card-body" id="summary-result">
      <p class="text-muted">Click the button below to generate a summary.</p>
    </div>
  </div>

  <button class="btn atom-btn-outline-success" id="generate-summary-btn" data-object-id="{{ $io->id }}">
    <i class="fas fa-magic me-1"></i> Generate summary
  </button>
@endsection
