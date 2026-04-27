{{--
  ahgRecordsManagePlugin — Dashboard

  Single landing surface for the Records Management module. Every RM tool is
  reachable from here. The cards count live data so officers see drift at a glance.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Records Management')
@section('body-class', 'admin records dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-archive me-2"></i> Records Management</h1>
  <span class="text-muted small">
    ISO 15489 / ISO 16175 / MoReq2010 / DoD 5015.2
  </span>
</div>

<p class="text-muted">
  Records lifecycle: retention schedules → file plan classification → review triggers → disposal workflow → certificate of destruction or transfer to archives.
  See <a href="{{ url('/help') }}">Help</a> for the full workflow.
</p>

<div class="row g-3">
  {{-- Retention Schedules --}}
  <div class="col-md-4">
    <a href="{{ route('records.schedules.index') }}" class="text-decoration-none">
      <div class="card h-100 border-primary">
        <div class="card-body">
          <h5 class="card-title text-primary"><i class="fas fa-clock me-1"></i> Retention Schedules</h5>
          <p class="card-text text-muted small">Authority + period + jurisdiction. Versioned, approval-gated, citable.</p>
          <div class="mt-2"><span class="badge bg-primary">{{ $stats['retention_schedules'] }}</span> schedule(s)</div>
        </div>
      </div>
    </a>
  </div>

  {{-- Disposal Classes --}}
  <div class="col-md-4">
    <a href="{{ route('records.schedules.index') }}" class="text-decoration-none">
      <div class="card h-100 border-info">
        <div class="card-body">
          <h5 class="card-title text-info"><i class="fas fa-tags me-1"></i> Disposal Classes</h5>
          <p class="card-text text-muted small">Per-schedule classes that pin retention period + trigger + final action.</p>
          <div class="mt-2"><span class="badge bg-info text-dark">{{ $stats['disposal_classes'] }}</span> active class(es)</div>
        </div>
      </div>
    </a>
  </div>

  {{-- File Plan --}}
  <div class="col-md-4">
    <a href="{{ route('records.fileplan.index') }}" class="text-decoration-none">
      <div class="card h-100 border-success">
        <div class="card-body">
          <h5 class="card-title text-success"><i class="fas fa-sitemap me-1"></i> File Plan</h5>
          <p class="card-text text-muted small">Hierarchical functional classification. CSV / spreadsheet import supported.</p>
          <div class="mt-2"><span class="badge bg-success">{{ $stats['fileplan_nodes'] }}</span> node(s)</div>
        </div>
      </div>
    </a>
  </div>

  {{-- Disposal Queue --}}
  <div class="col-md-4">
    <a href="{{ route('records.disposal.queue') }}" class="text-decoration-none">
      <div class="card h-100 border-danger">
        <div class="card-body">
          <h5 class="card-title text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Disposal Queue</h5>
          <p class="card-text text-muted small">Records awaiting a disposal decision: recommend → approve → execute.</p>
          <div class="mt-2"><span class="badge bg-danger">{{ $stats['pending_disposal'] }}</span> pending action(s)</div>
        </div>
      </div>
    </a>
  </div>

  {{-- Reviews --}}
  <div class="col-md-4">
    <a href="{{ route('records.reviews.index') }}" class="text-decoration-none">
      <div class="card h-100 border-warning">
        <div class="card-body">
          <h5 class="card-title text-warning"><i class="fas fa-eye me-1"></i> Reviews</h5>
          <p class="card-text text-muted small">Periodic review queue — when retention asks "look again before disposing".</p>
          <div class="mt-2"><span class="badge bg-warning text-dark">{{ $stats['overdue_reviews'] }}</span> overdue</div>
        </div>
      </div>
    </a>
  </div>

  {{-- Email Capture --}}
  <div class="col-md-4">
    <div class="card h-100 border-secondary">
      <div class="card-body">
        <h5 class="card-title text-secondary"><i class="fas fa-envelope me-1"></i> Email Capture <small class="text-muted">(P2.6)</small></h5>
        <p class="card-text text-muted small">Capture and classify business email as records of activity.</p>
        <div class="mt-2"><span class="badge bg-secondary">{{ $stats['captured_emails'] }}</span> captured</div>
      </div>
    </div>
  </div>

  {{-- Classification --}}
  <div class="col-md-4">
    <div class="card h-100 border-primary">
      <div class="card-body">
        <h5 class="card-title text-primary"><i class="fas fa-magic me-1"></i> Classification Rules <small class="text-muted">(P4.2)</small></h5>
        <p class="card-text text-muted small">Auto-assign records to file plan + disposal class on declare.</p>
        <div class="mt-2"><span class="badge bg-primary">{{ $stats['classified_records'] }}</span> classified record(s)</div>
      </div>
    </div>
  </div>

  {{-- Compliance --}}
  <div class="col-md-4">
    <div class="card h-100 border-dark">
      <div class="card-body">
        <h5 class="card-title text-dark"><i class="fas fa-clipboard-check me-1"></i> Compliance <small class="text-muted">(P2.8)</small></h5>
        <p class="card-text text-muted small">ISO 15489 / MoReq2010 / DoD 5015.2 self-assessment + reporting.</p>
        <div class="mt-2"><span class="badge bg-dark">{{ $stats['compliance_assessments'] }}</span> assessment(s)</div>
      </div>
    </div>
  </div>

  {{-- Vital Records (Phase 1, lives in ahg-integrity) --}}
  <div class="col-md-4">
    <a href="{{ url('/admin/integrity/vital-records') }}" class="text-decoration-none">
      <div class="card h-100 border-success">
        <div class="card-body">
          <h5 class="card-title text-success"><i class="fas fa-shield-alt me-1"></i> Vital Records</h5>
          <p class="card-text text-muted small">Records essential to organisational continuity. Off-site replicated.</p>
          <div class="mt-2"><span class="text-success small"><i class="fas fa-arrow-right me-1"></i>Open registry</span></div>
        </div>
      </div>
    </a>
  </div>
</div>

<hr class="my-4">

<h2 class="h5 mb-3">Quick links</h2>
<div class="d-flex gap-2 flex-wrap">
  <a href="{{ route('records.fileplan.import') }}" class="btn btn-sm btn-outline-success"><i class="fas fa-file-upload me-1"></i>Import file plan (CSV / spreadsheet)</a>
  <a href="{{ route('records.schedules.create') }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus me-1"></i>New retention schedule</a>
  <a href="{{ route('records.disposal.history') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-history me-1"></i>Disposal history</a>
</div>

<div class="mt-4 small text-muted">
  Status badges marked <em>(P2.4)</em>, <em>(P2.6)</em>, <em>(P2.8)</em>, <em>(P4.2)</em> mark roadmap phases.
  Database tables exist; UI/services pending. See README roadmap for full status.
</div>
@endsection
