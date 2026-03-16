@extends('ahg-theme-b5::layouts.app')
@section('title', 'Privacy Dashboard')

@section('content')
<div class="container-fluid py-4">

  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-user-shield',
    'featureTitle' => 'Privacy Dashboard',
    'featureDescription' => 'POPIA/GDPR compliance overview',
  ])

  @php
    $s = $stats ?? new \stdClass();
  @endphp

  {{-- PII & Redaction Row --}}
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body text-center">
          <h2 class="mb-0">{{ $s->pii_scans_completed ?? 0 }}</h2>
          <small>PII Scans Completed</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-warning text-dark">
        <div class="card-body text-center">
          <h2 class="mb-0">{{ $s->pii_detections ?? 0 }}</h2>
          <small>PII Detections</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body text-center">
          <h2 class="mb-0">{{ $s->redaction_applied ?? 0 }}</h2>
          <small>Redactions Applied</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-secondary text-white">
        <div class="card-body text-center">
          <h2 class="mb-0">{{ $s->redaction_pending ?? 0 }}</h2>
          <small>Redactions Pending</small>
        </div>
      </div>
    </div>
  </div>

  {{-- DSAR Row --}}
  <h5 class="mb-3"><i class="fas fa-file-alt me-2"></i>Data Subject Access Requests (DSARs)</h5>
  <div class="row g-3 mb-4">
    <div class="col-md-2">
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
          <h3 class="mb-0 text-primary">{{ $s->dsar_total ?? 0 }}</h3>
          <small class="text-muted">Total</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
          <h3 class="mb-0 text-warning">{{ $s->dsar_pending ?? 0 }}</h3>
          <small class="text-muted">Pending</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
          <h3 class="mb-0 text-info">{{ $s->dsar_in_progress ?? 0 }}</h3>
          <small class="text-muted">In Progress</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
          <h3 class="mb-0 text-success">{{ $s->dsar_completed ?? 0 }}</h3>
          <small class="text-muted">Completed</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
          <h3 class="mb-0 text-danger">{{ $s->dsar_overdue ?? 0 }}</h3>
          <small class="text-muted">Overdue</small>
        </div>
      </div>
    </div>
  </div>

  {{-- Breaches & Processing Activities --}}
  <div class="row g-4 mb-4">
    <div class="col-md-6">
      <h5 class="mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Data Breaches</h5>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <h3 class="mb-0 text-primary">{{ $s->breach_total ?? 0 }}</h3>
              <small class="text-muted">Total</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <h3 class="mb-0 text-warning">{{ $s->breach_open ?? 0 }}</h3>
              <small class="text-muted">Open</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <h3 class="mb-0 text-danger">{{ $s->breach_critical ?? 0 }}</h3>
              <small class="text-muted">Critical</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <h5 class="mb-3"><i class="fas fa-clipboard-list me-2"></i>Processing Activities (ROPA)</h5>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <h3 class="mb-0 text-primary">{{ $s->processing_total ?? 0 }}</h3>
              <small class="text-muted">Total</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <h3 class="mb-0 text-success">{{ $s->processing_active ?? 0 }}</h3>
              <small class="text-muted">Active</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <h3 class="mb-0 text-warning">{{ $s->processing_review_due ?? 0 }}</h3>
              <small class="text-muted">Review Due</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
