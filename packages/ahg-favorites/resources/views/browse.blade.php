@extends('theme::layouts.1col')
@section('title', 'Favorites')
@section('body-class', 'favorites')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1><i class="fas fa-heart me-2"></i>Favorites <span class="badge bg-primary">{{ $totalCount }}</span></h1>
  <div class="d-flex gap-2 align-items-center">
    {{-- Export Dropdown --}}
    @if($totalCount > 0)
      <div class="dropdown">
        <button class="btn atom-btn-white dropdown-toggle" data-bs-toggle="dropdown">
          <i class="fas fa-download me-1"></i>Export
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="{{ route('favorites.export.csv', request()->query()) }}"><i class="fas fa-file-csv me-2 text-success"></i>CSV</a></li>
          <li><a class="dropdown-item" href="{{ route('favorites.export.json', request()->query()) }}"><i class="fas fa-file-code me-2 text-warning"></i>JSON</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="{{ route('favorites.export.csv', array_merge(request()->query(), ['format' => 'print'])) }}" target="_blank"><i class="fas fa-print me-2"></i>Print</a></li>
        </ul>
      </div>
    @endif

    {{-- Import --}}
    <button class="btn atom-btn-white" data-bs-toggle="modal" data-bs-target="#importModal">
      <i class="fas fa-upload me-1"></i>Import
    </button>

    {{-- View Toggle --}}
    <div class="btn-group btn-group-sm">
      <a href="{{ route('favorites.browse', array_merge($params, ['view' => 'table'])) }}" class="btn {{ ($params['view'] ?? 'table') === 'table' ? 'atom-btn-outline-success' : 'atom-btn-white' }}" title="Table View">
        <i class="fas fa-list"></i>
      </a>
      <a href="{{ route('favorites.browse', array_merge($params, ['view' => 'grid'])) }}" class="btn {{ ($params['view'] ?? 'table') === 'grid' ? 'atom-btn-outline-success' : 'atom-btn-white' }}" title="Grid View">
        <i class="fas fa-th"></i>
      </a>
    </div>

    {{-- Clear All --}}
    @if($totalCount > 0)
      <form method="post" action="{{ route('favorites.clear') }}" onsubmit="return confirm('Are you sure you want to clear all favorites?');">
        @csrf
        <button type="submit" class="btn atom-btn-white btn-sm">
          <i class="fas fa-trash-alt me-1"></i>Clear All
        </button>
      </form>
    @endif
  </div>
</div>

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="row">
  {{-- Sidebar: Folders --}}
  <div class="col-lg-3 col-md-4">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <span><i class="fas fa-folder me-1"></i> Folders</span>
        <button class="btn btn-sm atom-btn-white" data-bs-toggle="modal" data-bs-target="#newFolderModal"><i class="fas fa-plus"></i></button>
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('favorites.browse') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center{{ empty($params['folder_id']) && empty($params['unfiled']) ? ' active' : '' }}">
          <span><i class="fas fa-heart me-2"></i>All Favorites</span>
          <span class="badge bg-primary rounded-pill">{{ $totalCount }}</span>
        </a>
        <a href="{{ route('favorites.browse', ['unfiled' => 1]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center{{ !empty($params['unfiled']) ? ' active' : '' }}">
          <span><i class="fas fa-inbox me-2"></i>Unfiled</span>
          <span class="badge bg-secondary rounded-pill">{{ $unfiledCount }}</span>
        </a>
        @if(!empty($folders) && count($folders) > 0)
          <li class="list-group-item px-3 py-1 bg-light"><small class="text-muted text-uppercase">My Folders</small></li>
          @foreach($folders as $folder)
            <a href="{{ route('favorites.browse', ['folder_id' => $folder->id]) }}"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center{{ ($params['folder_id'] ?? '') == $folder->id ? ' active' : '' }}">
              <span>
                <i class="fas fa-folder{{ ($params['folder_id'] ?? '') == $folder->id ? '-open' : '' }} me-2"></i>
                @if($folder->color)<i class="fas fa-circle me-1" style="color:{{ $folder->color }}; font-size:0.5em;"></i>@endif
                {{ $folder->name }}
                @if(!empty($folder->share_token))
                  <i class="fas fa-share-alt text-info ms-1" title="Shared"></i>
                @endif
              </span>
              <span class="badge bg-secondary rounded-pill">{{ $folder->item_count }}</span>
            </a>
          @endforeach
        @endif
      </div>
    </div>

    {{-- Folder Actions (when viewing a folder) --}}
    @if(isset($params['folder_id']) && $params['folder_id'])
      @php $activeFolder = $folders->firstWhere('id', $params['folder_id']); @endphp
      @if($activeFolder)
        <div class="card mb-3">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">Folder actions</div>
          <div class="card-body py-2">
            <h6 class="mb-2">{{ e($activeFolder->name) }}</h6>
            @if($activeFolder->description)
              <p class="text-muted small mb-2">{{ e($activeFolder->description) }}</p>
            @endif
            <div class="d-flex gap-1 flex-wrap">
              <button type="button" class="btn btn-sm atom-btn-white" data-bs-toggle="modal" data-bs-target="#editFolderModal">
                <i class="fas fa-edit"></i> Edit
              </button>
              @if(empty($activeFolder->share_token))
                <form method="post" action="{{ route('favorites.folder.share', $activeFolder->id) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-share-alt"></i> Share
                  </button>
                </form>
              @else
                <button type="button" class="btn btn-sm atom-btn-white text-white" data-bs-toggle="modal" data-bs-target="#shareInfoModal">
                  <i class="fas fa-share-alt"></i> Shared
                </button>
              @endif
              <form method="post" action="{{ route('favorites.folder.delete', $activeFolder->id) }}" class="d-inline"
                    onsubmit="return confirm('Delete this folder? Items will be moved to Unfiled.');">
                @csrf
                <button type="submit" class="btn btn-sm atom-btn-outline-danger">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </form>
            </div>
          </div>
        </div>
      @endif
    @endif
  </div>

  {{-- Main Content --}}
  <div class="col-lg-9 col-md-8">
    {{-- Search + Sort Bar --}}
    <form method="get" action="{{ route('favorites.browse') }}" class="row g-2 mb-3 align-items-end">
      @if(!empty($params['folder_id']))<input type="hidden" name="folder_id" value="{{ $params['folder_id'] }}">@endif
      @if(!empty($params['unfiled']))<input type="hidden" name="unfiled" value="1">@endif
      @if(($params['view'] ?? 'table') !== 'table')<input type="hidden" name="view" value="{{ $params['view'] }}">@endif

      <div class="col-md-5">
        <div class="input-group input-group-sm">
          <input type="text" name="query" class="form-control" placeholder="Search favorites..." value="{{ $params['query'] ?? '' }}">
          <button class="btn atom-btn-outline-light" type="submit"><i class="fas fa-search"></i></button>
          @if(!empty($params['query']))
            <a href="{{ route('favorites.browse', array_diff_key($params, ['query' => ''])) }}" class="btn atom-btn-outline-danger" title="Clear search"><i class="fas fa-times"></i></a>
          @endif
        </div>
      </div>
      <div class="col-md-4">
        <select name="sort" class="form-select form-select-sm" onchange="this.form.submit();">
          @foreach(['created_at' => 'Date Added (Newest)', 'title' => 'Title (A-Z)', 'reference_code' => 'Reference Code (A-Z)'] as $val => $label)
            <option value="{{ $val }}" {{ ($params['sort'] ?? 'created_at') === $val ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3 text-end">
        <small class="text-muted">{{ $total }} {{ Str::plural('item', $total) }}</small>
      </div>
    </form>

    {{-- Bulk Action Bar --}}
    <div id="bulkActionBar" class="border rounded bg-light p-2 mb-2" style="display: none;">
      <form method="post" action="{{ route('favorites.bulk') }}" id="bulkForm">
        @csrf
        <div class="d-flex gap-2 align-items-center flex-wrap">
          <span id="selectedCount" class="text-muted small me-2">0 selected</span>
          <button type="submit" name="action" value="remove" class="btn btn-sm atom-btn-outline-danger"
                  onclick="return confirm('Remove selected favorites?');">
            <i class="fas fa-trash me-1"></i>Remove Selected
          </button>
          @if(!empty($folders) && count($folders) > 0)
            <div class="input-group input-group-sm" style="max-width: 250px;">
              <select name="move_folder_id" class="form-select form-select-sm">
                <option value="">Move to...</option>
                <option value="">Unfiled</option>
                @foreach($folders as $f)
                  <option value="{{ $f->id }}">{{ $f->name }}</option>
                @endforeach
              </select>
              <button type="submit" name="action" value="move" class="btn btn-sm atom-btn-white">
                <i class="fas fa-folder"></i> Move
              </button>
            </div>
          @endif
        </div>
        <div id="bulkIdsContainer"></div>
      </form>
    </div>

    @if($results->isEmpty())
      <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        @if(!empty($params['query']))
          No favorites match your search.
        @else
          You have no favorites yet. Browse the archive and click the heart icon to add items.
        @endif
      </div>
    @elseif(($params['view'] ?? 'table') === 'grid')
      {{-- Grid View --}}
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
        @foreach($results as $item)
          <div class="col">
            <div class="card h-100 border">
              @if(!empty($item->thumbnail_path))
                <img src="{{ $item->thumbnail_path }}" class="card-img-top" alt="" style="height: 120px; object-fit: cover;">
              @endif
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div class="form-check">
                    <input type="checkbox" class="form-check-input bulk-checkbox" value="{{ $item->id }}">
                  </div>
                  <div>
                    @if(!empty($item->has_digital_object))
                      <i class="fas fa-camera text-info me-1" title="Has digital object"></i>
                    @endif
                    <form method="post" action="{{ route('favorites.remove', $item->id) }}" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Remove"
                              onclick="return confirm('Remove from favorites?');">
                        <i class="fas fa-heart-broken"></i>
                      </button>
                    </form>
                  </div>
                </div>
                <h6 class="card-title">
                  <i class="{{ $item->type_icon ?? 'fas fa-file-alt' }} text-muted me-1"></i>
                  <a href="{{ url('/' . $item->slug) }}" class="text-decoration-none">{{ $item->archival_description }}</a>
                </h6>
                @if($item->reference_code)
                  <p class="card-text small text-muted mb-1"><i class="fas fa-barcode me-1"></i>{{ $item->reference_code }}</p>
                @endif
                @if(!empty($item->level_of_description))
                  <p class="card-text small text-muted mb-1"><i class="fas fa-layer-group me-1"></i>{{ $item->level_of_description }}</p>
                @endif
                @if(!empty($item->date_range))
                  <p class="card-text small text-muted mb-1"><i class="fas fa-calendar-alt me-1"></i>{{ $item->date_range }}</p>
                @endif
                @if(!empty($item->repository_name))
                  <p class="card-text small text-muted mb-1"><i class="fas fa-building me-1"></i>{{ $item->repository_name }}</p>
                @endif
                <p class="card-text small text-muted mb-0">
                  <i class="far fa-clock me-1"></i>{{ \Carbon\Carbon::parse($item->created_at)->format('Y-m-d') }}
                </p>
              </div>
              @if($item->notes)
                <div class="card-footer bg-light small">
                  <i class="fas fa-sticky-note me-1"></i>{{ e($item->notes) }}
                </div>
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @else
      {{-- Table View --}}
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm align-middle mb-0">
          <thead>
            <tr style="background:var(--ahg-primary);color:#fff">
              <th style="width:30px"><input type="checkbox" id="selectAll" class="form-check-input" title="Select all"></th>
              <th>Title</th>
              <th style="width:140px">Reference Code</th>
              <th class="text-center" style="width:110px">Level</th>
              <th class="text-center col-optional col-dates" style="width:130px">Dates</th>
              <th class="text-center col-optional col-repository" style="width:150px">Repository</th>
              <th class="text-center" style="width:50px" title="Digital Object"><i class="fas fa-camera"></i></th>
              <th class="text-center" style="width:110px">Date Added</th>
              <th class="text-center" style="width:60px">Notes</th>
              <th class="text-center" style="width:100px">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($results as $item)
              <tr>
                <td><input type="checkbox" name="ids[]" value="{{ $item->id }}" class="form-check-input bulk-checkbox"></td>
                <td>
                  <a href="{{ url('/' . $item->slug) }}" class="text-decoration-none">
                    <i class="{{ $item->type_icon ?? 'fas fa-file-alt' }} text-muted me-2"></i>
                    {{ $item->archival_description }}
                  </a>
                </td>
                <td class="small text-muted"><code>{{ $item->reference_code ?? '' }}</code></td>
                <td class="text-center small text-muted">{{ $item->level_of_description ?? '' }}</td>
                <td class="text-center small text-muted col-optional col-dates">{{ $item->date_range ?? '' }}</td>
                <td class="text-center small text-muted col-optional col-repository">{{ $item->repository_name ?? '' }}</td>
                <td class="text-center">
                  @if(!empty($item->has_digital_object))
                    <i class="fas fa-camera text-info" title="Has digital object"></i>
                  @endif
                </td>
                <td class="text-center text-muted small">{{ \Carbon\Carbon::parse($item->created_at)->format('Y-m-d') }}</td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm atom-btn-white notes-toggle" data-fav-id="{{ $item->id }}" title="Notes">
                    <i class="fas fa-sticky-note{{ $item->notes ? ' text-warning' : '' }}"></i>
                  </button>
                </td>
                <td class="text-center">
                  <a href="{{ url('/' . $item->slug) }}" class="btn btn-sm atom-btn-white me-1" title="View">
                    <i class="fas fa-eye"></i>
                  </a>
                  <form method="post" action="{{ route('favorites.remove', $item->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Remove"
                            onclick="return confirm('Remove from favorites?');">
                      <i class="fas fa-heart-broken"></i>
                    </button>
                  </form>
                </td>
              </tr>
              {{-- Expandable notes row --}}
              <tr class="notes-row" id="notes-row-{{ $item->id }}" style="display: none;">
                <td></td>
                <td colspan="9">
                  <div class="input-group input-group-sm">
                    <input type="text" class="form-control notes-input" id="notes-input-{{ $item->id }}"
                           placeholder="Add a note..." value="{{ e($item->notes ?? '') }}">
                    <button class="btn atom-btn-white notes-save" data-fav-id="{{ $item->id }}" type="button">
                      <i class="fas fa-save"></i> Save
                    </button>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if(!$results->isEmpty())
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
        <div class="modal-header"><h5 class="modal-title"><i class="fas fa-folder-plus me-2"></i>New Folder</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Folder Name <span class="badge bg-danger ms-1">Required</span></label><input type="text" name="name" class="form-control" required maxlength="255"></div>
          <div class="mb-3"><label class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label><textarea name="description" class="form-control" rows="2"></textarea></div>
          <div class="mb-3"><label class="form-label">Color <span class="badge bg-secondary ms-1">Optional</span></label><input type="color" name="color" class="form-control form-control-color" value="#0d6efd"></div>
          @if(!empty($folders) && count($folders) > 0)
            <div class="mb-3">
              <label class="form-label">Parent Folder <span class="badge bg-secondary ms-1">Optional</span></label>
              <select class="form-select" name="parent_id">
                <option value="">None (top level)</option>
                @foreach($folders as $f)
                  @if(!$f->parent_id)
                    <option value="{{ $f->id }}">{{ $f->name }}</option>
                  @endif
                @endforeach
              </select>
            </div>
          @endif
        </div>
        <div class="modal-footer"><button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-folder-plus me-1"></i>Create Folder</button></div>
      </div>
    </form>
  </div>
</div>

{{-- Import Modal --}}
<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="{{ route('favorites.import') }}" enctype="multipart/form-data">
      @csrf
      @if(!empty($params['folder_id']))<input type="hidden" name="folder_id" value="{{ $params['folder_id'] }}">@endif
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="fas fa-upload me-2"></i>Import Favorites</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Upload CSV <span class="badge bg-secondary ms-1">Optional</span></label><input type="file" name="file" class="form-control" accept=".csv,.txt"><small class="text-muted">CSV must contain a "slug" or "reference_code" column.</small></div>
          <div class="text-center text-muted my-2">&mdash; or &mdash;</div>
          <div class="mb-3"><label class="form-label">Paste Slugs <span class="badge bg-secondary ms-1">Optional</span></label><textarea name="slugs" class="form-control" rows="4" placeholder="One slug per line, or comma-separated..."></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-upload me-1"></i>Import</button></div>
      </div>
    </form>
  </div>
</div>

{{-- Edit Folder Modal --}}
@if(isset($activeFolder) && $activeFolder)
<div class="modal fade" id="editFolderModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="{{ route('favorites.folder.edit', $activeFolder->id) }}">
      @csrf
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Folder</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Folder Name <span class="badge bg-danger ms-1">Required</span></label><input type="text" name="name" class="form-control" value="{{ e($activeFolder->name) }}" required maxlength="255"></div>
          <div class="mb-3"><label class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label><textarea name="description" class="form-control" rows="2">{{ e($activeFolder->description ?? '') }}</textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save Changes</button></div>
      </div>
    </form>
  </div>
</div>

@if(!empty($activeFolder->share_token))
<div class="modal fade" id="shareInfoModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-share-alt me-2"></i>Folder is Shared</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Share Link <span class="badge bg-secondary ms-1">Optional</span></label>
          <div class="input-group">
            <input type="text" class="form-control" readonly value="{{ url('/favorites/shared/' . $activeFolder->share_token) }}">
            <button type="button" class="btn atom-btn-white" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.innerHTML='<i class=\'fas fa-check\'></i>';">
              <i class="fas fa-copy"></i>
            </button>
          </div>
        </div>
        @if($activeFolder->share_expires_at)
          <p class="text-muted small">Expires: {{ $activeFolder->share_expires_at }}</p>
        @endif
      </div>
      <div class="modal-footer">
        <form action="{{ route('favorites.folder.revoke', $activeFolder->id) }}" method="post" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-danger" onclick="return confirm('Revoke sharing? The link will no longer work.');">
            <i class="fas fa-ban me-1"></i>Revoke Sharing
          </button>
        </form>
        <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endif
@endif

{{-- Column Config Dropdown --}}
<div class="dropdown position-fixed" style="bottom: 20px; right: 20px; z-index: 1050;">
  <button class="btn btn-sm atom-btn-white rounded-circle shadow" type="button" data-bs-toggle="dropdown" title="Column Settings" style="width:36px;height:36px;">
    <i class="fas fa-columns"></i>
  </button>
  <ul class="dropdown-menu dropdown-menu-end">
    <li><h6 class="dropdown-header">Optional Columns</h6></li>
    <li>
      <label class="dropdown-item">
        <input type="checkbox" class="form-check-input me-2 col-toggle" data-col="col-dates" checked>
        Dates
      </label>
    </li>
    <li>
      <label class="dropdown-item">
        <input type="checkbox" class="form-check-input me-2 col-toggle" data-col="col-repository" checked>
        Repository
      </label>
    </li>
  </ul>
</div>

@push('scripts')
<script>
(function() {
    // Select All checkbox
    var selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.bulk-checkbox').forEach(function(cb) { cb.checked = selectAll.checked; });
            updateBulkBar();
        });
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('bulk-checkbox')) updateBulkBar();
    });

    function updateBulkBar() {
        var checked = document.querySelectorAll('.bulk-checkbox:checked');
        var bar = document.getElementById('bulkActionBar');
        var countEl = document.getElementById('selectedCount');
        var container = document.getElementById('bulkIdsContainer');

        if (checked.length > 0) {
            bar.style.display = 'block';
            countEl.textContent = checked.length + ' selected';
            container.innerHTML = '';
            checked.forEach(function(cb) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });
        } else {
            bar.style.display = 'none';
        }
    }

    // Notes toggle
    document.querySelectorAll('.notes-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var favId = btn.getAttribute('data-fav-id');
            var row = document.getElementById('notes-row-' + favId);
            if (row) {
                row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
                if (row.style.display === 'table-row') {
                    var input = document.getElementById('notes-input-' + favId);
                    if (input) input.focus();
                }
            }
        });
    });

    // Notes save (AJAX)
    document.querySelectorAll('.notes-save').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var favId = btn.getAttribute('data-fav-id');
            var input = document.getElementById('notes-input-' + favId);
            if (!input) return;
            fetch('{{ url("/favorites/notes") }}/' + favId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({notes: input.value})
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var toggleBtn = document.querySelector('.notes-toggle[data-fav-id="' + favId + '"] i');
                    if (toggleBtn) {
                        if (input.value) { toggleBtn.classList.add('text-warning'); }
                        else { toggleBtn.classList.remove('text-warning'); }
                    }
                    input.classList.add('is-valid');
                    setTimeout(function() { input.classList.remove('is-valid'); }, 1500);
                }
            });
        });
    });

    // Column toggle
    document.querySelectorAll('.col-toggle').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var col = this.getAttribute('data-col');
            document.querySelectorAll('.' + col).forEach(function(el) {
                el.style.display = cb.checked ? '' : 'none';
            });
        });
    });
})();
</script>
@endpush
@endsection
