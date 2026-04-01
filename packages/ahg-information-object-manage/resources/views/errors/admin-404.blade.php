@extends('theme::layouts.1col')
@section('title', 'Record Not Found')
@section('body-class', 'admin error')

@section('content')
<div class="alert alert-danger mb-4">
  <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Record Not Found (404)</h4>
  <p>The URL <code>/{{ $diagnostics['slug'] }}</code> could not be resolved to a record.</p>
</div>

@foreach($diagnostics['issues'] as $issue)
  <div class="card mb-3 border-{{ $issue['type'] === 'missing_slugs' ? 'warning' : 'danger' }}">
    <div class="card-header {{ $issue['type'] === 'missing_slugs' ? 'bg-warning text-dark' : 'bg-danger text-white' }}">
      <i class="fas fa-{{ $issue['type'] === 'missing_slugs' ? 'unlink' : 'times-circle' }} me-2"></i>
      @if($issue['type'] === 'missing_slugs')
        Missing Slugs Detected
      @elseif($issue['type'] === 'unknown_class')
        Unknown Object Class
      @else
        Not Found
      @endif
    </div>
    <div class="card-body">
      <p>{{ $issue['message'] }}</p>

      @if(!empty($issue['records']))
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Parent ID</th>
                <th>Nested Set</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($issue['records'] as $rec)
                <tr>
                  <td><code>{{ $rec['id'] }}</code></td>
                  <td>{{ $rec['title'] }}</td>
                  <td>{{ $rec['parent_id'] ?? '-' }}</td>
                  <td>
                    @if($rec['has_nested_set'])
                      <span class="badge bg-success">OK</span>
                    @else
                      <span class="badge bg-warning text-dark">Missing lft/rgt</span>
                    @endif
                  </td>
                  <td>
                    <form method="POST" action="{{ route('admin.fix-missing-slug') }}" class="d-inline">
                      @csrf
                      <input type="hidden" name="object_id" value="{{ $rec['id'] }}">
                      <button type="submit" class="btn btn-sm atom-btn-outline-success">
                        <i class="fas fa-link me-1"></i>Generate Slug
                      </button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endforeach

@if(empty($diagnostics['issues']))
  <div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>No specific issues detected. The slug <code>{{ $diagnostics['slug'] }}</code> simply does not exist in the database.
  </div>
@endif

<a href="{{ url('/') }}" class="btn atom-btn-white"><i class="fas fa-home me-1"></i>Go to Homepage</a>
<a href="{{ url('/glam/browse') }}" class="btn atom-btn-outline-success"><i class="fas fa-search me-1"></i>Browse Records</a>
@endsection
