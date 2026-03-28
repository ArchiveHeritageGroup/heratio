@extends('ahg-theme-b5::layout')

@section('title', 'Security Reports')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item active">Reports</li>
  </ol></nav>

  <h1><i class="fas fa-chart-bar"></i> Security Reports</h1>

  {{-- Period Filter --}}
  <form method="GET" class="mb-4">
    <div class="row">
      <div class="col-md-3">
        <select name="period" class="form-select" onchange="this.form.submit()">
          <option value="7 days" {{ ($period ?? '') === '7 days' ? 'selected' : '' }}>Last 7 days</option>
          <option value="30 days" {{ ($period ?? '') === '30 days' ? 'selected' : '' }}>Last 30 days</option>
          <option value="90 days" {{ ($period ?? '') === '90 days' ? 'selected' : '' }}>Last 90 days</option>
          <option value="365 days" {{ ($period ?? '') === '365 days' ? 'selected' : '' }}>Last year</option>
        </select>
      </div>
    </div>
  </form>

  <div class="row mb-4">
    <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body"><h6>Total Clearances</h6><h3>{{ $total_clearances ?? 0 }}</h3></div></div></div>
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body"><h6>Grants (period)</h6><h3>{{ $grants_in_period ?? 0 }}</h3></div></div></div>
    <div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body"><h6>Revocations</h6><h3>{{ $revocations_in_period ?? 0 }}</h3></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body"><h6>Access Requests</h6><h3>{{ $requests_in_period ?? 0 }}</h3></div></div></div>
  </div>

  <div class="row">
    {{-- Clearances by Level --}}
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Clearances by Level</h5></div>
        <div class="card-body">
          <table class="table table-sm">
            <thead><tr><th>Level</th><th>Users</th><th>%</th></tr></thead>
            <tbody>
              @foreach($clearances_by_level ?? [] as $level)
              <tr>
                <td><span class="badge" style="background-color: {{ $level->color ?? '#666' }}">{{ e($level->name) }}</span></td>
                <td>{{ $level->count }}</td>
                <td>{{ $total_clearances ? round($level->count / $total_clearances * 100, 1) : 0 }}%</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Classified Objects by Level --}}
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Classified Objects by Level</h5></div>
        <div class="card-body">
          <table class="table table-sm">
            <thead><tr><th>Level</th><th>Objects</th></tr></thead>
            <tbody>
              @foreach($objects_by_level ?? [] as $level)
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

  {{-- Recent Activity --}}
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Recent Activity</h5></div>
    <div class="card-body table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>Date</th><th>Action</th><th>User</th><th>Target</th><th>Details</th></tr></thead>
        <tbody>
          @forelse($recent_activity ?? [] as $activity)
          <tr>
            <td>{{ $activity->created_at ?? '' }}</td>
            <td><span class="badge bg-{{ ($activity->action ?? '') === 'grant' ? 'success' : (($activity->action ?? '') === 'revoke' ? 'danger' : 'info') }}">{{ ucfirst($activity->action ?? '') }}</span></td>
            <td>{{ e($activity->performed_by_name ?? '') }}</td>
            <td>{{ e($activity->target_name ?? '') }}</td>
            <td>{{ e($activity->notes ?? '') }}</td>
          </tr>
          @empty
          <tr><td colspan="5" class="text-muted">No activity in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
