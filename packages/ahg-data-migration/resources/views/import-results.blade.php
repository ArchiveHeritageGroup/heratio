@extends('theme::layouts.1col')

@section('title', 'Import Results - Data Migration')
@section('body-class', 'admin data-migration import-results')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-check-circle me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Import Results') }}</h1>
      <span class="small text-muted">{{ __('Summary of the completed import operation') }}</span>
    </div>
  </div>

  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('data-migration.index') }}">Data Migration</a></li>
      <li class="breadcrumb-item active">Import Results</li>
    </ol>
  </nav>

  @if(!empty($result))
    @if(!empty($result['success']) && $result['success'])
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Import completed successfully.
      </div>
    @else
      <div class="alert alert-danger">
        <i class="fas fa-times-circle"></i> Import encountered issues. {{ $result['message'] ?? '' }}
      </div>
    @endif

    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card text-center border-success">
          <div class="card-body py-3">
            <div class="fs-2 fw-bold text-success">{{ number_format($result['imported'] ?? 0) }}</div>
            <div class="text-muted">Records Imported</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card text-center border-primary">
          <div class="card-body py-3">
            <div class="fs-2 fw-bold text-primary">{{ number_format($result['updated'] ?? 0) }}</div>
            <div class="text-muted">Records Updated</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card text-center border-warning">
          <div class="card-body py-3">
            <div class="fs-2 fw-bold text-warning">{{ number_format($result['skipped'] ?? 0) }}</div>
            <div class="text-muted">Records Skipped</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card text-center border-danger">
          <div class="card-body py-3">
            <div class="fs-2 fw-bold text-danger">{{ number_format($result['errors'] ?? 0) }}</div>
            <div class="text-muted">Errors</div>
          </div>
        </div>
      </div>
    </div>
  @else
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle"></i> No import results available. Results are only available immediately after an import operation.
    </div>
  @endif

  {{-- Job details link --}}
  @if($job)
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Job Details</h5>
      </div>
      <div class="card-body">
        <table class="table table-bordered mb-0">
          <tbody>
            <tr>
              <th style="width: 200px;">{{ __('Job ID') }}</th>
              <td>
                <a href="{{ route('data-migration.job', $job['id']) }}">{{ $job['id'] }}</a>
              </td>
            </tr>
            <tr>
              <th>{{ __('Name') }}</th>
              <td>{{ $job['name'] }}</td>
            </tr>
            <tr>
              <th>{{ __('Target Type') }}</th>
              <td><span class="badge bg-info">{{ $job['target_type'] }}</span></td>
            </tr>
            <tr>
              <th>{{ __('Status') }}</th>
              <td>
                @if($job['status'] === 'completed')
                  <span class="badge bg-success">{{ __('Completed') }}</span>
                @elseif($job['status'] === 'failed')
                  <span class="badge bg-danger">{{ __('Failed') }}</span>
                @else
                  <span class="badge bg-warning text-dark">{{ ucfirst($job['status']) }}</span>
                @endif
              </td>
            </tr>
            <tr>
              <th>{{ __('Message') }}</th>
              <td>{{ $job['progress_message'] ?? 'N/A' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    {{-- Error log --}}
    @if(!empty($job['error_log']) && count($job['error_log']) > 0)
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0 text-danger"><i class="fas fa-exclamation-triangle"></i> Errors ({{ count($job['error_log']) }})</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
            <table class="table table-bordered table-sm table-striped mb-0">
              <thead>
                <tr>
                  <th style="width: 80px;">{{ __('Row') }}</th>
                  <th>{{ __('Error') }}</th>
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
  @endif

  <div class="d-flex flex-wrap gap-2">
    <a href="{{ route('data-migration.upload') }}" class="btn atom-btn-outline-success">
      <i class="fas fa-upload"></i> {{ __('New Import') }}
    </a>
    <a href="{{ route('data-migration.jobs') }}" class="btn atom-btn-white">
      <i class="fas fa-tasks"></i> {{ __('View All Jobs') }}
    </a>
    <a href="{{ route('data-migration.index') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left"></i> {{ __('Dashboard') }}
    </a>
  </div>
@endsection
