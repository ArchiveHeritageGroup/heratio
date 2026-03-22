@extends('theme::layouts.2col')

@section('sidebar')
<div class="sidebar-content">
    <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>{{ e($collection->display_name) }}</h5>
        </div>
        <div class="card-body">
            <a href="{{ route('iiif-collection.view', $collection->id) }}" class="btn atom-btn-white w-100">
                <i class="fas fa-arrow-left me-2"></i>Back to Collection
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-link me-2"></i>Add External Manifest</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('iiif-collection.add-items', $collection->id) }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Manifest URI <span class="badge bg-secondary ms-1">Optional</span></label>
                    <input type="url" class="form-control form-control-sm" name="manifest_uri" placeholder="https://...">
                </div>
                <div class="mb-3">
                    <label class="form-label">Label <span class="badge bg-secondary ms-1">Optional</span></label>
                    <input type="text" class="form-control form-control-sm" name="label">
                </div>
                <button type="submit" class="btn btn-sm atom-btn-outline-success w-100">
                    <i class="fas fa-plus me-2"></i>Add External
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-plus-circle me-2"></i>Add Items to Collection</h1>
<h2>{{ e($collection->display_name) }}</h2>
@endsection

@section('content')
<div class="add-items-form">
    <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search & Add Objects</h5>
        </div>
        <div class="card-body">
            <form method="POST" id="addItemsForm" action="{{ route('iiif-collection.add-items', $collection->id) }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Search for objects <span class="badge bg-secondary ms-1">Optional</span></label>
                    <input type="text" class="form-control" id="objectSearchInput"
                           placeholder="Type to search by title or identifier..."
                           autocomplete="off">
                    <div id="searchResults" class="list-group mt-2" style="max-height: 300px; overflow-y: auto;"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Selected Items <span class="badge bg-secondary ms-1">Optional</span></label>
                    <div id="selectedItems" class="border rounded p-2" style="min-height: 50px;">
                        <span class="text-muted" id="noSelection">No items selected</span>
                    </div>
                </div>

                <button type="submit" class="btn atom-btn-outline-success btn-lg" id="addBtn" disabled>
                    <i class="fas fa-plus me-2"></i>Add Selected Items to Collection
                </button>
            </form>
        </div>
    </div>

    @if(!empty($collection->items))
    <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Current Items ({{ count($collection->items) }})</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered table-sm table-hover mb-0">
                <thead>
                <tbody>
                    @foreach($collection->items as $item)
                    <tr>
                        <td>{{ e($item->label ?: $item->object_title ?: 'Untitled') }}</td>
                        <td><code>{{ e($item->identifier ?: '-') }}</code></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

<script>
(function() {
    var searchInput = document.getElementById('objectSearchInput');
    var searchResults = document.getElementById('searchResults');
    var selectedItems = document.getElementById('selectedItems');
    var noSelection = document.getElementById('noSelection');
    var addBtn = document.getElementById('addBtn');
    var selected = {};
    var searchTimeout;

    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);

        if (query.length < 2) {
            searchResults.innerHTML = '';
            return;
        }

        searchTimeout = setTimeout(function() {
            fetch('{{ route('iiif-collection.autocomplete') }}?q=' + encodeURIComponent(query))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    searchResults.innerHTML = '';
                    if (data.results && data.results.length > 0) {
                        data.results.forEach(function(item) {
                            if (!selected[item.id]) {
                                var div = document.createElement('a');
                                div.href = '#';
                                div.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                                var badges = '';
                                if (item.hasChildren) {
                                    badges += '<span class="badge bg-info ms-2"><i class="fas fa-sitemap me-1"></i>' + item.childCount + ' children</span>';
                                }
                                if (!item.hasDigital) {
                                    badges += '<span class="badge bg-warning ms-1">No image</span>';
                                }
                                div.innerHTML = '<span><strong>' + (item.title || 'Untitled') + '</strong>' +
                                    (item.identifier ? ' <code class="ms-2 small">' + item.identifier + '</code>' : '') +
                                    '</span><span>' + badges + '</span>';
                                div.onclick = function(e) {
                                    e.preventDefault();
                                    addToSelected(item);
                                    searchInput.value = '';
                                    searchResults.innerHTML = '';
                                };
                                searchResults.appendChild(div);
                            }
                        });
                    } else {
                        searchResults.innerHTML = '<div class="list-group-item text-muted">No results found</div>';
                    }
                })
                .catch(function(err) {
                    console.error('Search error:', err);
                    searchResults.innerHTML = '<div class="list-group-item text-danger">Error searching</div>';
                });
        }, 300);
    });

    function addToSelected(item) {
        if (selected[item.id]) return;
        selected[item.id] = item;
        noSelection.style.display = 'none';

        var card = document.createElement('div');
        card.className = 'card mb-2 selected-item-card';
        card.id = 'sel-' + item.id;
        card.innerHTML =
            '<div class="card-body p-2">' +
                '<div class="d-flex justify-content-between align-items-start">' +
                    '<div>' +
                        '<strong>' + (item.title || 'Untitled') + '</strong>' +
                        (item.identifier ? ' <code class="ms-2 small">' + item.identifier + '</code>' : '') +
                        (item.hasChildren ? '<span class="badge bg-info ms-2">Has children</span>' : '') +
                    '</div>' +
                    '<button type="button" class="btn btn-sm atom-btn-outline-danger remove-btn">' +
                        '<i class="fas fa-times"></i>' +
                    '</button>' +
                '</div>' +
                (item.hasChildren ?
                    '<div class="form-check mt-2">' +
                        '<input type="checkbox" class="form-check-input include-children" ' +
                               'name="include_children[]" value="' + item.id + '" id="children-' + item.id + '">' +
                        '<label class="form-check-label small" for="children-' + item.id + '">' +
                            '<i class="fas fa-sitemap me-1"></i>Include all children (' + item.childCount + ' items)' +
                        ' <span class="badge bg-secondary ms-1">Optional</span></label>' +
                    '</div>'
                : '') +
                '<input type="hidden" name="object_ids[]" value="' + item.id + '">' +
            '</div>';
        card.querySelector('.remove-btn').onclick = function() {
            delete selected[item.id];
            card.remove();
            updateUI();
        };
        selectedItems.appendChild(card);
        updateUI();
    }

    function updateUI() {
        var count = Object.keys(selected).length;
        addBtn.disabled = count === 0;
        noSelection.style.display = count === 0 ? '' : 'none';
    }
})();
</script>

<style>
#selectedItems { display: flex; flex-direction: column; gap: 8px; }
#searchResults .list-group-item:hover { background: #e9ecef; }
.selected-item-card { border: 1px solid var(--ahg-primary, #005837); background: #f8fff8; }
.selected-item-card .form-check { background: #e8f5e9; padding: 8px 12px; border-radius: 4px; margin: 0 -8px -8px -8px; }
</style>
@endsection
