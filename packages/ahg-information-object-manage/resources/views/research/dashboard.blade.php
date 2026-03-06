@extends('theme::layouts.1col')
@section('title', 'Research Dashboard')

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-graduation-cap',
    'featureTitle' => 'Research Dashboard',
    'featureDescription' => 'Research projects, annotations, citations, and collaboration',
  ])

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body text-center">
          <h2 class="mb-0">0</h2>
          <small>Projects</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body text-center">
          <h2 class="mb-0">0</h2>
          <small>Annotations</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body text-center">
          <h2 class="mb-0">0</h2>
          <small>Citations</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-secondary text-white">
        <div class="card-body text-center">
          <h2 class="mb-0">0</h2>
          <small>Saved Searches</small>
        </div>
      </div>
    </div>
  </div>
@endsection
