@extends('ahg-theme-b5::layout')

@section('title', 'Security Dashboard')

@section('content')
<div class="container-fluid mt-3">
  <h1><i class="fas fa-shield-alt"></i> Security Dashboard</h1>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  {{-- Statistics Cards --}}
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <h5 class="card-title">Pending Requests</h5>
          <h2>{{ $statistics['pending_requests'] ?? 0 }}</h2>
          <small>Awaiting review</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-warning text-dark">
        <div class="card-body">
          <h5 class="card-title">Expiring Clearances</h5>
          <h2>{{ $statistics['expiring_clearances'] ?? 0 }}</h2>
          <small>Within 30 days</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-danger text-white">
        <div class="card-body">
          <h5 class="card-title">Recent Denials</h5>
          <h2>{{ $statistics['recent_denials'] ?? 0 }}</h2>
          <small>Last 7 days</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body">
          <h5 class="card-title">Reviews Due</h5>
          <h2>{{ $statistics['reviews_due'] ?? 0 }}</h2>
          <small>Declassifications</small>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    {{-- Clearances by Level --}}
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-users"></i> User Clearances by Level</h5>
        </div>
        <div class="card-body">
          <table class="table table-sm">
            <thead><tr><th>Level</th><th>Users</th></tr></thead>
            <tbody>
              @foreach($statistics['clearances_by_level'] ?? [] as $level)
              <tr>
                <td><span class="badge" style="background-color: {{ $level->color ?? '#666' }}">{{ e($level->name) }}</span></td>
                <td>{{ $level->count }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
          <a href="{{ route('security-clearance.index') }}" class="btn btn-sm btn-outline-primary">Manage Clearances</a>
        </div>
      </div>
    </div>

    {{-- Objects by Classification --}}
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-folder-open"></i> Objects by Classification</h5>
        </div>
        <div class="card-body">
          <table class="table table-sm">
            <thead><tr><th>Classification</th><th>Objects</th></tr></thead>
            <tbody>
              @foreach($statistics['objects_by_level'] ?? [] as $level)
              <tr>
                <td><span class="badge" style="background-color: {{ $level->color ?? '#666' }}">{{ e($level->name) }}</span></td>
                <td>{{ $level->count }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  {{-- Pending Requests --}}
  @if(!empty($pendingRequests))
  <div class="card mb-4">
    <div class="card-header bg-warning">
      <h5 class="mb-0"><i class="fas fa-clock"></i> Pending Access Requests</h5>
    </div>
    <div class="card-body">
      <table class="table table-striped">
        <thead>
          <tr><th>User</th><th>Object</th><th>Type</th><th>Priority</th><th>Requested</th><th>Actions</th></tr>
        </thead>
        <tbody>
          @foreach($pendingRequests as $req)
          <tr class="{{ in_array($req->priority ?? '', ['urgent','immediate']) ? 'table-warning' : '' }}">
            <td>{{ e($req->username ?? '') }}</td>
            <td>{{ e($req->object_title ?? '-') }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $req->request_type ?? '')) }}</td>
            <td>
              <span class="badge bg-{{ ($req->priority ?? '') === 'immediate' ? 'danger' : (($req->priority ?? '') === 'urgent' ? 'warning' : 'secondary') }}">
                {{ ucfirst($req->priority ?? 'normal') }}
              </span>
            </td>
            <td>{{ isset($req->created_at) ? date('Y-m-d H:i', strtotime($req->created_at)) : '' }}</td>
            <td>
              <a href="{{ route('security-clearance.view-request', ['id' => $req->request_id ?? $req->id]) }}" class="btn btn-sm btn-primary">Review</a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Expiring Clearances --}}
  @if(!empty($expiringClearances))
  <div class="card mb-4">
    <div class="card-header bg-warning">
      <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Expiring Clearances</h5>
    </div>
    <div class="card-body">
      <table class="table table-striped">
        <thead>
          <tr><th>User</th><th>Clearance</th><th>Expires</th><th>Days Left</th><th>Renewal Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          @foreach($expiringClearances as $exp)
          <tr class="{{ ($exp->days_remaining ?? 99) <= 7 ? 'table-danger' : (($exp->days_remaining ?? 99) <= 14 ? 'table-warning' : '') }}">
            <td>{{ e($exp->username ?? '') }}</td>
            <td><span class="badge" style="background-color: {{ $exp->color ?? '#666' }}">{{ e($exp->clearance_name ?? '') }}</span></td>
            <td>{{ $exp->expires_at ?? '' }}</td>
            <td>{{ $exp->days_remaining ?? '' }}</td>
            <td>
              @if(($exp->renewal_status ?? 'none') === 'pending')
                <span class="badge bg-info">Pending</span>
              @else
                <span class="badge bg-secondary">Not Requested</span>
              @endif
            </td>
            <td>
              <a href="{{ route('security-clearance.view', ['id' => $exp->user_id ?? 0]) }}" class="btn btn-sm btn-outline-primary">Manage</a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Due Declassifications --}}
  @if(!empty($dueDeclassifications))
  <div class="card mb-4">
    <div class="card-header bg-info text-white">
      <h5 class="mb-0"><i class="fas fa-unlock"></i> Due for Declassification</h5>
    </div>
    <div class="card-body">
      <table class="table table-striped">
        <thead><tr><th>Object</th><th>Current</th><th>Downgrade To</th><th>Scheduled Date</th></tr></thead>
        <tbody>
          @foreach($dueDeclassifications as $dec)
          <tr>
            <td>{{ e($dec->title ?? $dec->identifier ?? 'ID: ' . $dec->object_id) }}</td>
            <td>{{ e($dec->from_classification ?? '') }}</td>
            <td>{{ e($dec->to_classification ?? 'Public') }}</td>
            <td>{{ $dec->scheduled_date ?? '' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Quick Links --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-link"></i> Quick Links</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-3">
          <a href="{{ route('security-clearance.index') }}" class="btn btn-outline-primary btn-block mb-2 w-100">
            <i class="fas fa-users"></i> Manage Clearances
          </a>
        </div>
        <div class="col-md-3">
          <a href="{{ route('security-clearance.compartments') }}" class="btn btn-outline-secondary btn-block mb-2 w-100">
            <i class="fas fa-project-diagram"></i> Compartments
          </a>
        </div>
        <div class="col-md-3">
          <a href="{{ route('security-clearance.audit-dashboard') }}" class="btn btn-outline-info btn-block mb-2 w-100">
            <i class="fas fa-history"></i> Audit Log
          </a>
        </div>
        <div class="col-md-3">
          <a href="{{ route('security-clearance.report') }}" class="btn btn-outline-success btn-block mb-2 w-100">
            <i class="fas fa-chart-bar"></i> Reports
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
