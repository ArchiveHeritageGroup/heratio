@extends('ahg-theme-b5::layout')

@section('title', 'Security Audit Dashboard')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item active">Audit Dashboard</li>
  </ol></nav>

  <h1><i class="fas fa-history"></i> Security Audit Dashboard</h1>

  {{-- Period Filter --}}
  <form method="GET" class="mb-3">
    <div class="row">
      <div class="col-md-3">
        <select name="period" class="form-select" onchange="this.form.submit()">
          <option value="7 days" {{ ($period ?? '') === '7 days' ? 'selected' : '' }}>Last 7 days</option>
          <option value="30 days" {{ ($period ?? '') === '30 days' ? 'selected' : '' }}>Last 30 days</option>
          <option value="90 days" {{ ($period ?? '') === '90 days' ? 'selected' : '' }}>Last 90 days</option>
        </select>
      </div>
      <div class="col-md-4">
        <small class="text-muted">Since: {{ $stats['since'] ?? '' }}</small>
      </div>
    </div>
  </form>

  {{-- Activity Stats --}}
  <div class="row mb-4">
    <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body"><h6>Total Events</h6><h3>{{ $stats['total_events'] ?? 0 }}</h3></div></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body"><h6>Security Events</h6><h3>{{ $stats['security_events'] ?? 0 }}</h3></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body"><h6>Users Active</h6><h3>{{ count($stats['by_user'] ?? []) }}</h3></div></div></div>
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body"><h6>Action Types</h6><h3>{{ count($stats['by_action'] ?? []) }}</h3></div></div></div>
  </div>

  <div class="row">
    {{-- Top Users --}}
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Most Active Users</h5></div>
        <div class="card-body table-responsive">
          <table class="table table-sm">
            <thead><tr><th>User</th><th>Events</th></tr></thead>
            <tbody>
              @forelse($stats['by_user'] ?? [] as $user)
              <tr>
                <td>{{ e($user->username ?? '') }}</td>
                <td>{{ $user->count ?? 0 }}</td>
              </tr>
              @empty
              <tr><td colspan="2" class="text-muted">No data.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Actions Breakdown --}}
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Actions Breakdown</h5></div>
        <div class="card-body table-responsive">
          <table class="table table-sm">
            <thead><tr><th>Action</th><th>Count</th></tr></thead>
            <tbody>
              @forelse($stats['by_action'] ?? [] as $action)
              <tr>
                <td>
                  <span class="badge bg-{{ in_array($action->action ?? '', ['denied','failed']) ? 'danger' : 'info' }}">
                    {{ ucfirst($action->action ?? '') }}
                  </span>
                </td>
                <td>{{ $action->count ?? 0 }}</td>
              </tr>
              @empty
              <tr><td colspan="2" class="text-muted">No data.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  {{-- Daily Activity --}}
  @if(count($stats['by_day'] ?? []))
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Daily Activity</h5></div>
    <div class="card-body table-responsive">
      <table class="table table-sm">
        <thead><tr><th>Date</th><th>Events</th><th></th></tr></thead>
        <tbody>
          @foreach($stats['by_day'] as $day)
          <tr>
            <td>{{ $day->date }}</td>
            <td>{{ $day->count }}</td>
            <td>
              <div class="progress" style="height: 20px;">
                <div class="progress-bar bg-primary" style="width: {{ min(100, ($day->count / max(1, $stats['total_events'])) * 500) }}%">{{ $day->count }}</div>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Quick Links --}}
  <div class="d-flex gap-2">
    <a href="{{ route('security-clearance.audit-index') }}" class="btn btn-outline-primary"><i class="fas fa-list"></i> Full Log</a>
    <a href="{{ route('security-clearance.audit-export') }}" class="btn btn-outline-secondary"><i class="fas fa-download"></i> Export CSV</a>
    <a href="{{ route('security-clearance.audit-object-access') }}" class="btn btn-outline-info"><i class="fas fa-folder-open"></i> Object Access</a>
  </div>
</div>
@endsection
