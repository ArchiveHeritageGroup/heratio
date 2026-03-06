@extends('theme::layouts.1col')
@section('title', 'Privacy Dashboard')

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-user-shield',
    'featureTitle' => 'Privacy Dashboard',
    'featureDescription' => 'POPIA/GDPR compliance overview',
  ])

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card bg-success text-white">
        <div class="card-body text-center">
          <h2 class="mb-0">0</h2>
          <small>PII Scans Completed</small>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card bg-warning text-dark">
        <div class="card-body text-center">
          <h2 class="mb-0">0</h2>
          <small>PII Detections</small>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card bg-info text-white">
        <div class="card-body text-center">
          <h2 class="mb-0">0</h2>
          <small>Redactions Applied</small>
        </div>
      </div>
    </div>
  </div>
@endsection
