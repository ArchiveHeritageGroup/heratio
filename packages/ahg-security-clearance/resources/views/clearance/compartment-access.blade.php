@extends('ahg-theme-b5::layout')

@section('title', 'Compartment Access')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.compartments') }}">Compartments</a></li>
    <li class="breadcrumb-item active">Access Grants</li>
  </ol></nav>

  <h1><i class="fas fa-key"></i> Compartment Access Grants</h1>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr><th>User</th><th>Compartment</th><th>Granted By</th><th>Granted At</th><th>Expires</th><th>Actions</th></tr>
        </thead>
        <tbody>
          @forelse($grants ?? [] as $grant)
          <tr>
            <td>{{ e($grant->username ?? '') }}</td>
            <td><code>{{ e($grant->compartment_name ?? '') }}</code></td>
            <td>{{ e($grant->granted_by_name ?? '') }}</td>
            <td>{{ $grant->granted_at ?? '' }}</td>
            <td>{{ $grant->expires_at ?? 'Never' }}</td>
            <td>
              <a href="{{ route('security-clearance.view', ['id' => $grant->user_id ?? 0]) }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-user"></i> View User
              </a>
            </td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-muted">No compartment access grants.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
