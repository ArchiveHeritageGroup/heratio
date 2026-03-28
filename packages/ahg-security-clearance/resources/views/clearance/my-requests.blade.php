@extends('ahg-theme-b5::layout')

@section('title', 'My Access Requests')

@section('content')
<div class="container-fluid mt-3">
  <h1><i class="fas fa-file-alt"></i> My Access Requests</h1>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  {{-- Current Clearance --}}
  @if(!empty($currentClearance))
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-shield-alt"></i> My Clearance</h5></div>
    <div class="card-body">
      <p><strong>Level:</strong> <span class="badge" style="background-color: {{ $currentClearance->color ?? '#666' }}">{{ e($currentClearance->classification_name ?? 'None') }}</span></p>
      <p><strong>Expires:</strong> {{ $currentClearance->expires_at ?? 'Never' }}</p>
    </div>
  </div>
  @endif

  {{-- My Requests --}}
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Request History</h5></div>
    <div class="card-body table-responsive">
      <table class="table table-striped">
        <thead>
          <tr><th>Object</th><th>Type</th><th>Status</th><th>Submitted</th><th>Reviewed</th><th>Notes</th></tr>
        </thead>
        <tbody>
          @forelse($requests ?? [] as $req)
          <tr>
            <td>{{ e($req->object_title ?? 'Object #' . ($req->object_id ?? '')) }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $req->request_type ?? '')) }}</td>
            <td>
              <span class="badge bg-{{ ($req->status ?? '') === 'approved' ? 'success' : (($req->status ?? '') === 'denied' ? 'danger' : 'warning') }}">
                {{ ucfirst($req->status ?? 'pending') }}
              </span>
            </td>
            <td>{{ $req->created_at ?? '' }}</td>
            <td>{{ $req->reviewed_at ?? '—' }}</td>
            <td>{{ e($req->review_notes ?? '') }}</td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-muted">No requests submitted.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
