{{-- Saved Searches - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'savedSearches'])
@endsection

@section('content')
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <h1 class="mb-4"><i class="fas fa-search me-2"></i>Saved Searches</h1>

  {{-- Saved Searches Table --}}
  <div class="card mb-4">
    <div class="card-body">
      @if(count($savedSearches ?? []) > 0)
        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead>
              <tr>
                <th>Name</th>
                <th>Query</th>
                <th>Date Saved</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($savedSearches as $search)
                <tr>
                  <td>{{ e($search->name) }}</td>
                  <td><code>{{ e(\Illuminate\Support\Str::limit($search->query ?? '', 60)) }}</code></td>
                  <td>{{ $search->created_at ? \Carbon\Carbon::parse($search->created_at)->format('Y-m-d') : '-' }}</td>
                  <td>
                    <a href="{{ route('research.savedSearches.run', $search->id) }}" class="btn btn-sm atom-btn-white" title="Run Search">
                      <i class="fas fa-play"></i>
                    </a>
                    <form action="{{ route('research.savedSearches.destroy', $search->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this saved search?')">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Delete">
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
        <div class="text-center py-4 text-muted">
          <i class="fas fa-search fa-3x mb-3"></i>
          <p>No saved searches yet.</p>
        </div>
      @endif
    </div>
  </div>

  @if(is_object($savedSearches) && method_exists($savedSearches, 'links'))
    <div class="d-flex justify-content-center mb-4">{{ $savedSearches->links() }}</div>
  @endif

  {{-- Save New Search --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-plus me-2"></i>Save a New Search</div>
    <div class="card-body">
      <form action="{{ route('research.savedSearches.store') }}" method="POST">
        @csrf
        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="ss_name" class="form-label">Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
            <input type="text" name="name" id="ss_name" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="ss_query" class="form-label">Search Query <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
            <input type="text" name="query" id="ss_query" class="form-control" required placeholder="Enter a search query or URL">
          </div>
          <div class="col-md-2 mb-3 d-flex align-items-end">
            <button type="submit" class="btn atom-btn-outline-success w-100">
              <i class="fas fa-save me-1"></i>Save
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
@endsection
