@extends('ahg-theme-b5::layout')

@section('title', 'Security Audit Log')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.audit-dashboard') }}">Audit</a></li>
    <li class="breadcrumb-item active">Full Log</li>
  </ol></nav>

  <h1><i class="fas fa-list"></i> Security Audit Log</h1>
  <p class="text-muted">{{ $total ?? 0 }} total entries</p>

  {{-- Filters --}}
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-2">
        <div class="col-md-2">
          <label class="form-label">Action</label>
          <select name="log_action" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach($actions ?? [] as $action)
              <option value="{{ $action }}" {{ ($filters['action'] ?? '') === $action ? 'selected' : '' }}>{{ ucfirst($action) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Category</label>
          <select name="category" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach($categories ?? [] as $cat)
              <option value="{{ $cat }}" {{ ($filters['category'] ?? '') === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">User</label>
          <input type="text" name="user" class="form-control form-control-sm" value="{{ $filters['user_name'] ?? '' }}" placeholder="Username">
        </div>
        <div class="col-md-2">
          <label class="form-label">From</label>
          <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">To</label>
          <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div class="col-md-2 d-flex align-items-end gap-1">
          <button type="submit" class="btn btn-sm btn-primary">Filter</button>
          <a href="{{ route('security-clearance.audit-index') }}" class="btn btn-sm btn-secondary">Clear</a>
          <a href="{{ route('security-clearance.audit-export') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download"></i></a>
        </div>
      </form>
    </div>
  </div>

  {{-- Log Table --}}
  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-sm table-striped table-hover">
        <thead>
          <tr><th>ID</th><th>Time</th><th>Action</th><th>Category</th><th>User</th><th>Object</th><th>IP</th><th>Details</th></tr>
        </thead>
        <tbody>
          @forelse($logs ?? [] as $log)
          <tr>
            <td>{{ $log->id ?? '' }}</td>
            <td>{{ $log->created_at ?? '' }}</td>
            <td>
              <span class="badge bg-{{ in_array($log->action ?? '', ['denied','failed','revoke']) ? 'danger' : (in_array($log->action ?? '', ['download','print','export']) ? 'warning' : 'info') }}">
                {{ ucfirst($log->action ?? '') }}
              </span>
            </td>
            <td>{{ e($log->action_category ?? '') }}</td>
            <td>{{ e($log->user_name ?? '') }}</td>
            <td>{{ e($log->object_title ?? '') }}</td>
            <td><code>{{ e($log->ip_address ?? '') }}</code></td>
            <td><small>{{ e(\Illuminate\Support\Str::limit($log->details ?? '', 50)) }}</small></td>
          </tr>
          @empty
          <tr><td colspan="8" class="text-muted">No audit log entries.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Pagination --}}
  @if(($totalPages ?? 1) > 1)
  <nav class="mt-3">
    <ul class="pagination">
      @if($page > 1)
        <li class="page-item"><a class="page-link" href="?{{ http_build_query(array_merge(request()->query(), ['page' => $page - 1])) }}">Prev</a></li>
      @endif
      @for($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++)
        <li class="page-item {{ $i == $page ? 'active' : '' }}"><a class="page-link" href="?{{ http_build_query(array_merge(request()->query(), ['page' => $i])) }}">{{ $i }}</a></li>
      @endfor
      @if($page < $totalPages)
        <li class="page-item"><a class="page-link" href="?{{ http_build_query(array_merge(request()->query(), ['page' => $page + 1])) }}">Next</a></li>
      @endif
    </ul>
  </nav>
  @endif
</div>
@endsection
