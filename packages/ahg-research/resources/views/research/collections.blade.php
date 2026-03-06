{{-- Evidence Sets (Collections) - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'collections'])
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-layer-group me-2"></i>Evidence Sets</h1>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createCollectionModal">
      <i class="fas fa-plus me-1"></i>New Collection
    </button>
  </div>

  <div class="row">
    @forelse($collections ?? [] as $collection)
      <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title">
              <a href="{{ route('research.viewCollection', $collection->id) }}">{{ e($collection->name) }}</a>
            </h5>
            @if($collection->description ?? false)
              <p class="card-text small text-muted">{{ e(\Illuminate\Support\Str::limit($collection->description, 100)) }}</p>
            @endif
            <div class="d-flex justify-content-between align-items-center mt-2">
              <span class="badge bg-primary rounded-pill">{{ $collection->items_count ?? 0 }} items</span>
              <small class="text-muted">{{ $collection->created_at ? $collection->created_at->format('Y-m-d') : '' }}</small>
            </div>
          </div>
          <div class="card-footer bg-transparent d-flex justify-content-end gap-1">
            <a href="{{ route('research.viewCollection', $collection->id) }}" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-eye"></i>
            </a>
            <form action="{{ route('research.collections.destroy', $collection->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this collection?')">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </div>
      </div>
    @empty
      <div class="col-12">
        <div class="text-center py-5 text-muted">
          <i class="fas fa-layer-group fa-3x mb-3"></i>
          <p>No collections yet. Create your first evidence set to start organising your research.</p>
        </div>
      </div>
    @endforelse
  </div>

  @if(method_exists($collections ?? collect(), 'links'))
    <div class="d-flex justify-content-center">{{ $collections->links() }}</div>
  @endif

  {{-- Create Collection Modal --}}
  <div class="modal fade" id="createCollectionModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="{{ route('research.collections.store') }}" method="POST">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>New Collection</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="description" class="form-label">Description</label>
              <textarea name="description" id="description" class="form-control" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success"><i class="fas fa-plus me-1"></i>Create</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
