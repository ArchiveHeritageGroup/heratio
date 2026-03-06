@extends('theme::layouts.1col')

@section('title', 'Reports')
@section('body-class', 'admin reports')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-chart-bar me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Reports</h1>
      <span class="small text-muted">Dashboard overview</span>
    </div>
  </div>

  {{-- Stats cards --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-primary">{{ number_format($stats['descriptions']) }}</div>
          <div class="small text-muted">Descriptions</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-success">{{ number_format($stats['authorities']) }}</div>
          <div class="small text-muted">Authority records</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-info">{{ number_format($stats['repositories']) }}</div>
          <div class="small text-muted">Repositories</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-warning">{{ number_format($stats['accessions']) }}</div>
          <div class="small text-muted">Accessions</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-danger">{{ number_format($stats['digital_objects']) }}</div>
          <div class="small text-muted">Digital objects</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-secondary">{{ number_format($stats['users']) }}</div>
          <div class="small text-muted">Users</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Recent activity --}}
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h5 class="mb-0">Recent activity</h5>
      @if(Route::has('audit.browse'))
        <a href="{{ route('audit.browse') }}" class="btn btn-sm btn-outline-secondary">
          View all <i class="fas fa-arrow-right ms-1"></i>
        </a>
      @endif
    </div>
    <div class="card-body p-0">
      @if(count($recentActivity))
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr>
              <th>Date</th>
              <th>User</th>
              <th>Action</th>
              @if($auditTable === 'ahg_audit_log')
                <th>Entity type</th>
                <th>Entity title</th>
              @else
                <th>Table</th>
                <th>Record ID</th>
              @endif
            </tr>
          </thead>
          <tbody>
            @foreach($recentActivity as $entry)
              <tr>
                <td>{{ !empty($entry['created_at']) ? \Carbon\Carbon::parse($entry['created_at'])->format('Y-m-d H:i') : '' }}</td>
                <td>{{ $entry['username'] ?? '' }}</td>
                <td>
                  @php
                    $actionVal = $entry['action'] ?? '';
                    $badgeClass = match($actionVal) {
                      'create' => 'bg-success',
                      'update' => 'bg-primary',
                      'delete' => 'bg-danger',
                      default => 'bg-secondary',
                    };
                  @endphp
                  <span class="badge {{ $badgeClass }}">{{ $actionVal }}</span>
                </td>
                @if($auditTable === 'ahg_audit_log')
                  <td>{{ $entry['entity_type'] ?? '' }}</td>
                  <td>{{ $entry['entity_title'] ?? '' }}</td>
                @else
                  <td>{{ $entry['table_name'] ?? '' }}</td>
                  <td>{{ $entry['record_id'] ?? '' }}</td>
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <div class="p-3 text-muted">No recent activity found.</div>
      @endif
    </div>
  </div>
@endsection
