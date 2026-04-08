@extends('theme::layouts.1col')
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

  {{-- Public Privacy Information --}}
  <div class="row mt-4">
    <div class="col-lg-8">
      {{-- Your Rights --}}
      <div class="card mb-4">
        <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-balance-scale me-2"></i>Your Privacy Rights</h5></div>
        <div class="card-body">
          <p>Under applicable data protection laws, you have the following rights regarding your personal information:</p>
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><i class="fas fa-eye text-primary me-2"></i><strong>Right of Access</strong> — Request access to your personal information</li>
            <li class="list-group-item"><i class="fas fa-edit text-primary me-2"></i><strong>Right to Rectification</strong> — Request correction of inaccurate information</li>
            <li class="list-group-item"><i class="fas fa-trash text-primary me-2"></i><strong>Right to Erasure</strong> — Request deletion of your personal information</li>
            <li class="list-group-item"><i class="fas fa-hand-paper text-primary me-2"></i><strong>Right to Object</strong> — Object to processing of your information</li>
            <li class="list-group-item"><i class="fas fa-exchange-alt text-primary me-2"></i><strong>Right to Portability</strong> — Receive your data in a portable format</li>
          </ul>
        </div>
      </div>

      {{-- How We Use Data --}}
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-database me-2"></i>How We Process Your Data</h5></div>
        <div class="card-body">
          <p>We collect and process personal information for the following purposes:</p>
          <ul>
            <li>Providing access to archival records and research services</li>
            <li>Processing research requests and reading room bookings</li>
            <li>Managing donor agreements and access restrictions</li>
            <li>Compliance with legal and regulatory requirements</li>
            <li>Improving our services and user experience</li>
          </ul>
          <p class="mb-0">We process your data in accordance with applicable data protection laws including POPIA, NDPA, Kenya DPA, and GDPR where applicable.</p>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      {{-- Take Action --}}
      <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Take Action</h5></div>
        <div class="card-body d-grid gap-3">
          <a href="{{ url('/privacy/dsar-request') }}" class="btn btn-primary btn-lg"><i class="fas fa-file-alt me-2"></i>Submit Data Request</a>
          <a href="{{ url('/privacy/complaint') }}" class="btn btn-warning btn-lg"><i class="fas fa-exclamation-circle me-2"></i>Lodge Complaint</a>
          <a href="{{ url('/privacy/dsar-status') }}" class="btn btn-outline-secondary btn-lg"><i class="fas fa-search me-2"></i>Check Request Status</a>
        </div>
      </div>

      {{-- Request Types --}}
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-list me-2"></i>Request Types</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between align-items-center">Access Request <span class="badge bg-primary rounded-pill">DSAR</span></li>
          <li class="list-group-item d-flex justify-content-between align-items-center">Correction Request <span class="badge bg-info rounded-pill">DSAR</span></li>
          <li class="list-group-item d-flex justify-content-between align-items-center">Deletion Request <span class="badge bg-danger rounded-pill">DSAR</span></li>
          <li class="list-group-item d-flex justify-content-between align-items-center">Privacy Complaint <span class="badge bg-warning text-dark rounded-pill">Complaint</span></li>
        </ul>
      </div>

      {{-- Supported Jurisdictions --}}
      <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-globe me-2"></i>Supported Jurisdictions</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">POPIA (South Africa)</li>
          <li class="list-group-item">NDPA (Nigeria)</li>
          <li class="list-group-item">Kenya DPA</li>
          <li class="list-group-item">GDPR (European Union)</li>
          <li class="list-group-item">PIPEDA (Canada)</li>
          <li class="list-group-item">CCPA (California)</li>
        </ul>
      </div>
    </div>
  </div>

</div>
@endsection
