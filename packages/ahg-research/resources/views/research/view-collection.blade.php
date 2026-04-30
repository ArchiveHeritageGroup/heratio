@extends('theme::layouts.2col')
@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'collections'])
@endsection
@section('title', $collection->name ?? 'Evidence Set')

@section('content')
@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1>
    <a href="{{ route('research.collections') }}" class="btn btn-outline-secondary btn-sm me-2"><i class="fas fa-arrow-left"></i></a>
    <i class="fas fa-folder-open text-primary me-2"></i>{{ e($collection->name) }}
  </h1>
  <div>
    <div class="dropdown d-inline-block me-1">
      <button class="btn btn-danger btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-file-export me-1"></i>Export Finding Aid</button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="{{ url('/research/exportFindingAid?id=' . $collection->id . '&format=pdf') }}"><i class="fas fa-file-pdf me-2 text-danger"></i>PDF</a></li>
        <li><a class="dropdown-item" href="{{ url('/research/exportFindingAid?id=' . $collection->id . '&format=docx') }}"><i class="fas fa-file-word me-2 text-primary"></i>DOCX</a></li>
        <li><a class="dropdown-item" href="{{ url('/research/generateFindingAid?id=' . $collection->id) }}"><i class="fas fa-file-code me-2 text-secondary"></i>HTML</a></li>
      </ul>
    </div>
    <button type="button" class="btn btn-outline-primary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editCollectionModal"><i class="fas fa-edit me-1"></i>Edit</button>
    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteCollectionModal"><i class="fas fa-trash me-1"></i>Delete</button>
  </div>
</div>

{{-- Collection Info --}}
<div class="card mb-4">
  <div class="card-body">
    <div class="row">
      <div class="col-md-8">
        @if($collection->description)
          <p class="mb-2">{{ e($collection->description) }}</p>
        @endif
        <small class="text-muted"><i class="fas fa-clock me-1"></i>Created: {{ date('M j, Y', strtotime($collection->created_at)) }}</small>
      </div>
      <div class="col-md-4 text-md-end">
        <span class="badge bg-{{ ($collection->is_public ?? false) ? 'success' : 'secondary' }} me-2">{{ ($collection->is_public ?? false) ? 'Public' : 'Private' }}</span>
        <span class="badge bg-primary">{{ count($collection->items ?? []) }} items</span>
      </div>
    </div>
  </div>
</div>

{{-- Add Item --}}
<div class="card mb-4">
  <div class="card-header bg-success text-white py-2"><i class="fas fa-plus me-2"></i>Add Item to Evidence Set</div>
  <div class="card-body">
    <form action="{{ route('research.collections.addItem', $collection->id) }}" method="POST">
      @csrf
      <input type="hidden" name="object_id" id="addItemObjectId" value="">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">{{ __('Search for item') }}</label>
          <select id="itemSearchSelect"></select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ __('Notes (optional)') }}</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="{{ __('Add a note...') }}"></textarea>
        </div>
        <div class="col-md-2">
          <label class="form-label d-block">&nbsp;</label>
          <div class="form-check">
            <input type="checkbox" name="include_descendants" value="1" class="form-check-input" id="includeDescendants">
            <label class="form-check-label small" for="includeDescendants">{{ __('Include children') }}</label>
          </div>
        </div>
        <div class="col-md-2">
          <label class="form-label d-block">&nbsp;</label>
          <button type="submit" class="btn btn-success w-100"><i class="fas fa-plus me-1"></i>Add</button>
        </div>
      </div>
    </form>
  </div>
</div>

{{-- Items Table --}}
<div class="card">
  <div class="card-header"><i class="fas fa-list me-2"></i>Evidence Set Items ({{ count($collection->items ?? []) }})</div>
  @if(count($collection->items ?? []) > 0)
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr>
          <th>{{ __('Title') }}</th>
          <th>{{ __('Level') }}</th>
          <th width="30%">{{ __('Notes') }}</th>
          <th>{{ __('Added') }}</th>
          <th width="100">{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
      @foreach($collection->items as $item)
        <tr>
          <td>
            <a href="{{ url('/' . ($item->object_slug ?? $item->slug ?? '')) }}">{{ e($item->object_title ?? $item->title ?? 'Untitled') }}</a>
            @if($item->identifier ?? false)
              <br><small class="text-muted">{{ e($item->identifier) }}</small>
            @endif
          </td>
          <td><small>{{ e($item->level_of_description ?? '-') }}</small></td>
          <td>
            <div class="notes-cell"
                 data-id="{{ $item->object_id ?? $item->id }}"
                 data-notes="{{ e($item->notes ?? '') }}"
                 data-title="{{ e($item->object_title ?? $item->title ?? 'Untitled') }}">
              <span class="notes-text">{!! nl2br(e($item->notes ?: '-')) !!}</span>
            </div>
          </td>
          <td><small>{{ $item->created_at ? date('M j, Y', strtotime($item->created_at)) : '' }}</small></td>
          <td>
            <button type="button" class="btn btn-sm btn-outline-primary edit-notes-btn"
                    data-id="{{ $item->object_id ?? $item->id }}"
                    data-bs-toggle="modal" data-bs-target="#editNotesModal"><i class="fas fa-edit"></i></button>
            <form method="POST" action="{{ route('research.collections.removeItem', [$collection->id, $item->object_id ?? $item->id]) }}" class="d-inline" onsubmit="return confirm('Remove?')">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
            </form>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  @else
  <div class="card-body text-center text-muted py-5">
    <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
    <p class="mb-0">This evidence set is empty. Use the search above to add items.</p>
  </div>
  @endif
</div>

{{-- Edit Collection Modal --}}
<div class="modal fade" id="editCollectionModal" tabindex="-1">
  <div class="modal-dialog">
    <form action="{{ route('research.collections.update', $collection->id) }}" method="POST">
      @csrf
      @method('PUT')
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Evidence Set</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">{{ __('Name *') }}</label><input type="text" name="name" class="form-control" value="{{ e($collection->name) }}" required></div>
          <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea name="description" class="form-control" rows="3">{{ e($collection->description ?? '') }}</textarea></div>
          <div class="form-check"><input type="checkbox" name="is_public" value="1" class="form-check-input" id="isPublic" {{ ($collection->is_public ?? false) ? 'checked' : '' }}><label class="form-check-label" for="isPublic">{{ __('Public') }}</label></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button></div>
      </div>
    </form>
  </div>
</div>

{{-- Edit Notes Modal --}}
<div class="modal fade" id="editNotesModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" id="editNotesForm">
      @csrf
      <input type="hidden" name="booking_action" value="update_notes">
      <input type="hidden" name="object_id" id="editNotesObjectId" value="">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="fas fa-sticky-note me-2"></i>Edit Notes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <p class="text-muted mb-2"><small><i class="fas fa-file-alt me-1"></i><span id="editNotesItemTitle"></span></small></p>
          <label class="form-label">{{ __('Notes') }}</label>
          <textarea name="notes" id="editNotesTextarea" class="form-control" rows="6" placeholder="{{ __('Add your research notes here...') }}"></textarea>
          <small class="text-muted">Use these notes to track research progress, observations, or relevant information.</small>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Notes</button></div>
      </div>
    </form>
  </div>
</div>

{{-- Delete Collection Modal --}}
<div class="modal fade" id="deleteCollectionModal" tabindex="-1">
  <div class="modal-dialog">
    <form action="{{ route('research.collections.destroy', $collection->id) }}" method="POST">
      @csrf
      @method('DELETE')
      <div class="modal-content">
        <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><p>Delete this evidence set?</p><p class="text-danger"><strong>{{ e($collection->name) }}</strong></p></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete</button></div>
      </div>
    </form>
  </div>
</div>

@push('css')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Item search TomSelect
  new TomSelect('#itemSearchSelect', {
    valueField: 'id', labelField: 'name', searchField: ['name'],
    placeholder: 'Type to search...', loadThrottle: 300,
    load: function(query, callback) {
      if (query.length < 2) return callback();
      fetch('/informationobject/autocomplete?query=' + encodeURIComponent(query) + '&limit=20')
        .then(function(r) { return r.json(); })
        .then(function(data) { callback(data); })
        .catch(function() { callback(); });
    },
    render: {
      option: function(item) { return '<div><strong>' + (item.name || '[Untitled]') + '</strong> <small class="text-muted">#' + item.id + '</small></div>'; },
      item: function(item) { return '<div>' + (item.name || '[Untitled]') + '</div>'; }
    },
    onChange: function(value) {
      document.getElementById('addItemObjectId').value = value;
    }
  });

  // Edit notes modal handler
  document.querySelectorAll('.edit-notes-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.dataset.id;
      var cell = document.querySelector('.notes-cell[data-id="' + id + '"]');
      if (cell) {
        document.getElementById('editNotesObjectId').value = id;
        document.getElementById('editNotesTextarea').value = cell.dataset.notes || '';
        document.getElementById('editNotesItemTitle').textContent = cell.dataset.title || 'Item';
      }
    });
  });

  document.getElementById('editNotesModal').addEventListener('shown.bs.modal', function() {
    document.getElementById('editNotesTextarea').focus();
  });
});
</script>
@endpush
@endsection
