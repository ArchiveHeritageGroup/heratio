@extends('theme::layouts.1col')

@section('title', 'Job #' . $job['id'] . ' - Data Migration')
@section('body-class', 'admin data-migration job-status')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-clipboard-check me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ $job['name'] ?: 'Job #' . $job['id'] }}</h1>
      <span class="small text-muted">Migration Job Details</span>
    </div>
  </div>

  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('data-migration.index') }}">Data Migration</a></li>
      <li class="breadcrumb-item"><a href="{{ route('data-migration.jobs') }}">Jobs</a></li>
      <li class="breadcrumb-item active">Job #{{ $job['id'] }}</li>
    </ol>
  </nav>

  {{-- Status and Progress --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Progress</h5>
      @if($job['status'] === 'completed')
        <span class="badge bg-success fs-6">Completed</span>
      @elseif($job['status'] === 'failed')
        <span class="badge bg-danger fs-6">Failed</span>
      @elseif($job['status'] === 'processing')
        <span class="badge bg-info fs-6">Processing</span>
      @else
        <span class="badge bg-warning text-dark fs-6">Pending</span>
      @endif
    </div>
    <div class="card-body">
      <div class="progress mb-3" style="height: 30px;">
        <div class="progress-bar {{ $job['status'] === 'failed' ? 'bg-danger' : ($job['status'] === 'completed' ? 'bg-success' : 'bg-info progress-bar-striped progress-bar-animated') }}"
             role="progressbar" style="width: {{ $progressPercent }}%"
             aria-valuenow="{{ $progressPercent }}" aria-valuemin="0" aria-valuemax="100">
          {{ $progressPercent }}%
        </div>
      </div>

      @if($job['progress_message'])
        <p class="text-muted mb-3"><i class="fas fa-comment"></i> {{ $job['progress_message'] }}</p>
      @endif

      <div class="row g-3">
        <div class="col-6 col-md-2">
          <div class="text-center">
            <div class="fs-4 fw-bold">{{ number_format($job['total_records']) }}</div>
            <div class="small text-muted">Total Records</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="text-center">
            <div class="fs-4 fw-bold">{{ number_format($job['processed_records']) }}</div>
            <div class="small text-muted">Processed</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="text-center">
            <div class="fs-4 fw-bold text-success">{{ number_format($job['imported_records']) }}</div>
            <div class="small text-muted">Imported</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="text-center">
            <div class="fs-4 fw-bold text-primary">{{ number_format($job['updated_records']) }}</div>
            <div class="small text-muted">Updated</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="text-center">
            <div class="fs-4 fw-bold text-warning">{{ number_format($job['skipped_records']) }}</div>
            <div class="small text-muted">Skipped</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="text-center">
            <div class="fs-4 fw-bold text-danger">{{ number_format($job['error_count']) }}</div>
            <div class="small text-muted">Errors</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Job Details --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-info-circle"></i> Job Details</h5>
    </div>
    <div class="card-body">
      <table class="table table-bordered mb-0">
        <tbody>
          <tr>
            <th style="width: 200px;">{{ __('Job ID') }}</th>
            <td>{{ $job['id'] }}</td>
          </tr>
          <tr>
            <th>{{ __('Target Type') }}</th>
            <td><span class="badge bg-info">{{ $job['target_type'] }}</span></td>
          </tr>
          <tr>
            <th>{{ __('Source File') }}</th>
            <td><code>{{ $job['source_file'] ?: 'N/A' }}</code></td>
          </tr>
          <tr>
            <th>{{ __('Source Format') }}</th>
            <td>{{ strtoupper($job['source_format'] ?? 'CSV') }}</td>
          </tr>
          <tr>
            <th>{{ __('Started') }}</th>
            <td>{{ $job['started_at'] ? \Carbon\Carbon::parse($job['started_at'])->format('Y-m-d H:i:s') : 'Not started' }}</td>
          </tr>
          <tr>
            <th>{{ __('Completed') }}</th>
            <td>{{ $job['completed_at'] ? \Carbon\Carbon::parse($job['completed_at'])->format('Y-m-d H:i:s') : 'Not completed' }}</td>
          </tr>
          @if($job['started_at'] && $job['completed_at'])
            <tr>
              <th>{{ __('Duration') }}</th>
              <td>{{ \Carbon\Carbon::parse($job['started_at'])->diffForHumans(\Carbon\Carbon::parse($job['completed_at']), true) }}</td>
            </tr>
          @endif
          @if(!empty($job['import_options']))
            <tr>
              <th>{{ __('Import Type') }}</th>
              <td>{{ $job['import_options']['import_type'] ?? 'N/A' }}</td>
            </tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>

  {{-- Error Log --}}
  @if(!empty($job['error_log']) && count($job['error_log']) > 0)
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0 text-danger"><i class="fas fa-exclamation-triangle"></i> Error Log ({{ count($job['error_log']) }} errors)</h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead>
              <tr>
                <th style="width: 80px;">{{ __('Row') }}</th>
                <th>{{ __('Error Message') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($job['error_log'] as $error)
                <tr>
                  <td>{{ $error['row'] ?? 'N/A' }}</td>
                  <td><code>{{ $error['message'] ?? 'Unknown error' }}</code></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  <div class="d-flex gap-2">
    <a href="{{ route('data-migration.jobs') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left"></i> Back to Jobs
    </a>
    <a href="{{ route('data-migration.index') }}" class="btn atom-btn-white">
      <i class="fas fa-home"></i> Dashboard
    </a>
  </div>
@endsection
