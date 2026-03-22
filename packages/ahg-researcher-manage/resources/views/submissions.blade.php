@extends('theme::layouts.1col')

@section('title', 'Researcher Submissions')
@section('body-class', 'browse researcher-submissions')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <i class="fas fa-3x fa-flask me-3" aria-hidden="true"></i>
      <div class="d-flex flex-column">
        <h1 class="mb-0">
          @if($pager->getNbResults())
            Showing {{ number_format($pager->getNbResults()) }} results
          @else
            No results found
          @endif
        </h1>
        <span class="small text-muted">Researcher Submissions</span>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('researcher.dashboard') }}" class="btn atom-btn-white">
        <i class="fas fa-tachometer-alt me-1"></i> Dashboard
      </a>
      <a href="{{ route('researcher.import') }}" class="btn atom-btn-white">
        <i class="fas fa-file-import me-1"></i> Import Exchange
      </a>
    </div>
  </div>

  {{-- Status filter buttons --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('researcher.submissions') }}"
       class="btn btn-sm {{ $currentStatus === '' ? 'atom-btn-white' : 'atom-btn-white' }}">
      All
    </a>
    <a href="{{ route('researcher.submissions', ['status' => 'draft']) }}"
       class="btn btn-sm {{ $currentStatus === 'draft' ? 'atom-btn-white' : 'atom-btn-white' }}">
      Draft
    </a>
    <a href="{{ route('researcher.submissions', ['status' => 'submitted']) }}"
       class="btn btn-sm {{ $currentStatus === 'submitted' ? 'atom-btn-outline-success' : 'atom-btn-white' }}">
      Submitted
    </a>
    <a href="{{ route('researcher.submissions', ['status' => 'under_review']) }}"
       class="btn btn-sm {{ $currentStatus === 'under_review' ? 'atom-btn-white' : 'atom-btn-white' }}">
      Under Review
    </a>
    <a href="{{ route('researcher.submissions', ['status' => 'approved']) }}"
       class="btn btn-sm {{ $currentStatus === 'approved' ? 'atom-btn-outline-success' : 'atom-btn-white' }}">
      Approved
    </a>
    <a href="{{ route('researcher.submissions', ['status' => 'published']) }}"
       class="btn btn-sm {{ $currentStatus === 'published' ? 'atom-btn-outline-success' : 'atom-btn-white' }}">
      Published
    </a>
    <a href="{{ route('researcher.submissions', ['status' => 'returned']) }}"
       class="btn btn-sm {{ $currentStatus === 'returned' ? 'atom-btn-white' : 'atom-btn-white' }}">
      Returned
    </a>
    <a href="{{ route('researcher.submissions', ['status' => 'rejected']) }}"
       class="btn btn-sm {{ $currentStatus === 'rejected' ? 'atom-btn-white' : 'atom-btn-white' }}">
      Rejected
    </a>
  </div>

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            @if($isAdmin)
              <th>Researcher</th>
            @endif
            <th>Source</th>
            <th>Items</th>
            <th>Files</th>
            <th>Status</th>
            <th>Created</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $sub)
            <tr>
              <td>{{ $sub['id'] }}</td>
              <td>{{ $sub['title'] ?: '[Untitled]' }}</td>
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
              <td>{{ $sub['created_at'] ? \Carbon\Carbon::parse($sub['created_at'])->format('Y-m-d H:i') : '' }}</td>
              <td>{{ $sub['updated_at'] ? \Carbon\Carbon::parse($sub['updated_at'])->format('Y-m-d H:i') : '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @include('ahg-core::components.pager', ['pager' => $pager])
  @else
    <div class="card">
      <div class="card-body text-center text-muted py-5">
        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
        @if($currentStatus !== '')
          No submissions with status "{{ str_replace('_', ' ', $currentStatus) }}".
        @else
          No submissions found.
        @endif
      </div>
    </div>
  @endif
@endsection
