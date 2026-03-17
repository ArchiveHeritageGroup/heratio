@extends('ahg-theme-b5::layouts.app')

@section('title', 'User Security Clearances')

@section('content')
<div class="container-fluid py-4">

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Admin</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.groups') }}">ACL</a></li>
      <li class="breadcrumb-item active" aria-current="page">User Clearances</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-id-badge me-2"></i> User Security Clearances</h2>
    <a href="{{ route('acl.groups') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i> Back to ACL
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Set Clearance Form --}}
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i> Set User Clearance</h5>
    </div>
    <div class="card-body">
      <form action="{{ route('acl.set-clearance') }}" method="POST" class="row g-3 align-items-end">
        @csrf
        <div class="col-md-5">
          <label for="clearance_user_id" class="form-label">User</label>
          <select name="user_id" id="clearance_user_id" class="form-select" required>
            <option value="">-- Select User --</option>
            @foreach($users as $user)
              <option value="{{ $user->id }}">{{ $user->display_name ?? $user->username }} ({{ $user->username }})</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-5">
          <label for="clearance_classification_id" class="form-label">Clearance Level</label>
          <select name="classification_id" id="clearance_classification_id" class="form-select" required>
            <option value="">-- Select Level --</option>
            @foreach($classifications as $cls)
              <option value="{{ $cls->id }}">{{ $cls->name }} ({{ $cls->code }}, level {{ $cls->level }})</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-success w-100">
            <i class="fas fa-save me-1"></i> Set Clearance
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Current Clearances Table --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-list me-2"></i> Current Clearances</h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>User</th>
              <th>Clearance Level</th>
              <th>Granted By</th>
              <th>Granted At</th>
              <th>Expires At</th>
            </tr>
          </thead>
          <tbody>
            @forelse($clearances as $clr)
              <tr>
                <td><strong>{{ $clr->user_display_name ?? $clr->username ?? '—' }}</strong></td>
                <td>
                  <span class="badge" style="background-color:{{ $clr->classification_color ?? '#6c757d' }};">
                    {{ $clr->classification_name ?? '—' }}
                  </span>
                  <small class="text-muted ms-1">({{ $clr->classification_code ?? '' }})</small>
                </td>
                <td>{{ $clr->granted_by_name ?? '—' }}</td>
                <td>{{ $clr->granted_at ?? '—' }}</td>
                <td>
                  @if($clr->expires_at)
                    @php
                      $expired = \Carbon\Carbon::parse($clr->expires_at)->isPast();
                    @endphp
                    <span class="{{ $expired ? 'text-danger fw-bold' : '' }}">
                      {{ $clr->expires_at }}
                      @if($expired)
                        <i class="fas fa-exclamation-triangle ms-1" title="Expired"></i>
                      @endif
                    </span>
                  @else
                    <span class="text-muted">No expiry</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted py-4">No user clearances assigned.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
