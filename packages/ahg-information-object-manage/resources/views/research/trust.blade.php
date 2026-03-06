@extends('theme::layouts.1col')
@section('title', 'Trust Score — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-star-half-alt',
    'featureTitle' => 'Trust Score',
    'featureDescription' => 'Calculated trust and reliability score for this description',
  ])

  <div class="card">
    <div class="card-body text-center">
      <h2 class="display-4 text-muted">—</h2>
      <p class="text-muted">Trust score not yet calculated for this description.</p>
      <button class="btn atom-btn-outline-success" onclick="alert('Trust score calculation — migration in progress'); return false;">
        <i class="fas fa-calculator me-1"></i> Calculate trust score
      </button>
    </div>
  </div>
@endsection
