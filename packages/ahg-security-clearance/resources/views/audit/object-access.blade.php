@extends('ahg-theme-b5::layout')

@section('title', 'Object Access Audit')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.audit-dashboard') }}">Audit</a></li>
    <li class="breadcrumb-item active">Object Access</li>
  </ol></nav>

  <h1><i class="fas fa-folder-open"></i> Object Access Audit</h1>

  {{-- Object Search --}}
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-2">
        <div class="col-md-4">
          <label class="form-label">Object ID</label>
          <input type="number" name="object_id" class="form-control" value="{{ request('object_id') }}" placeholder="Enter object ID" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Period</label>
          <select name="period" class="form-select">
            <option value="7 days" {{ ($period ?? '') === '7 days' ? 'selected' : '' }}>Last 7 days</option>
            <option value="30 days" {{ ($period ?? '') === '30 days' ? 'selected' : '' }}>Last 30 days</option>
            <option value="90 days" {{ ($period ?? '') === '90 days' ? 'selected' : '' }}>Last 90 days</option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-primary">Search</button>
        </div>
      </form>
    </div>
  </div>

  @if($object)
  {{-- Object Info --}}
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Object Information</h5></div>
    <div class="card-body">
      <table class="table table-borderless mb-0">
        <tr><th width="20%">Title</th><td>{{ e($object->title ?? 'Untitled') }}</td></tr>
        <tr><th>Identifier</th><td>{{ e($object->identifier ?? '') }}</td></tr>
        <tr><th>ID</th><td>{{ $object->id }}</td></tr>
        <tr><th>Total Accesses</th><td><strong>{{ $totalAccess }}</strong></td></tr>
      </table>
    </div>
  </div>

  {{-- Daily Access Chart --}}
  @if(count($dailyAccess))
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Daily Access Timeline</h5></div>
    <div class="card-body table-responsive">
      <table class="table table-sm">
        <thead><tr><th>Date</th><th>Accesses</th><th></th></tr></thead>
        <tbody>
          @foreach($dailyAccess as $day)
          <tr>
            <td>{{ $day->date }}</td>
            <td>{{ $day->count }}</td>
            <td>
              <div class="progress" style="height: 20px;">
                <div class="progress-bar bg-primary" style="width: {{ min(100, ($day->count / max(1, $totalAccess)) * 200) }}%">{{ $day->count }}</div>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Access Logs --}}
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Access Log</h5></div>
    <div class="card-body table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>Date</th><th>User</th><th>IP</th><th>Type</th></tr></thead>
        <tbody>
          @forelse($accessLogs as $log)
          <tr>
            <td>{{ $log->access_date ?? '' }}</td>
            <td>{{ e($log->user_name ?? $log->user_id ?? '') }}</td>
            <td><code>{{ e($log->ip_address ?? '') }}</code></td>
            <td>{{ e($log->access_type ?? 'view') }}</td>
          </tr>
          @empty
          <tr><td colspan="4" class="text-muted">No access logs.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Security Logs --}}
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Security Events</h5></div>
    <div class="card-body table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>Time</th><th>Action</th><th>User</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
          @forelse($securityLogs as $log)
          <tr>
            <td>{{ $log->created_at ?? '' }}</td>
            <td>
              <span class="badge bg-{{ in_array($log->action ?? '', ['denied','failed']) ? 'danger' : 'info' }}">
                {{ ucfirst($log->action ?? '') }}
              </span>
            </td>
            <td>{{ e($log->user_name ?? '') }}</td>
            <td>{{ e($log->details ?? '') }}</td>
            <td><code>{{ e($log->ip_address ?? '') }}</code></td>
          </tr>
          @empty
          <tr><td colspan="5" class="text-muted">No security events.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @elseif(request('object_id'))
    <div class="alert alert-warning">Object not found.</div>
  @endif
</div>
@endsection
