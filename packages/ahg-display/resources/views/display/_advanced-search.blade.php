@php
  $params = request()->all();
  $showAdvanced = ($params['showAdvanced'] ?? '') === '1';
  $currentType = $params['type'] ?? '';

  // Get levels of description
  $levels = \Illuminate\Support\Facades\DB::table('term')
      ->join('term_i18n', function ($j) { $j->on('term.id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', 'en'); })
      ->where('term.taxonomy_id', 34)->orderBy('term_i18n.name')
      ->select('term.id', 'term_i18n.name')->get();

  // Get repositories
  $repositories = \Illuminate\Support\Facades\DB::table('repository')
      ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
      ->where('actor_i18n.culture', 'en')
      ->whereNotNull('actor_i18n.authorized_form_of_name')
      ->where('actor_i18n.authorized_form_of_name', '!=', '')
      ->orderBy('actor_i18n.authorized_form_of_name')
      ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')->get();

  // Saved searches (for authenticated users)
  $savedSearches = collect();
  if (auth()->check()) {
      try {
          $savedSearches = \Illuminate\Support\Facades\DB::table('research_saved_search')
              ->where('user_id', auth()->id())->orderByDesc('created_at')->limit(10)->get();
      } catch (\Exception $e) {}
  }
@endphp

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

<div class="accordion mb-3" id="glamAdvancedSearchAccordion">
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button{{ $showAdvanced ? '' : ' collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#glamAdvancedSearchPanel">
        <i class="fas fa-sliders-h me-2"></i>Advanced search options
      </button>
    </h2>
    <div id="glamAdvancedSearchPanel" class="accordion-collapse collapse{{ $showAdvanced ? ' show' : '' }}">
      <div class="accordion-body">
        <form method="get" action="{{ url('/glam/browse') }}" id="glam-advanced-search-form">

          {{-- Sector Quick Filter Buttons --}}
          <div class="mb-4">
            <label class="form-label fw-bold"><i class="fas fa-layer-group me-1"></i>Search in sector</label>
            <div class="d-flex flex-wrap gap-2">
              <a href="{{ url('/glam/browse?showAdvanced=1') }}" class="btn {{ empty($currentType) ? 'btn-secondary' : 'btn-outline-secondary' }}"><i class="fas fa-globe me-1"></i>All</a>
              <a href="{{ url('/glam/browse?type=archive&showAdvanced=1') }}" class="btn {{ $currentType === 'archive' ? 'btn-success' : 'btn-outline-success' }}"><i class="fas fa-archive me-1"></i>Archive</a>
              <a href="{{ url('/glam/browse?type=library&showAdvanced=1') }}" class="btn {{ $currentType === 'library' ? 'btn-info text-white' : 'btn-outline-info' }}"><i class="fas fa-book me-1"></i>Library</a>
              <a href="{{ url('/glam/browse?type=museum&showAdvanced=1') }}" class="btn {{ $currentType === 'museum' ? 'btn-warning' : 'btn-outline-warning' }}"><i class="fas fa-landmark me-1"></i>Museum</a>
              <a href="{{ url('/glam/browse?type=gallery&showAdvanced=1') }}" class="btn {{ $currentType === 'gallery' ? 'btn-danger' : 'btn-outline-danger' }}"><i class="fas fa-palette me-1"></i>Gallery</a>
              <a href="{{ url('/glam/browse?type=dam&showAdvanced=1') }}" class="btn {{ $currentType === 'dam' ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-images me-1"></i>Photos</a>
            </div>
          </div>

          {{-- Tab navigation --}}
          <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#adv-basic" type="button">Basic</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#adv-content" type="button">Content</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#adv-access" type="button">Access Points</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#adv-dates" type="button">Dates</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#adv-filters" type="button">Filters</button></li>
          </ul>

          <div class="tab-content">
            {{-- Basic Tab --}}
            <div class="tab-pane fade show active" id="adv-basic">
              <div class="row g-3">
                <div class="col-md-6"><label class="form-label small fw-bold">Any field</label><input type="text" name="query" class="form-control" value="{{ $params['query'] ?? '' }}"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Title</label><input type="text" name="title" class="form-control" value="{{ $params['title'] ?? '' }}"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Identifier</label><input type="text" name="identifier" class="form-control" value="{{ $params['identifier'] ?? '' }}"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Reference code</label><input type="text" name="referenceCode" class="form-control" value="{{ $params['referenceCode'] ?? '' }}"></div>
              </div>
            </div>

            {{-- Content Tab --}}
            <div class="tab-pane fade" id="adv-content">
              <div class="row g-3">
                <div class="col-md-6"><label class="form-label small fw-bold">Scope and content</label><input type="text" name="scopeAndContent" class="form-control" value="{{ $params['scopeAndContent'] ?? '' }}"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Extent and medium</label><input type="text" name="extentAndMedium" class="form-control" value="{{ $params['extentAndMedium'] ?? '' }}"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Archival history</label><input type="text" name="archivalHistory" class="form-control" value="{{ $params['archivalHistory'] ?? '' }}"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Acquisition</label><input type="text" name="acquisition" class="form-control" value="{{ $params['acquisition'] ?? '' }}"></div>
              </div>
            </div>

            {{-- Access Points Tab --}}
            <div class="tab-pane fade" id="adv-access">
              <div class="row g-3">
                <div class="col-md-6"><label class="form-label small fw-bold">Creator</label><input type="text" name="creatorSearch" class="form-control" value="{{ $params['creatorSearch'] ?? '' }}"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Subject</label><input type="text" name="subjectSearch" class="form-control" value="{{ $params['subjectSearch'] ?? '' }}"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Place</label><input type="text" name="placeSearch" class="form-control" value="{{ $params['placeSearch'] ?? '' }}"></div>
                <div class="col-md-6"><label class="form-label small fw-bold">Genre</label><input type="text" name="genreSearch" class="form-control" value="{{ $params['genreSearch'] ?? '' }}"></div>
              </div>
            </div>

            {{-- Dates Tab --}}
            <div class="tab-pane fade" id="adv-dates">
              <div class="row g-3">
                <div class="col-md-4"><label class="form-label small fw-bold">Date from</label><input type="date" name="startDate" class="form-control" value="{{ $params['startDate'] ?? '' }}"></div>
                <div class="col-md-4"><label class="form-label small fw-bold">Date to</label><input type="date" name="endDate" class="form-control" value="{{ $params['endDate'] ?? '' }}"></div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Date matching</label>
                  <select name="rangeType" class="form-select">
                    <option value="inclusive" {{ ($params['rangeType'] ?? '') === 'inclusive' ? 'selected' : '' }}>Overlapping</option>
                    <option value="exact" {{ ($params['rangeType'] ?? '') === 'exact' ? 'selected' : '' }}>Exact</option>
                  </select>
                </div>
              </div>
            </div>

            {{-- Filters Tab --}}
            <div class="tab-pane fade" id="adv-filters">
              {{-- Search specific field --}}
              <div class="mb-3 p-3 bg-light rounded">
                <label class="form-label small fw-bold"><i class="fas fa-search me-1"></i>Search specific field</label>
                <div id="field-search-rows">
                  <div class="input-group mb-2 field-search-row">
                    <select class="form-select field-select" style="max-width: 200px;" onchange="this.nextElementSibling.name = this.value">
                      <option value="" selected>-- Select field --</option>
                      <option value="title">Title</option>
                      <option value="identifier">Identifier</option>
                      <option value="referenceCode">Reference code</option>
                      <option value="scopeAndContent">Scope and content</option>
                      <option value="extentAndMedium">Extent and medium</option>
                      <option value="archivalHistory">Archival history</option>
                      <option value="acquisition">Acquisition</option>
                      <option value="creatorSearch">Creator</option>
                      <option value="subjectSearch">Subject</option>
                      <option value="placeSearch">Place</option>
                      <option value="genreSearch">Genre</option>
                    </select>
                    <input type="text" name="" class="form-control" value="" placeholder="Enter search term...">
                  </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="add-field-search-btn">
                  <i class="fas fa-plus me-1"></i>Add criterion
                </button>
              </div>

              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Sector</label>
                  <select name="type" class="form-select" id="sector-filter-select">
                    <option value="">All sectors</option>
                    <option value="archive" {{ $currentType === 'archive' ? 'selected' : '' }}>Archive</option>
                    <option value="library" {{ $currentType === 'library' ? 'selected' : '' }}>Library</option>
                    <option value="museum" {{ $currentType === 'museum' ? 'selected' : '' }}>Museum</option>
                    <option value="gallery" {{ $currentType === 'gallery' ? 'selected' : '' }}>Gallery</option>
                    <option value="dam" {{ $currentType === 'dam' ? 'selected' : '' }}>Photos</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Level of description</label>
                  <select name="level" class="form-select" id="level-filter-select">
                    <option value="">Any level</option>
                    @foreach($levels as $level)
                      <option value="{{ $level->id }}" {{ ($params['level'] ?? '') == $level->id ? 'selected' : '' }}>{{ $level->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Repository</label>
                  <select name="repo" id="repo-select">
                    <option value="">Any repository</option>
                    @foreach($repositories as $repo)
                      <option value="{{ $repo->id }}" {{ ($params['repo'] ?? '') == $repo->id ? 'selected' : '' }}>{{ $repo->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Digital objects</label>
                  <select name="hasDigital" class="form-select">
                    <option value="">Any</option>
                    <option value="1" {{ ($params['hasDigital'] ?? '') === '1' ? 'selected' : '' }}>With digital objects</option>
                    <option value="0" {{ ($params['hasDigital'] ?? '') === '0' ? 'selected' : '' }}>Without digital objects</option>
                  </select>
                </div>
                <div class="col-12">
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="topLevel" id="topLevel-all" value="0" {{ ($params['topLevel'] ?? '0') === '0' ? 'checked' : '' }}>
                    <label class="form-check-label" for="topLevel-all">All descriptions</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="topLevel" id="topLevel-top" value="1" {{ ($params['topLevel'] ?? '') === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="topLevel-top">Top-level only</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <input type="hidden" name="showAdvanced" value="1">

          <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top">
            <a href="{{ url('/glam/browse') }}" class="btn btn-outline-secondary"><i class="fas fa-undo me-1"></i>Reset</a>
            <button type="submit" class="btn btn-success"><i class="fas fa-search me-1"></i>Search</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Saved searches + Save button --}}
@auth
<div class="d-flex align-items-center flex-wrap gap-2 mb-3">
  @if($savedSearches->isNotEmpty())
    <div class="dropdown">
      <button class="btn btn-sm btn-outline-success dropdown-toggle py-0 px-2" type="button" data-bs-toggle="dropdown">
        <i class="fas fa-bookmark me-1"></i>Saved Searches ({{ $savedSearches->count() }})
      </button>
      <ul class="dropdown-menu">
        @foreach($savedSearches as $ss)
          <li><a class="dropdown-item" href="{{ url('/glam/browse?' . ($ss->query_string ?? '')) }}"><i class="fas fa-search me-2 text-muted"></i>{{ $ss->name ?? 'Saved search' }}</a></li>
        @endforeach
      </ul>
    </div>
  @endif
  @if(request()->has('query') || request()->has('title') || request()->has('type'))
    <button type="button" class="btn btn-sm btn-success py-0 px-2" data-bs-toggle="modal" data-bs-target="#saveGlamSearchModal">
      <i class="fas fa-bookmark me-1"></i>Save Search
    </button>
  @endif
</div>
@endauth

{{-- "Only top-level descriptions" active filter badge --}}
@if(request('topLevel') === '1')
  <div class="d-flex flex-wrap gap-2 mb-3">
    @php $removeTop = request()->except(['topLevel']); @endphp
    <a href="{{ url('/glam/browse?' . http_build_query(array_merge($removeTop, ['topLevel' => '0']))) }}" class="badge bg-primary p-2 text-decoration-none text-white">
      Only top-level descriptions <i class="fas fa-times ms-1"></i>
    </a>
  </div>
@endif

{{-- Save Search Modal --}}
@auth
<div class="modal fade" id="saveGlamSearchModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Save This Search</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Name *</label>
          <input type="text" id="glam-save-search-name" class="form-control" required>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="glam-save-search-public">
          <label class="form-check-label" for="glam-save-search-public"><i class="fas fa-link me-1"></i>Make public (shareable link)</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="glam-save-search-global">
          <label class="form-check-label" for="glam-save-search-global"><i class="fas fa-globe me-1"></i>Global (visible to all users)</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="glam-save-search-notify">
          <label class="form-check-label" for="glam-save-search-notify">Notify me of new results</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="glam-save-search-btn">Save</button>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('glam-save-search-btn').addEventListener('click', function() {
  var name = document.getElementById('glam-save-search-name').value;
  if (!name) { alert('Please enter a name'); return; }
  var data = {
    name: name,
    search_params: window.location.search.substring(1),
    is_public: document.getElementById('glam-save-search-public').checked ? 1 : 0,
    is_global: document.getElementById('glam-save-search-global').checked ? 1 : 0,
    notify: document.getElementById('glam-save-search-notify').checked ? 1 : 0,
    entity_type: 'informationobject',
    _token: '{{ csrf_token() }}'
  };
  fetch('/research/saved-searches', {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': data._token},
    body: JSON.stringify(data)
  }).then(function(r) { return r.json(); })
    .then(function(d) {
      var modal = bootstrap.Modal.getInstance(document.getElementById('saveGlamSearchModal'));
      if (modal) modal.hide();
      alert('Search saved!');
      location.reload();
    }).catch(function() { alert('Error saving search'); });
});
</script>
@endauth

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tom Select for repository dropdown
    var repoSelect = document.getElementById('repo-select');
    if (repoSelect) {
        new TomSelect(repoSelect, {
            create: false,
            sortField: { field: "text", direction: "asc" },
            placeholder: 'Type to search...',
            allowEmptyOption: true
        });
    }

    // Field search: add criterion
    var addBtn = document.getElementById('add-field-search-btn');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            var container = document.getElementById('field-search-rows');
            var firstRow = container.querySelector('.field-search-row');
            var newRow = firstRow.cloneNode(true);
            var select = newRow.querySelector('select');
            var input = newRow.querySelector('input');
            select.selectedIndex = 0;
            input.name = '';
            input.value = '';
            if (!newRow.querySelector('.btn-outline-danger')) {
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-outline-danger';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.onclick = function() { this.closest('.field-search-row').remove(); };
                newRow.appendChild(removeBtn);
            }
            container.appendChild(newRow);
        });
    }

    // Field search: sync dropdown to input name
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('field-select')) {
            var input = e.target.closest('.field-search-row').querySelector('input[type="text"]');
            if (input) { input.name = e.target.value; }
        }
    });

    // Before submit: remove unnamed field inputs to keep URL clean
    var form = document.getElementById('glam-advanced-search-form');
    if (form) {
        form.addEventListener('submit', function() {
            form.querySelectorAll('.field-search-row input[type="text"]').forEach(function(input) {
                if (!input.name || !input.value.trim()) { input.removeAttribute('name'); }
            });
        });
    }

    // Sync sector quick filter buttons with the level dropdown
    var sectorSelect = document.getElementById('sector-filter-select');
    if (sectorSelect) {
        @php
            // Build levels-by-sector map from DB
            $levelsBySectorMap = [];
            $sectorLevelMap = [
                'library' => [1700,1701,1702,1703,1759,1704,1161],
                'dam' => [905146,905147,1755,1756,1161,1757,1758],
                'archive' => [238,241,236,242,299,434,239,237,240],
                'museum' => [1757,1751,1750,512,500,1752],
                'gallery' => [1750,905146,512],
            ];
            $allLevels = $levels->keyBy('id');
            foreach ($sectorLevelMap as $sector => $ids) {
                $sectorLevels = [];
                foreach ($ids as $id) {
                    if ($allLevels->has($id)) {
                        $sectorLevels[] = ['id' => $id, 'name' => $allLevels[$id]->name];
                    }
                }
                $levelsBySectorMap[$sector] = $sectorLevels;
            }
            $levelsBySectorMap[''] = $levels->map(fn($l) => ['id' => $l->id, 'name' => $l->name])->values()->toArray();
        @endphp
        var levelsBySector = @json($levelsBySectorMap);

        sectorSelect.addEventListener('change', function() {
            var sector = this.value;
            var levelSelect = document.getElementById('level-filter-select');
            if (!levelSelect) return;
            var currentLevel = levelSelect.value;
            levelSelect.innerHTML = '<option value="">Any level</option>';
            var levels = levelsBySector[sector] || levelsBySector[''] || [];
            levels.forEach(function(l) {
                var opt = document.createElement('option');
                opt.value = l.id;
                opt.textContent = l.name;
                if (String(l.id) === currentLevel) opt.selected = true;
                levelSelect.appendChild(opt);
            });
        });
    }
});
</script>
