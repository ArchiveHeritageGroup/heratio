{{-- Annotation Studio - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'annotations'])
@endsection

@section('content')
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-highlighter me-2"></i>Annotation Studio</h1>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#annotationModal">
      <i class="fas fa-plus me-1"></i>New Annotation
    </button>
  </div>

  {{-- Filter Bar --}}
  <div class="card mb-4">
    <div class="card-body">
      <form action="{{ route('research.annotations') }}" method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label for="search" class="form-label small">Search</label>
          <input type="text" name="q" id="search" class="form-control form-control-sm" placeholder="Search annotations..." value="{{ e($query ?? '') }}">
        </div>
        <div class="col-md-3">
          <label for="visibility" class="form-label small">Visibility</label>
          <select name="visibility" id="visibility" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="private" {{ ($visibility ?? '') === 'private' ? 'selected' : '' }}>Private</option>
            <option value="public" {{ ($visibility ?? '') === 'public' ? 'selected' : '' }}>Public</option>
            <option value="shared" {{ ($visibility ?? '') === 'shared' ? 'selected' : '' }}>Shared</option>
          </select>
        </div>
        <div class="col-md-3">
          <label for="tag" class="form-label small">Tag</label>
          <input type="text" name="tag" id="tag" class="form-control form-control-sm" placeholder="Filter by tag" value="{{ e($tag ?? '') }}">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Annotations List --}}
  @forelse($annotations ?? [] as $annotation)
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h5 class="card-title mb-1">
              <a href="#" data-bs-toggle="modal" data-bs-target="#editAnnotationModal{{ $annotation->id }}">
                {{ e($annotation->title ?? 'Untitled') }}
              </a>
            </h5>
            <div class="mb-2">
              <span class="badge bg-{{ ($annotation->type ?? '') === 'note' ? 'info' : (($annotation->type ?? '') === 'highlight' ? 'warning' : 'secondary') }}">
                {{ ucfirst(e($annotation->type ?? 'note')) }}
              </span>
              <span class="badge bg-{{ ($annotation->visibility ?? 'private') === 'private' ? 'dark' : (($annotation->visibility ?? '') === 'public' ? 'success' : 'primary') }}">
                {{ ucfirst(e($annotation->visibility ?? 'private')) }}
              </span>
              @if($annotation->entity_type ?? false)
                <span class="badge bg-light text-dark">{{ e($annotation->entity_type) }}</span>
              @endif
            </div>
          </div>
          <small class="text-muted">{{ $annotation->created_at ? \Carbon\Carbon::parse($annotation->created_at)->format('Y-m-d H:i') : '' }}</small>
        </div>
        <div class="card-text">
          @if(($annotation->content_format ?? 'text') === 'html')
            {!! $annotation->content !!}
          @else
            {!! nl2br(e($annotation->content ?? '')) !!}
          @endif
        </div>
        @if($annotation->tags ?? false)
          <div>
            @foreach(explode(',', $annotation->tags) as $t)
              <span class="badge bg-outline-secondary border">{{ e(trim($t)) }}</span>
            @endforeach
          </div>
        @endif
      </div>
      <div class="card-footer bg-transparent d-flex justify-content-end gap-1">
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAnnotationModal{{ $annotation->id }}">
          <i class="fas fa-edit"></i>
        </button>
        <form action="{{ route('research.annotations.destroy', $annotation->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this annotation?')">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
        </form>
      </div>
    </div>

    {{-- Edit Modal for each annotation --}}
    <div class="modal fade" id="editAnnotationModal{{ $annotation->id }}" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form action="{{ route('research.annotations.update', $annotation->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-header">
              <h5 class="modal-title">Edit Annotation</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" value="{{ e($annotation->title ?? '') }}">
              </div>
              <div class="mb-3">
                <label class="form-label">Content <span class="text-danger">*</span></label>
                <textarea name="content" class="form-control" rows="5" required>{{ e($annotation->content ?? '') }}</textarea>
              </div>
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label class="form-label">Entity Type</label>
                  <input type="text" name="entity_type" class="form-control" value="{{ e($annotation->entity_type ?? '') }}">
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">Tags</label>
                  <input type="text" name="tags" class="form-control" value="{{ e($annotation->tags ?? '') }}" placeholder="comma-separated">
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">Visibility</label>
                  <select name="visibility" class="form-select">
                    <option value="private" {{ ($annotation->visibility ?? '') === 'private' ? 'selected' : '' }}>Private</option>
                    <option value="public" {{ ($annotation->visibility ?? '') === 'public' ? 'selected' : '' }}>Public</option>
                    <option value="shared" {{ ($annotation->visibility ?? '') === 'shared' ? 'selected' : '' }}>Shared</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @empty
    <div class="text-center py-5 text-muted">
      <i class="fas fa-highlighter fa-3x mb-3"></i>
      <p>No annotations yet. Create your first annotation to start recording your research notes.</p>
    </div>
  @endforelse

  @if(is_object($annotations) && method_exists($annotations, 'links'))
    <div class="d-flex justify-content-center">{{ $annotations->links() }}</div>
  @endif

  {{-- Create Annotation Modal --}}
  <div class="modal fade" id="annotationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form action="{{ route('research.annotations.store') }}" method="POST">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-highlighter me-2"></i>New Annotation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="ann_title" class="form-label">Title</label>
              <input type="text" name="title" id="ann_title" class="form-control">
            </div>
            <div class="mb-3">
              <label for="ann_content" class="form-label">Content <span class="text-danger">*</span></label>
              <textarea name="content" id="ann_content" class="form-control" rows="5" required></textarea>
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="ann_entity_type" class="form-label">Entity Type</label>
                <input type="text" name="entity_type" id="ann_entity_type" class="form-control" placeholder="e.g. information_object">
              </div>
              <div class="col-md-4 mb-3">
                <label for="ann_tags" class="form-label">Tags</label>
                <input type="text" name="tags" id="ann_tags" class="form-control" placeholder="comma-separated">
              </div>
              <div class="col-md-4 mb-3">
                <label for="ann_visibility" class="form-label">Visibility</label>
                <select name="visibility" id="ann_visibility" class="form-select">
                  <option value="private">Private</option>
                  <option value="public">Public</option>
                  <option value="shared">Shared</option>
                </select>
              </div>
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
