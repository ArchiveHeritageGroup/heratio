{{-- Collection Detail - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'collections'])
@endsection

@section('content')
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-layer-group me-2"></i>{{ e($collection->name ?? '') }}</h1>
    <a href="{{ route('research.collections') }}" class="btn btn-secondary">
      <i class="fas fa-arrow-left me-1"></i>Back
    </a>
  </div>

  {{-- Collection Info / Edit --}}
  <div class="card mb-4">
    <div class="card-header"><i class="fas fa-info-circle me-2"></i>Collection Info</div>
    <div class="card-body">
      <form action="{{ route('research.collections.update', $collection->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
          <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
          <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $collection->name ?? '') }}" required>
        </div>
        <div class="mb-3">
          <label for="description" class="form-label">Description</label>
          <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $collection->description ?? '') }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-save me-1"></i>Update
        </button>
      </form>
    </div>
  </div>

  {{-- Items --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-list me-2"></i>Items ({{ count($items ?? []) }})</span>
    </div>
    <div class="card-body">
      @if(count($items ?? []) > 0)
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead>
              <tr>
                <th>Title</th>
                <th>Slug</th>
                <th>Notes</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($items as $item)
                <tr>
                  <td>{{ e($item->title ?? '-') }}</td>
                  <td>
                    @if($item->slug ?? false)
                      <a href="{{ url('/' . $item->slug) }}" target="_blank">{{ e($item->slug) }}</a>
                    @else
                      -
                    @endif
                  </td>
                  <td>{{ e($item->notes ?? '-') }}</td>
                  <td>
                    <form action="{{ route('research.collections.removeItem', [$collection->id, $item->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this item?')">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                        <i class="fas fa-times"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mb-0">No items in this collection.</p>
      @endif
    </div>
  </div>

  {{-- Add Item --}}
  <div class="card mb-4">
    <div class="card-header"><i class="fas fa-plus me-2"></i>Add Item</div>
    <div class="card-body">
      <form action="{{ route('research.collections.addItem', $collection->id) }}" method="POST">
        @csrf
        <div class="row">
          <div class="col-md-8 mb-3">
            <label for="search_query" class="form-label">Search for an item</label>
            <input type="text" name="search_query" id="search_query" class="form-control" placeholder="Search by title, reference, or slug...">
          </div>
          <div class="col-md-4 mb-3">
            <label for="item_notes" class="form-label">Notes</label>
            <input type="text" name="notes" id="item_notes" class="form-control" placeholder="Optional notes">
          </div>
        </div>
        <input type="hidden" name="object_id" id="object_id" value="">
        <button type="submit" class="btn btn-success btn-sm">
          <i class="fas fa-plus me-1"></i>Add to Collection
        </button>
      </form>
    </div>
  </div>
@endsection
