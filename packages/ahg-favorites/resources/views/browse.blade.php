@extends('theme::layouts.1col')
@section('title', 'Favorites')
@section('body-class', 'favorites')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1><i class="fas fa-heart me-2"></i>Favorites <span class="badge bg-primary">{{ $totalCount }}</span></h1>
  <div class="dropdown">
    <button class="btn atom-btn-white dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
    <ul class="dropdown-menu dropdown-menu-end">
      <li><a class="dropdown-item" href="{{ route('favorites.export.csv', request()->query()) }}"><i class="fas fa-file-csv me-2"></i>Export CSV</a></li>
      <li><a class="dropdown-item" href="{{ route('favorites.export.json', request()->query()) }}"><i class="fas fa-file-code me-2"></i>Export JSON</a></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fas fa-file-import me-2"></i>Import</a></li>
    </ul>
  </div>
</div>

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row">
  {{-- Sidebar: Folders --}}
  <div class="col-md-3">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <span>Folders</span>
        <button class="btn btn-sm atom-btn-white" data-bs-toggle="modal" data-bs-target="#newFolderModal"><i class="fas fa-plus"></i></button>
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('favorites.browse') }}" class="list-group-item list-group-item-action d-flex justify-content-between{{ empty($params['folder_id']) && empty($params['unfiled']) ? ' active' : '' }}">
          All Favorites <span class="badge bg-primary">{{ $totalCount }}</span>
        </a>
        <a href="{{ route('favorites.browse', ['unfiled' => 1]) }}" class="list-group-item list-group-item-action d-flex justify-content-between{{ !empty($params['unfiled']) ? ' active' : '' }}">
          Unfiled <span class="badge bg-secondary">{{ $unfiledCount }}</span>
        </a>
        @foreach($folders as $folder)
          <a href="{{ route('favorites.browse', ['folder_id' => $folder->id]) }}" class="list-group-item list-group-item-action d-flex justify-content-between{{ ($params['folder_id'] ?? '') == $folder->id ? ' active' : '' }}">
            <span>
              @if($folder->color)<i class="fas fa-circle me-1" style="color:{{ $folder->color }}; font-size:0.6em;"></i>@endif
              {{ $folder->name }}
            </span>
            <span class="badge bg-secondary">{{ $folder->item_count }}</span>
          </a>
        @endforeach
      </div>
    </div>

    @if(isset($params['folder_id']) && $params['folder_id'])
      @php $activeFolder = $folders->firstWhere('id', $params['folder_id']); @endphp
      @if($activeFolder)
        <div class="card mb-3">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">Folder actions</div>
          <div class="card-body">
            <form method="post" action="{{ route('favorites.folder.edit', $activeFolder->id) }}" class="mb-2">
              @csrf
              <input type="text" name="name" class="form-control form-control-sm mb-2" value="{{ e($activeFolder->name) }}">
              <button type="submit" class="btn btn-sm atom-btn-white w-100">Rename</button>
            </form>
            @if($activeFolder->share_token)
              <div class="alert alert-info small mb-2">
                Shared: <code>{{ url('/favorites/shared/' . $activeFolder->share_token) }}</code>
              </div>
              <form method="post" action="{{ route('favorites.folder.revoke', $activeFolder->id) }}" class="mb-2">
                @csrf
                <button type="submit" class="btn btn-sm atom-btn-white w-100">Revoke sharing</button>
              </form>
            @else
              <form method="post" action="{{ route('favorites.folder.share', $activeFolder->id) }}" class="mb-2">
                @csrf
                <button type="submit" class="btn btn-sm atom-btn-white w-100"><i class="fas fa-share me-1"></i>Share folder</button>
              </form>
            @endif
            <form method="post" action="{{ route('favorites.folder.delete', $activeFolder->id) }}" onsubmit="return confirm('Delete folder? Items will be moved to unfiled.')">
              @csrf
              <button type="submit" class="btn btn-sm atom-btn-outline-danger w-100"><i class="fas fa-trash me-1"></i>Delete folder</button>
            </form>
          </div>
        </div>
      @endif
    @endif
  </div>

  {{-- Main content --}}
  <div class="col-md-9">
    {{-- Search bar --}}
    <form method="get" action="{{ route('favorites.browse') }}" class="d-flex mb-3">
      @if(!empty($params['folder_id']))<input type="hidden" name="folder_id" value="{{ $params['folder_id'] }}">@endif
      @if(!empty($params['unfiled']))<input type="hidden" name="unfiled" value="1">@endif
      <input type="text" name="query" class="form-control form-control-sm me-2" placeholder="Search favorites..." value="{{ $params['query'] ?? '' }}">
      <select name="sort" class="form-select form-select-sm me-2" style="width:auto">
        <option value="created_at" {{ ($params['sort'] ?? '') === 'created_at' ? 'selected' : '' }}>Date added</option>
        <option value="title" {{ ($params['sort'] ?? '') === 'title' ? 'selected' : '' }}>Title</option>
        <option value="reference_code" {{ ($params['sort'] ?? '') === 'reference_code' ? 'selected' : '' }}>Reference code</option>
      </select>
      <button type="submit" class="btn atom-btn-outline-light btn-sm"><i class="fas fa-search"></i></button>
    </form>

    @if($results->isEmpty())
      <div class="alert alert-info">No favorites found.</div>
    @else
      <form method="post" action="{{ route('favorites.bulk') }}" id="bulkForm">
        @csrf
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <input type="checkbox" id="selectAll" class="form-check-input me-2">
            <select name="action" class="form-select form-select-sm d-inline-block" style="width:auto">
              <option value="">Bulk actions...</option>
              <option value="remove">Remove selected</option>
              <option value="move">Move to folder...</option>
            </select>
            <select name="move_folder_id" class="form-select form-select-sm d-inline-block" style="width:auto">
              <option value="">Unfiled</option>
              @foreach($folders as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
            </select>
            <button type="submit" class="btn btn-sm atom-btn-white">Apply</button>
          </div>
          <span class="text-muted small">{{ $total }} {{ Str::plural('item', $total) }}</span>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped table-sm">
            <thead>
              <tr style="background:var(--ahg-primary);color:#fff">
                <th style="width:30px"></th>
                <th>Title</th>
                <th>Reference code</th>
                <th>Type</th>
                <th>Date added</th>
                <th>Notes</th>
                <th style="width:60px">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($results as $item)
                <tr>
                  <td><input type="checkbox" name="ids[]" value="{{ $item->id }}" class="form-check-input bulk-check"></td>
                  <td><a href="{{ url('/' . $item->slug) }}">{{ $item->archival_description }}</a></td>
                  <td><code>{{ $item->reference_code ?? '' }}</code></td>
                  <td><span class="badge bg-light text-dark">{{ $item->object_type ?? '' }}</span></td>
                  <td>{{ \Carbon\Carbon::parse($item->created_at)->format('Y-m-d') }}</td>
                  <td>
                    <input type="text" class="form-control form-control-sm notes-input" data-id="{{ $item->id }}" value="{{ e($item->notes ?? '') }}" placeholder="Add notes...">
                  </td>
                  <td>
                    <form method="post" action="{{ route('favorites.remove', $item->id) }}" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Remove"><i class="fas fa-times"></i></button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </form>

      @include('ahg-reports::_pagination')
    @endif
  </div>
</div>

{{-- New Folder Modal --}}
<div class="modal fade" id="newFolderModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="{{ route('favorites.folder.create') }}">
      @csrf
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">New Folder</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
          <div class="mb-3"><label class="form-label">Color</label><input type="color" name="color" class="form-control form-control-color" value="#0d6efd"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn atom-btn-outline-success">Create</button></div>
      </div>
    </form>
  </div>
</div>

{{-- Import Modal --}}
<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="{{ route('favorites.import') }}" enctype="multipart/form-data">
      @csrf
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Import Favorites</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">CSV file</label><input type="file" name="file" class="form-control" accept=".csv,.txt"></div>
          <div class="mb-3"><label class="form-label">Or paste slugs (one per line)</label><textarea name="slugs" class="form-control" rows="4"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn atom-btn-outline-success">Import</button></div>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
  document.querySelectorAll('.bulk-check').forEach(cb => cb.checked = this.checked);
});

document.querySelectorAll('.notes-input').forEach(input => {
  let timer;
  input.addEventListener('input', function() {
    clearTimeout(timer);
    const id = this.dataset.id;
    const notes = this.value;
    timer = setTimeout(() => {
      fetch('{{ url("/favorites/notes") }}/' + id, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        body: JSON.stringify({notes})
      });
    }, 800);
  });
});
</script>
@endsection
