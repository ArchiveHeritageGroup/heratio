@extends('theme::layouts.2col')

@section('title', 'Rights Management Dashboard')
@section('body-class', 'admin rights-admin')

@section('sidebar')
  @include('ahg-extended-rights::admin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0"><i class="fas fa-gavel me-2"></i>Rights Management Dashboard</h1>
@endsection

@section('content')
  {{-- Stats Cards --}}
  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="card bg-primary text-white h-100">
        <div class="card-body">
          <h6 class="card-title">Total Rights Records</h6>
          <h2 class="mb-0">{{ number_format($stats['total_rights_records'] ?? 0) }}</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card bg-warning text-dark h-100">
        <div class="card-body">
          <h6 class="card-title">Active Embargoes</h6>
          <h2 class="mb-0">{{ number_format($stats['active_embargoes'] ?? 0) }}</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card bg-danger text-white h-100">
        <div class="card-body">
          <h6 class="card-title">Expiring Soon (30 days)</h6>
          <h2 class="mb-0">{{ number_format($stats['expiring_soon'] ?? 0) }}</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card bg-info text-white h-100">
        <div class="card-body">
          <h6 class="card-title">TK Label Assignments</h6>
          <h2 class="mb-0">{{ number_format($stats['tk_label_assignments'] ?? 0) }}</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    {{-- Expiring Embargoes Alert --}}
    @if(isset($expiringEmbargoes) && count($expiringEmbargoes) > 0)
    <div class="col-lg-6 mb-4">
      <div class="card border-warning">
        <div class="card-header bg-warning text-dark">
          <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Embargoes Expiring Soon</h5>
        </div>
        <div class="card-body p-0">
          <table class="table table-sm table-hover mb-0">
            <thead>
              <tr><th>Object</th><th>Expires</th><th>Action</th></tr>
            </thead>
            <tbody>
              @foreach($expiringEmbargoes as $embargo)
              <tr>
                <td>
                  <a href="{{ $embargo->slug ? url($embargo->slug) : '#' }}">
                    {{ $embargo->object_title ?: 'ID: ' . $embargo->object_id }}
                  </a>
                </td>
                <td>
                  <span class="badge bg-warning text-dark">
                    {{ \Carbon\Carbon::parse($embargo->end_date)->format('d M Y') }}
                  </span>
                </td>
                <td>
                  <a href="{{ route('ext-rights-admin.embargo-edit', $embargo->id) }}" class="btn btn-sm btn-outline-primary">Review</a>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
    @endif

    {{-- Review Due --}}
    @if(isset($reviewDue) && count($reviewDue) > 0)
    <div class="col-lg-6 mb-4">
      <div class="card border-info">
        <div class="card-header bg-info text-white">
          <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Reviews Due</h5>
        </div>
        <div class="card-body p-0">
          <table class="table table-sm table-hover mb-0">
            <thead>
              <tr><th>Object</th><th>Review Date</th><th>Action</th></tr>
            </thead>
            <tbody>
              @foreach($reviewDue as $embargo)
              <tr>
                <td>
                  <a href="{{ $embargo->slug ? url($embargo->slug) : '#' }}">
                    {{ $embargo->object_title ?: 'ID: ' . $embargo->object_id }}
                  </a>
                </td>
                <td>{{ \Carbon\Carbon::parse($embargo->review_date)->format('d M Y') }}</td>
                <td>
                  <a href="{{ route('ext-rights-admin.embargo-edit', $embargo->id) }}" class="btn btn-sm btn-outline-info">Review</a>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
    @endif
  </div>

  {{-- Rights by Basis --}}
  <div class="row">
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Rights by Basis</h5>
        </div>
        <div class="card-body">
          @if(!empty($stats['by_basis']))
            <ul class="list-group list-group-flush">
              @foreach($stats['by_basis'] as $basis => $count)
              <li class="list-group-item d-flex justify-content-between align-items-center">
                {{ ucfirst($basis) }}
                <span class="badge bg-primary rounded-pill">{{ $count }}</span>
              </li>
              @endforeach
            </ul>
          @else
            <p class="text-muted">No rights records yet.</p>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Rights Statements Used</h5>
        </div>
        <div class="card-body">
          @if(!empty($stats['by_rights_statement']))
            <ul class="list-group list-group-flush">
              @foreach($stats['by_rights_statement'] as $code => $count)
              <li class="list-group-item d-flex justify-content-between align-items-center">
                {{ $code }}
                <span class="badge bg-primary rounded-pill">{{ $count }}</span>
              </li>
              @endforeach
            </ul>
          @else
            <p class="text-muted">No rights statements assigned yet.</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Quick Actions --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Quick Actions</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4 mb-2">
          <a href="{{ route('ext-rights-admin.embargo-new') }}" class="btn btn-outline-primary w-100">
            <i class="fas fa-plus me-2"></i>Create New Embargo
          </a>
        </div>
        <div class="col-md-4 mb-2">
          <a href="{{ route('ext-rights-admin.orphan-work-new') }}" class="btn btn-outline-info w-100">
            <i class="fas fa-search me-2"></i>Start Orphan Work Search
          </a>
        </div>
        <div class="col-md-4 mb-2">
          <a href="{{ route('ext-rights-admin.process-expired') }}" class="btn btn-outline-warning w-100"
             onclick="return confirm('Process all expired embargoes?');">
            <i class="fas fa-clock me-2"></i>Process Expired Embargoes
          </a>
        </div>
      </div>
    </div>
  </div>
@endsection
