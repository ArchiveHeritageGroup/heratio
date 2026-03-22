@extends('theme::layouts.1col')

@section('title', 'Jobs')
@section('body-class', 'browse jobs')

@section('title-block')
  <h1>Jobs</h1>
@endsection

@section('before-content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Stats cards --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card text-center h-100">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold">{{ (int) ($stats['total'] ?? 0) }}</div>
          <div class="text-muted small">Total</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center h-100 border-primary">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-primary">{{ (int) ($stats['running'] ?? 0) }}</div>
          <div class="text-muted small">Active</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center h-100 border-success">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-success">{{ (int) ($stats['completed'] ?? 0) }}</div>
          <div class="text-muted small">Completed</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center h-100 border-danger">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-danger">{{ (int) ($stats['error'] ?? 0) }}</div>
          <div class="text-muted small">Failed</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Filter pills + action buttons --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    <ul class="nav nav-pills">
      <li class="nav-item">
        <a href="{{ route('job.browse') }}" class="nav-link {{ ($currentStatus === '' || $currentStatus === 'all') ? 'active' : '' }}">
          All
        </a>
      </li>
      <li class="nav-item">
        <a href="{{ route('job.browse', ['status' => 'running']) }}" class="nav-link {{ $currentStatus === 'running' ? 'active' : '' }}">
          Active
        </a>
      </li>
      <li class="nav-item">
        <a href="{{ route('job.browse', ['status' => 'completed']) }}" class="nav-link {{ $currentStatus === 'completed' ? 'active' : '' }}">
          Completed
        </a>
      </li>
      <li class="nav-item">
        <a href="{{ route('job.browse', ['status' => 'error']) }}" class="nav-link {{ $currentStatus === 'error' ? 'active' : '' }}">
          Failed
        </a>
      </li>
    </ul>

    <div class="d-flex flex-wrap gap-2 ms-auto">
      <a href="{{ route('job.browse', array_merge(request()->query(), [])) }}" class="btn btn-outline-secondary btn-sm" title="Refresh">
        <i class="fas fa-sync-alt"></i> Refresh
      </a>
      @if(Route::has('job.export-csv'))
        <a href="{{ route('job.export-csv') }}" class="btn btn-outline-secondary btn-sm" title="Export CSV">
          <i class="fas fa-download"></i> Export CSV
        </a>
      @endif
      @if(($stats['completed'] ?? 0) + ($stats['error'] ?? 0) > 0 && Route::has('job.clear-inactive'))
        <a href="{{ route('job.clear-inactive') }}" class="btn btn-outline-danger btn-sm" title="Clear inactive jobs">
          <i class="fas fa-trash-alt"></i> Clear inactive
        </a>
      @endif
    </div>
  </div>
@endsection

@section('content')
  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-hover mb-0">
        <thead>
          <tr style="background:var(--ahg-primary);color:#fff">
            <th>Job name</th>
            <th>Status</th>
            <th>User</th>
            <th>Created</th>
            <th>Completed</th>
            <th>Related object</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $job)
            <tr>
              <td>{{ $job['name'] ?: '[Unknown]' }}</td>
              <td>
                @if($job['status_id'] == 184)
                  <span class="badge bg-success">Completed</span>
                @elseif($job['status_id'] == 185)
                  <span class="badge bg-danger">Error</span>
                @else
                  <span class="badge bg-primary">In progress</span>
                @endif
              </td>
              <td>{{ $job['user_name'] ?: ($job['username'] ?? 'System') }}</td>
              <td>{{ !empty($job['created_at']) ? \Carbon\Carbon::parse($job['created_at'])->format('Y-m-d g:i A') : '' }}</td>
              <td>{{ !empty($job['completed_at']) ? \Carbon\Carbon::parse($job['completed_at'])->format('Y-m-d g:i A') : '' }}</td>
              <td>
                @if(!empty($job['object_slug']))
                  <a href="{{ url('/' . $job['object_slug']) }}">{{ $job['object_slug'] }}</a>
                @elseif(!empty($job['object_id']))
                  <span class="text-muted">#{{ (int) $job['object_id'] }}</span>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td>
                <div class="btn-group btn-group-sm">
                  <a href="{{ route('job.show', $job['id']) }}" class="btn btn-outline-primary btn-sm" title="Report">
                    <i class="fas fa-file-alt"></i>
                  </a>
                  @if($job['status_id'] != 183)
                    @if(Route::has('job.destroy'))
                      <a href="{{ route('job.destroy', $job['id']) }}" class="btn btn-outline-danger btn-sm" title="Delete">
                        <i class="fas fa-trash-alt"></i>
                      </a>
                    @endif
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="alert alert-info">No jobs found.</div>
  @endif
@endsection

@section('after-content')
  @include('ahg-core::components.pager', ['pager' => $pager])

  <div class="text-muted small text-center mb-3">
    Showing {{ $pager->getNbResults() ? count($pager->getResults()) : 0 }} of {{ $pager->getNbResults() }} job(s)
  </div>

  {{-- Auto-refresh for active jobs --}}
  @if($currentStatus === 'running' && ($stats['running'] ?? 0) > 0)
    <script>
      (function() {
        var refreshInterval = 15000;
        var timerId = setInterval(function() {
          window.location.reload();
        }, refreshInterval);
        document.addEventListener('visibilitychange', function() {
          if (document.hidden) {
            clearInterval(timerId);
          } else {
            timerId = setInterval(function() {
              window.location.reload();
            }, refreshInterval);
          }
        });
      })();
    </script>
  @endif
@endsection
