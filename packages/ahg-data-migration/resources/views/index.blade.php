@extends('theme::layouts.1col')

@section('title', 'Data Migration')
@section('body-class', 'admin data-migration')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-exchange-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Data Migration</h1>
      <span class="small text-muted">Import, export, and manage field mappings</span>
    </div>
  </div>

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('data-migration.upload') }}" class="btn btn-primary">
      <i class="fas fa-upload"></i> New Import
    </a>
    <a href="{{ route('data-migration.batch-export') }}" class="btn btn-outline-secondary">
      <i class="fas fa-download"></i> Batch Export
    </a>
    <a href="{{ route('data-migration.jobs') }}" class="btn btn-outline-info">
      <i class="fas fa-tasks"></i> All Jobs
    </a>
  </div>

  <div class="row">
    {{-- Saved Mappings --}}
    <div class="col-lg-6 mb-4">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-map-signs"></i> Saved Mappings</h5>
          <span class="badge bg-secondary">{{ count($mappings) }}</span>
        </div>
        <div class="card-body p-0">
          @if(count($mappings) > 0)
            <div class="table-responsive">
              <table class="table table-bordered table-striped mb-0">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Target Type</th>
                    <th>Category</th>
                    <th>Updated</th>
                    <th style="width: 80px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($mappings as $mapping)
                    <tr>
                      <td>{{ $mapping['name'] }}</td>
                      <td>
                        <span class="badge bg-info">{{ $mapping['target_type'] }}</span>
                      </td>
                      <td>{{ $mapping['category'] ?? 'Custom' }}</td>
                      <td>{{ $mapping['updated_at'] ? \Carbon\Carbon::parse($mapping['updated_at'])->format('Y-m-d H:i') : '' }}</td>
                      <td>
                        <form method="POST" action="{{ route('data-migration.delete-mapping', $mapping['id']) }}" class="d-inline" onsubmit="return confirm('Delete this mapping?')">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                            <i class="fas fa-trash"></i>
                          </button>
                        </form>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="p-3 text-muted text-center">
              <i class="fas fa-info-circle"></i> No saved mappings yet. Create one during import.
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Recent Jobs --}}
    <div class="col-lg-6 mb-4">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-history"></i> Recent Jobs</h5>
          <a href="{{ route('data-migration.jobs') }}" class="btn btn-sm btn-outline-secondary">View All</a>
        </div>
        <div class="card-body p-0">
          @if(count($jobs) > 0)
            <div class="table-responsive">
              <table class="table table-bordered table-striped mb-0">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Records</th>
                    <th>Created</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($jobs as $job)
                    <tr>
                      <td>
                        <a href="{{ route('data-migration.job', $job['id']) }}">
                          {{ $job['name'] ?: 'Job #' . $job['id'] }}
                        </a>
                      </td>
                      <td>
                        @if($job['status'] === 'completed')
                          <span class="badge bg-success">Completed</span>
                        @elseif($job['status'] === 'failed')
                          <span class="badge bg-danger">Failed</span>
                        @elseif($job['status'] === 'processing')
                          <span class="badge bg-info">Processing</span>
                        @else
                          <span class="badge bg-warning text-dark">Pending</span>
                        @endif
                      </td>
                      <td>
                        @php
                          $pct = $job['total_records'] > 0 ? min(100, round(($job['processed_records'] / $job['total_records']) * 100)) : 0;
                        @endphp
                        <div class="progress" style="height: 18px; min-width: 60px;">
                          <div class="progress-bar" role="progressbar" style="width: {{ $pct }}%">{{ $pct }}%</div>
                        </div>
                      </td>
                      <td>{{ number_format($job['processed_records']) }} / {{ number_format($job['total_records']) }}</td>
                      <td>{{ $job['created_at'] ? \Carbon\Carbon::parse($job['created_at'])->format('Y-m-d H:i') : '' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="p-3 text-muted text-center">
              <i class="fas fa-info-circle"></i> No migration jobs yet.
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
