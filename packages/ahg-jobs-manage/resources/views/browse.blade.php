@extends('theme::layouts.1col')

@section('title', 'Jobs')
@section('body-class', 'browse jobs')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cogs me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">Jobs</span>
    </div>
  </div>

  {{-- Stats cards --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold">{{ number_format($stats['total']) }}</div>
          <div class="small text-muted">Total</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center border-primary">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-primary">{{ number_format($stats['running']) }}</div>
          <div class="small text-muted">Active</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center border-success">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-success">{{ number_format($stats['completed']) }}</div>
          <div class="small text-muted">Completed</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center border-danger">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-danger">{{ number_format($stats['error']) }}</div>
          <div class="small text-muted">Failed</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Filter buttons --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('job.browse') }}"
         class="btn btn-sm {{ $currentStatus === '' ? 'btn-secondary' : 'btn-outline-secondary' }}">
        All
      </a>
      <a href="{{ route('job.browse', ['status' => 'running']) }}"
         class="btn btn-sm {{ $currentStatus === 'running' ? 'btn-primary' : 'btn-outline-primary' }}">
        Active
      </a>
      <a href="{{ route('job.browse', ['status' => 'completed']) }}"
         class="btn btn-sm {{ $currentStatus === 'completed' ? 'btn-success' : 'btn-outline-success' }}">
        Completed
      </a>
      <a href="{{ route('job.browse', ['status' => 'error']) }}"
         class="btn btn-sm {{ $currentStatus === 'error' ? 'btn-danger' : 'btn-outline-danger' }}">
        Failed
      </a>
    </div>

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'date',
      ])
    </div>
  </div>

  {{-- Action buttons --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('job.browse', request()->query()) }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-sync-alt me-1"></i> Refresh
    </a>
    <a href="{{ route('job.export-csv') }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-download me-1"></i> Export CSV
    </a>
    @if(($stats['completed'] + $stats['error']) > 0)
      <form action="{{ route('job.clear-inactive') }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Clear all completed and failed jobs?')">
          <i class="fas fa-trash me-1"></i> Clear inactive
        </button>
      </form>
    @endif
  </div>

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th>Job name</th>
            <th>User</th>
            <th>Status</th>
            <th>Created</th>
            <th>Completed</th>
            <th>Related object</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $job)
            <tr>
              <td>
                <a href="{{ route('job.show', $job['id']) }}">
                  {{ $job['name'] ?: '[Unknown]' }}
                </a>
              </td>
              <td>{{ $job['user_name'] ?: $job['username'] ?: '' }}</td>
              <td>
                @if($job['status_id'] == 184)
                  <span class="badge bg-success">{{ $job['status_name'] ?: 'Completed' }}</span>
                @elseif($job['status_id'] == 185)
                  <span class="badge bg-danger">{{ $job['status_name'] ?: 'Failed' }}</span>
                @else
                  <span class="badge bg-primary">{{ $job['status_name'] ?: 'Active' }}</span>
                @endif
              </td>
              <td>{{ $job['created_at'] ? \Carbon\Carbon::parse($job['created_at'])->format('Y-m-d H:i') : '' }}</td>
              <td>{{ $job['completed_at'] ? \Carbon\Carbon::parse($job['completed_at'])->format('Y-m-d H:i') : '' }}</td>
              <td>
                @if(!empty($job['object_slug']))
                  <a href="/{{ $job['object_slug'] }}">{{ $job['object_slug'] }}</a>
                @elseif(!empty($job['object_id']))
                  #{{ $job['object_id'] }}
                @else
                  &mdash;
                @endif
              </td>
              <td class="text-nowrap">
                <a href="{{ route('job.show', $job['id']) }}" class="btn btn-sm btn-outline-info" title="Report">
                  <i class="fas fa-file-alt"></i>
                </a>
                @if(in_array($job['status_id'], [184, 185]))
                  <form action="{{ route('job.destroy', $job['id']) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this job?')">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])

  {{-- Auto-refresh when there are active/running jobs --}}
  @if($currentStatus === 'running' || ($currentStatus === '' && $stats['running'] > 0))
    <script>setTimeout(function() { location.reload(); }, 15000);</script>
  @endif
@endsection
