@extends('theme::layouts.1col')
@section('title', 'Spectrum Data — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-chart-bar',
    'featureTitle' => 'Spectrum 5.1 Data',
    'featureDescription' => 'Museum procedures — acquisition, loans, movement, conservation, valuation',
  ])

  <div class="alert alert-info">
    <i class="fas fa-info-circle me-1"></i> No Spectrum procedure data recorded for this description.
  </div>
@endsection
