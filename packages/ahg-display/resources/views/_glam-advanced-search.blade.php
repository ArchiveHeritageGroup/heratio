{{-- GLAM Advanced Search Panel - Full ISAD(G) Fields with Sector Groupings --}}
@php
$params = request()->all();
$showAdvanced = ($params['showAdvanced'] ?? '') === '1';
$currentType = $params['type'] ?? '';

// Get levels filtered by sector
$levelsBySector = [];
try {
    $allLevels = \Illuminate\Support\Facades\DB::table('term')
        ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
        ->where('term.taxonomy_id', 34) {{-- LEVEL_OF_DESCRIPTION_ID --}}
        ->where('term_i18n.culture', 'en')
        ->orderBy('term_i18n.name')
        ->select('term.id', 'term_i18n.name')
        ->get()
        ->toArray();

    // Get levels per sector from configuration table
    $sectorLevels = \Illuminate\Support\Facades\DB::table('level_of_description_sector as los')
        ->join('term_i18n as ti', function($join) {
            $join->on('los.term_id', '=', 'ti.id')
                 ->where('ti.culture', '=', 'en');
        })
        ->select('los.sector', 'los.term_id as id', 'ti.name', 'los.display_order')
        ->orderBy('los.display_order')
        ->get();

    // Group by sector
    foreach ($sectorLevels as $sl) {
        if (!isset($levelsBySector[$sl->sector])) {
            $levelsBySector[$sl->sector] = [];
        }
        $levelsBySector[$sl->sector][] = (object)['id' => $sl->id, 'name' => $sl->name];
    }
    $levelsBySector[''] = $allLevels;
} catch (\Exception $e) {
    \Log::error("Levels query error: " . $e->getMessage());
    $levelsBySector[''] = [];
}

// Get repositories
$repositories = [];
try {
    $repositories = \Illuminate\Support\Facades\DB::table('repository')
        ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
        ->where('actor_i18n.culture', 'en')
        ->whereNotNull('actor_i18n.authorized_form_of_name')
        ->where('actor_i18n.authorized_form_of_name', '!=', '')
        ->orderBy('actor_i18n.authorized_form_of_name')
        ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
        ->get()
        ->toArray();
} catch (\Exception $e) {
    \Log::error("Repository query error: " . $e->getMessage());
}

// Determine which levels to show
$currentLevels = isset($levelsBySector[$currentType]) && !empty($levelsBySector[$currentType])
    ? $levelsBySector[$currentType]
    : ($levelsBySector[''] ?? []);
@endphp
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

<div class="accordion mb-3" id="glamAdvancedSearchAccordion" data-default-closed>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button {{ $showAdvanced ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#glamAdvancedSearchPanel" aria-expanded="{{ $showAdvanced ? 'true' : 'false' }}">
        <i class="fas fa-sliders-h me-2"></i>{{ __('Advanced search options') }}
      </button>
    </h2>
    <div id="glamAdvancedSearchPanel" class="accordion-collapse collapse {{ $showAdvanced ? 'show' : '' }}">
      <div class="accordion-body">
        <form method="get" action="{{ route('display.browse') }}" id="glam-advanced-search-form">

          <!-- Sector Quick Filter -->
          <div class="mb-4">
            <label class="form-label fw-bold"><i class="fas fa-layer-group me-1"></i>{{ __('Search in sector') }}</label>
            <div class="d-flex flex-wrap gap-2">
              <a href="{{ route('display.browse', ['showAdvanced' => '1']) }}" class="btn {{ empty($currentType) ? 'btn-secondary' : 'btn-outline-secondary' }}">
                <i class="fas fa-globe me-1"></i>{{ __('All') }}
              </a>
              <a href="{{ route('display.browse', ['type' => 'archive', 'showAdvanced' => '1']) }}" class="btn {{ $currentType === 'archive' ? 'btn-success' : 'btn-outline-success' }}">
                <i class="fas fa-archive me-1"></i>{{ __('Archive') }}
              </a>
              <a href="{{ route('display.browse', ['type' => 'library', 'showAdvanced' => '1']) }}" class="btn {{ $currentType === 'library' ? 'btn-info text-white' : 'btn-outline-info' }}">
                <i class="fas fa-book me-1"></i>{{ __('Library') }}
              </a>
              <a href="{{ route('display.browse', ['type' => 'museum', 'showAdvanced' => '1']) }}" class="btn {{ $currentType === 'museum' ? 'btn-warning' : 'btn-outline-warning' }}">
                <i class="fas fa-landmark me-1"></i>{{ __('Museum') }}
              </a>
              <a href="{{ route('display.browse', ['type' => 'gallery', 'showAdvanced' => '1']) }}" class="btn {{ $currentType === 'gallery' ? 'btn-danger' : 'btn-outline-danger' }}">
                <i class="fas fa-palette me-1"></i>{{ __('Gallery') }}
              </a>
              <a href="{{ route('display.browse', ['type' => 'dam', 'showAdvanced' => '1']) }}" class="btn {{ $currentType === 'dam' ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="fas fa-images me-1"></i>{{ __('Photos') }}
              </a>
            </div>
          </div>

          <!-- Nav Tabs -->
          <ul class="nav nav-tabs mb-3" id="advSearchTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#basic-search" type="button">{{ __('Basic') }}</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#content-search" type="button">{{ __('Content') }}</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#access-search" type="button">{{ __('Access Points') }}</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#dates-search" type="button">{{ __('Dates') }}</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#filters-search" type="button">{{ __('Filters') }}</button></li>
          </ul>

          <div class="tab-content">
            <!-- Basic Tab -->
            <div class="tab-pane fade show active" id="basic-search">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small fw-bold">{{ __('Any field') }}</label>
                  <input type="text" name="query" class="form-control" value="{{ e($params['query'] ?? '') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-bold">{{ __('Title') }}</label>
                  <input type="text" name="title" class="form-control" value="{{ e($params['title'] ?? '') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-bold">{{ __('Identifier') }}</label>
                  <input type="text" name="identifier" class="form-control" value="{{ e($params['identifier'] ?? '') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-bold">{{ __('Reference code') }}</label>
                  <input type="text" name="referenceCode" class="form-control" value="{{ e($params['referenceCode'] ?? '') }}">
                </div>
              </div>
            </div>

            <!-- Content Tab -->
            <div class="tab-pane fade" id="content-search">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small fw-bold">{{ __('Scope and content') }}</label>
                  <input type="text" name="scopeAndContent" class="form-control" value="{{ e($params['scopeAndContent'] ?? '') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-bold">{{ __('Extent and medium') }}</label>
                  <input type="text" name="extentAndMedium" class="form-control" value="{{ e($params['extentAndMedium'] ?? '') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-bold">{{ __('Archival history') }}</label>
                  <input type="text" name="archivalHistory" class="form-control" value="{{ e($params['archivalHistory'] ?? '') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-bold">{{ __('Acquisition') }}</label>
                  <input type="text" name="acquisition" class="form-control" value="{{ e($params['acquisition'] ?? '') }}">
                </div>
              </div>
            </div>

            <!-- Access Points Tab -->
            <div class="tab-pane fade" id="access-search">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small fw-bold">{{ __('Creator') }}</label>
                  <input type="text" name="creatorSearch" class="form-control" value="{{ e($params['creatorSearch'] ?? '') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-bold">{{ __('Subject') }}</label>
                  <input type="text" name="subjectSearch" class="form-control" value="{{ e($params['subjectSearch'] ?? '') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-bold">{{ __('Place') }}</label>
                  <input type="text" name="placeSearch" class="form-control" value="{{ e($params['placeSearch'] ?? '') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-bold">{{ __('Genre') }}</label>
                  <input type="text" name="genreSearch" class="form-control" value="{{ e($params['genreSearch'] ?? '') }}">
                </div>
              </div>
            </div>

            <!-- Dates Tab -->
            <div class="tab-pane fade" id="dates-search">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label small fw-bold">{{ __('Date from') }}</label>
                  <input type="date" name="startDate" class="form-control" value="{{ e($params['startDate'] ?? '') }}">
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">{{ __('Date to') }}</label>
                  <input type="date" name="endDate" class="form-control" value="{{ e($params['endDate'] ?? '') }}">
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">{{ __('Date matching') }}</label>
                  <select name="rangeType" class="form-select">
                    <option value="inclusive" {{ ($params['rangeType'] ?? '') === 'inclusive' ? 'selected' : '' }}>{{ __('Overlapping') }}</option>
                    <option value="exact" {{ ($params['rangeType'] ?? '') === 'exact' ? 'selected' : '' }}>{{ __('Exact') }}</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Filters Tab -->
            <div class="tab-pane fade" id="filters-search">
              <!-- Search specific field -->
              <div class="mb-3 p-3 bg-light rounded">
                <label class="form-label small fw-bold"><i class="fas fa-search me-1"></i>{{ __('Search specific field') }}</label>
                <div id="field-search-rows">
                  @php
                  $fieldSearchOptions = [
                      '' => __('-- Select field --'),
                      'title' => __('Title'),
                      'identifier' => __('Identifier'),
                      'referenceCode' => __('Reference code'),
                      'scopeAndContent' => __('Scope and content'),
                      'extentAndMedium' => __('Extent and medium'),
                      'archivalHistory' => __('Archival history'),
                      'acquisition' => __('Acquisition'),
                      'creatorSearch' => __('Creator'),
                      'subjectSearch' => __('Subject'),
                      'placeSearch' => __('Place'),
                      'genreSearch' => __('Genre'),
                  ];
                  $activeFieldSearches = [];
                  foreach ($fieldSearchOptions as $key => $label) {
                      if ($key && !empty($params[$key])) {
                          $activeFieldSearches[] = ['field' => $key, 'value' => $params[$key]];
                      }
                  }
                  if (empty($activeFieldSearches)) {
                      $activeFieldSearches[] = ['field' => '', 'value' => ''];
                  }
                  @endphp
                  @foreach($activeFieldSearches as $idx => $fs)
                  <div class="input-group mb-2 field-search-row">
                    <select class="form-select field-select" style="max-width: 200px;" onchange="this.nextElementSibling.name = this.value">
                      @foreach($fieldSearchOptions as $key => $label)
                        <option value="{{ $key }}" {{ $fs['field'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                      @endforeach
                    </select>
                    <input type="text" name="{{ $fs['field'] }}" class="form-control" value="{{ e($fs['value']) }}" placeholder="{{ __('Enter search term...') }}">
                    @if($idx > 0)
                    <button type="button" class="btn btn-outline-danger" onclick="this.closest('.field-search-row').remove()"><i class="fas fa-times"></i></button>
                    @endif
                  </div>
                  @endforeach
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="add-field-search-btn">
                  <i class="fas fa-plus me-1"></i>{{ __('Add criterion') }}
                </button>
              </div>

              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label small fw-bold">{{ __('Sector') }}</label>
                  <select name="type" class="form-select" id="sector-filter-select">
                    <option value="" {{ empty($currentType) ? 'selected' : '' }}>{{ __('All sectors') }}</option>
                    <option value="archive" {{ $currentType === 'archive' ? 'selected' : '' }}>{{ __('Archive') }}</option>
                    <option value="library" {{ $currentType === 'library' ? 'selected' : '' }}>{{ __('Library') }}</option>
                    <option value="museum" {{ $currentType === 'museum' ? 'selected' : '' }}>{{ __('Museum') }}</option>
                    <option value="gallery" {{ $currentType === 'gallery' ? 'selected' : '' }}>{{ __('Gallery') }}</option>
                    <option value="dam" {{ $currentType === 'dam' ? 'selected' : '' }}>{{ __('Photos') }}</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">{{ __('Level of description') }}
                    @if($currentType)<span class="badge bg-secondary">{{ ucfirst($currentType) }}</span>@endif
                  </label>
                  <select name="level" class="form-select" id="level-filter-select">
                    <option value="">{{ __('Any level') }}</option>
                    @foreach($currentLevels as $level)
                      <option value="{{ $level->id }}" {{ ($params['level'] ?? '') == $level->id ? 'selected' : '' }}>{{ e($level->name) }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">{{ __('Repository') }}</label>
                  <select name="repo" id="repo-select">
                    <option value="">{{ __('Any repository') }}</option>
                    @foreach($repositories as $repo)
                      <option value="{{ $repo->id }}" {{ ($params['repo'] ?? '') == $repo->id ? 'selected' : '' }}>{{ e($repo->name) }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">{{ __('Digital objects') }}</label>
                  <select name="hasDigital" class="form-select">
                    <option value="">{{ __('Any') }}</option>
                    <option value="1" {{ ($params['hasDigital'] ?? '') === '1' ? 'selected' : '' }}>{{ __('With digital objects') }}</option>
                    <option value="0" {{ ($params['hasDigital'] ?? '') === '0' ? 'selected' : '' }}>{{ __('Without digital objects') }}</option>
                  </select>
                </div>
                <div class="col-12">
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="topLevel" id="topLevel-all" value="0" {{ ($params['topLevel'] ?? '0') === '0' ? 'checked' : '' }}>
                    <label class="form-check-label" for="topLevel-all">{{ __('All descriptions') }}</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="topLevel" id="topLevel-top" value="1" {{ ($params['topLevel'] ?? '') === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="topLevel-top">{{ __('Top-level only') }}</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <input type="hidden" name="showAdvanced" value="1">

          <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top">
            <a href="{{ route('display.browse') }}" class="btn btn-outline-secondary"><i class="fas fa-undo me-1"></i>{{ __('Reset') }}</a>
            <button type="submit" class="btn btn-success"><i class="fas fa-search me-1"></i>{{ __('Search') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script{{ Vite::useCspNonce() ? ' nonce="' . Vite::cspNonce() . '"' : '' }}>
document.addEventListener('DOMContentLoaded', function() {
    var repoSelect = document.getElementById('repo-select');
    if (repoSelect) {
        new TomSelect(repoSelect, {
            create: false,
            sortField: { field: "text", direction: "asc" },
            placeholder: '{{ __("Type to search...") }}',
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
            // Add remove button if not present
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
            if (input) {
                input.name = e.target.value;
            }
        }
    });

    // Before submit: remove unnamed field inputs to keep URL clean
    var form = document.getElementById('glam-advanced-search-form');
    if (form) {
        form.addEventListener('submit', function() {
            form.querySelectorAll('.field-search-row input[type="text"]').forEach(function(input) {
                if (!input.name || !input.value.trim()) {
                    input.removeAttribute('name');
                }
            });
        });
    }

    // Sync sector quick filter buttons with the dropdown
    var sectorSelect = document.getElementById('sector-filter-select');
    if (sectorSelect) {
        // Update levels dropdown when sector changes
        var levelsBySector = @json(collect($levelsBySector)->map(function($levels) {
            return collect($levels)->map(function($l) { return ['id' => $l->id, 'name' => $l->name]; })->values();
        }));

        sectorSelect.addEventListener('change', function() {
            var sector = this.value;
            var levelSelect = document.getElementById('level-filter-select');
            if (!levelSelect) return;
            var currentLevel = levelSelect.value;
            levelSelect.innerHTML = '<option value="">{{ __("Any level") }}</option>';
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
