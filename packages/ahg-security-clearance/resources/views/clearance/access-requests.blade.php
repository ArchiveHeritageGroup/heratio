@extends('ahg-theme-b5::layout')

@section('title', 'Access Requests')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item active">Access Requests</li>
  </ol></nav>

  <h1><i class="fas fa-inbox"></i> Pending Access Requests</h1>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  {{-- Stats --}}
  <div class="row mb-4">
    <div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body"><h6>Pending</h6><h3>{{ $stats['pending'] ?? 0 }}</h3></div></div></div>
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body"><h6>Approved Today</h6><h3>{{ $stats['approved_today'] ?? 0 }}</h3></div></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body"><h6>Denied Today</h6><h3>{{ $stats['denied_today'] ?? 0 }}</h3></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body"><h6>This Month</h6><h3>{{ $stats['total_this_month'] ?? 0 }}</h3></div></div></div>
  </div>

  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr><th>User</th><th>Object</th><th>Type</th><th>Priority</th><th>Justification</th><th>Requested</th><th>Actions</th></tr>
        </thead>
        <tbody>
          @forelse($requests ?? [] as $req)
          <tr class="{{ in_array($req->priority ?? '', ['urgent','immediate']) ? 'table-warning' : '' }}">
            <td>{{ e($req->username ?? '') }}</td>
            <td>{{ e($req->object_title ?? '-') }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $req->request_type ?? '')) }}</td>
            <td>
              <span class="badge bg-{{ ($req->priority ?? '') === 'immediate' ? 'danger' : (($req->priority ?? '') === 'urgent' ? 'warning' : 'secondary') }}">
                {{ ucfirst($req->priority ?? 'normal') }}
              </span>
            </td>
            <td>{{ \Illuminate\Support\Str::limit(e($req->justification ?? ''), 50) }}</td>
            <td>{{ isset($req->created_at) ? date('Y-m-d H:i', strtotime($req->created_at)) : '' }}</td>
            <td>
              <a href="{{ route('security-clearance.view-request', ['id' => $req->id]) }}" class="btn btn-sm btn-primary">Review</a>
              <form method="POST" action="{{ route('security-clearance.approve-request', ['id' => $req->id]) }}" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-success" onclick="return confirm('Approve this request?')"><i class="fas fa-check"></i></button>
              </form>
              <form method="POST" action="{{ route('security-clearance.deny-request', ['id' => $req->id]) }}" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-danger" onclick="return confirm('Deny this request?')"><i class="fas fa-times"></i></button>
              </form>
            </td>
          </tr>
          @empty
          <tr><td colspan="7" class="text-muted">No pending requests.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
