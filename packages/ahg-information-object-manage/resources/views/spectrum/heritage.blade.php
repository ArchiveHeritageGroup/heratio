@extends('theme::layouts.1col')
@section('title', 'Heritage Assets — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-landmark',
    'featureTitle' => 'Heritage Asset Accounting',
    'featureDescription' => 'Multi-regional heritage asset financial accounting (IPSAS, GRAP, FRS, GASB)',
  ])

  <div class="alert alert-info">
    <i class="fas fa-info-circle me-1"></i> No heritage asset data recorded for this description.
  </div>
@endsection
