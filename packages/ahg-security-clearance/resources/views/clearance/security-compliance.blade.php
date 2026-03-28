@extends('ahg-theme-b5::layout')

@section('title', 'Security Compliance')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item active">Compliance</li>
  </ol></nav>

  <h1><i class="fas fa-clipboard-check"></i> Security Compliance</h1>

  {{-- Compliance Stats --}}
  <div class="row mb-4">
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body"><h6>Compliant Users</h6><h3>{{ $stats['compliant_users'] ?? 0 }}</h3></div></div></div>
    <div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body"><h6>Expiring Soon</h6><h3>{{ $stats['expiring_soon'] ?? 0 }}</h3></div></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body"><h6>Non-Compliant</h6><h3>{{ $stats['non_compliant'] ?? 0 }}</h3></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body"><h6>Pending Reviews</h6><h3>{{ $stats['pending_reviews'] ?? 0 }}</h3></div></div></div>
  </div>

  {{-- Recent Compliance Logs --}}
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Recent Compliance Activity</h5></div>
    <div class="card-body table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>Date</th><th>Event</th><th>User</th><th>Details</th><th>Status</th></tr></thead>
        <tbody>
          @forelse($recentLogs ?? [] as $log)
          <tr>
            <td>{{ $log->created_at ?? '' }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $log->event_type ?? '')) }}</td>
            <td>{{ e($log->username ?? '') }}</td>
            <td>{{ e($log->details ?? '') }}</td>
            <td>
              <span class="badge bg-{{ ($log->status ?? '') === 'pass' ? 'success' : (($log->status ?? '') === 'fail' ? 'danger' : 'warning') }}">
                {{ ucfirst($log->status ?? 'unknown') }}
              </span>
            </td>
          </tr>
          @empty
          <tr><td colspan="5" class="text-muted">No compliance logs.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
