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
          <div class="small text-muted">Error</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center border-primary">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-primary">{{ number_format($stats['running']) }}</div>
          <div class="small text-muted">Running</div>
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
      <a href="{{ route('job.browse', ['status' => 'completed']) }}"
         class="btn btn-sm {{ $currentStatus === 'completed' ? 'btn-success' : 'btn-outline-success' }}">
        Completed
      </a>
      <a href="{{ route('job.browse', ['status' => 'error']) }}"
         class="btn btn-sm {{ $currentStatus === 'error' ? 'btn-danger' : 'btn-outline-danger' }}">
        Error
      </a>
      <a href="{{ route('job.browse', ['status' => 'running']) }}"
         class="btn btn-sm {{ $currentStatus === 'running' ? 'btn-primary' : 'btn-outline-primary' }}">
        Running
      </a>
    </div>

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'date',
      ])
    </div>
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
                  <span class="badge bg-danger">{{ $job['status_name'] ?: 'Error' }}</span>
                @else
                  <span class="badge bg-primary">{{ $job['status_name'] ?: 'Running' }}</span>
                @endif
              </td>
              <td>{{ $job['created_at'] ? \Carbon\Carbon::parse($job['created_at'])->format('Y-m-d H:i') : '' }}</td>
              <td>{{ $job['completed_at'] ? \Carbon\Carbon::parse($job['completed_at'])->format('Y-m-d H:i') : '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])
@endsection
