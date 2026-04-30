@extends('theme::layouts.1col')

@section('title', 'Migration Jobs - Data Migration')
@section('body-class', 'admin data-migration jobs')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-tasks me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Migration Jobs') }}</h1>
      <span class="small text-muted">{{ __('Track import and export operations') }}</span>
    </div>
  </div>

  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('data-migration.index') }}">Data Migration</a></li>
      <li class="breadcrumb-item active">Jobs</li>
    </ol>
  </nav>

  <div class="d-flex gap-2 mb-3">
    <a href="{{ route('data-migration.upload') }}" class="btn btn-primary">
      <i class="fas fa-upload"></i> {{ __('New Import') }}
    </a>
    <a href="{{ route('data-migration.index') }}" class="btn btn btn-outline-secondary">
      <i class="fas fa-arrow-left"></i> {{ __('Dashboard') }}
    </a>
  </div>

  @if(count($jobs) > 0)
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th>{{ __('ID') }}</th>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Target') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Progress') }}</th>
                <th>{{ __('Imported') }}</th>
                <th>{{ __('Updated') }}</th>
                <th>{{ __('Skipped') }}</th>
                <th>{{ __('Errors') }}</th>
                <th>{{ __('Created') }}</th>
                <th>{{ __('Completed') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($jobs as $job)
                @php
                  $pct = $job['total_records'] > 0 ? min(100, round(($job['processed_records'] / $job['total_records']) * 100)) : 0;
                @endphp
                <tr>
                  <td>{{ $job['id'] }}</td>
                  <td>
                    <a href="{{ route('data-migration.job', $job['id']) }}">
                      {{ $job['name'] ?: 'Job #' . $job['id'] }}
                    </a>
                  </td>
                  <td><span class="badge bg-info">{{ $job['target_type'] }}</span></td>
                  <td>
                    @if($job['status'] === 'completed')
                      <span class="badge bg-success">{{ __('Completed') }}</span>
                    @elseif($job['status'] === 'failed')
                      <span class="badge bg-danger">{{ __('Failed') }}</span>
                    @elseif($job['status'] === 'processing')
                      <span class="badge bg-info">{{ __('Processing') }}</span>
                    @else
                      <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                    @endif
                  </td>
                  <td>
                    <div class="progress" style="height: 18px; min-width: 80px;">
                      <div class="progress-bar {{ $job['status'] === 'failed' ? 'bg-danger' : '' }}" role="progressbar" style="width: {{ $pct }}%">{{ $pct }}%</div>
                    </div>
                  </td>
                  <td>{{ number_format($job['imported_records']) }}</td>
                  <td>{{ number_format($job['updated_records']) }}</td>
                  <td>{{ number_format($job['skipped_records']) }}</td>
                  <td>
                    @if($job['error_count'] > 0)
                      <span class="text-danger fw-bold">{{ number_format($job['error_count']) }}</span>
                    @else
                      0
                    @endif
                  </td>
                  <td>{{ $job['created_at'] ? \Carbon\Carbon::parse($job['created_at'])->format('Y-m-d H:i') : '' }}</td>
                  <td>{{ $job['completed_at'] ? \Carbon\Carbon::parse($job['completed_at'])->format('Y-m-d H:i') : '' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @else
    <div class="alert alert-info">
      <i class="fas fa-info-circle"></i> {{ __('No migration jobs found.') }}
    </div>
  @endif
@endsection
