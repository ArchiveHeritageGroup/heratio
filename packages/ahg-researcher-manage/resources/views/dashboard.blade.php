@extends('theme::layouts.1col')

@section('title', 'Researcher Dashboard')
@section('body-class', 'researcher dashboard')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <i class="fas fa-3x fa-flask me-3" aria-hidden="true"></i>
      <div class="d-flex flex-column">
        <h1 class="mb-0">Researcher Dashboard</h1>
        <span class="small text-muted">Manage your submissions</span>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('researcher.import') }}" class="btn atom-btn-white">
        <i class="fas fa-file-import me-1"></i> Import Exchange
      </a>
      <a href="{{ route('researcher.submissions') }}" class="btn atom-btn-white">
        <i class="fas fa-list me-1"></i> All Submissions
      </a>
    </div>
  </div>

  {{-- Stats cards --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-secondary">{{ number_format($stats['total']) }}</div>
          <div class="small text-muted">Total</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center border-secondary">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-secondary">{{ number_format($stats['draft']) }}</div>
          <div class="small text-muted">Draft</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center border-warning">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-warning">{{ number_format($stats['pending']) }}</div>
          <div class="small text-muted">Pending Review</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center border-success">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-success">{{ number_format($stats['approved']) }}</div>
          <div class="small text-muted">Approved</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center border-primary">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-primary">{{ number_format($stats['published']) }}</div>
          <div class="small text-muted">Published</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center border-danger">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-danger">{{ number_format($stats['returned_rejected']) }}</div>
          <div class="small text-muted">Returned / Rejected</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Recent submissions table --}}
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0">Recent Submissions</h5>
      <a href="{{ route('researcher.submissions') }}" class="btn btn-sm atom-btn-white">View All</a>
    </div>
    <div class="card-body p-0">
      @if(count($recentSubmissions) > 0)
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th>Title</th>
                @if($isAdmin)
                  <th>Researcher</th>
                @endif
                <th>Source</th>
                <th>Items</th>
                <th>Files</th>
                <th>Status</th>
                <th>Updated</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentSubmissions as $sub)
                <tr>
                  <td>
                    <a href="{{ route('researcher.submissions', ['status' => $sub['status']]) }}">
                      {{ $sub['title'] ?: '[Untitled]' }}
                    </a>
                  </td>
                  @if($isAdmin)
                    <td>{{ $sub['researcher_name'] ?: $sub['username'] ?: '' }}</td>
                  @endif
                  <td>
                    @if($sub['source_type'] === 'online')
                      <span class="badge bg-info">Online</span>
                    @else
                      <span class="badge bg-secondary">Offline</span>
                    @endif
                  </td>
                  <td>{{ number_format($sub['total_items'] ?? 0) }}</td>
                  <td>{{ number_format($sub['total_files'] ?? 0) }}</td>
                  <td>
                    @switch($sub['status'])
                      @case('draft')
                        <span class="badge bg-secondary">Draft</span>
                        @break
                      @case('submitted')
                        <span class="badge bg-warning text-dark">Submitted</span>
                        @break
                      @case('under_review')
                        <span class="badge bg-info">Under Review</span>
                        @break
                      @case('approved')
                        <span class="badge bg-success">Approved</span>
                        @break
                      @case('published')
                        <span class="badge bg-primary">Published</span>
                        @break
                      @case('returned')
                        <span class="badge bg-danger">Returned</span>
                        @break
                      @case('rejected')
                        <span class="badge bg-dark">Rejected</span>
                        @break
                      @default
                        <span class="badge bg-light text-dark">{{ $sub['status'] }}</span>
                    @endswitch
                  </td>
                  <td>{{ $sub['updated_at'] ? \Carbon\Carbon::parse($sub['updated_at'])->format('Y-m-d H:i') : '' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="text-center text-muted py-4">
          <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
          No submissions yet. Start by importing an exchange file or creating a new submission.
        </div>
      @endif
    </div>
  </div>
@endsection
