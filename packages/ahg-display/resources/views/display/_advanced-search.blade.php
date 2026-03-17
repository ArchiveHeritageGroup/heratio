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
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Sector</label>
                  <select name="type" class="form-select">
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
                  <select name="level" class="form-select">
                    <option value="">Any level</option>
                    @foreach($levels as $level)
                      <option value="{{ $level->id }}" {{ ($params['level'] ?? '') == $level->id ? 'selected' : '' }}>{{ $level->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Repository</label>
                  <select name="repo" class="form-select">
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

{{-- Saved searches --}}
@auth
  @if($savedSearches->isNotEmpty())
    <div class="dropdown mb-3">
      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="fas fa-bookmark me-1"></i>Saved searches ({{ $savedSearches->count() }})
      </button>
      <ul class="dropdown-menu">
        @foreach($savedSearches as $ss)
          <li><a class="dropdown-item" href="{{ url('/glam/browse?' . ($ss->query_string ?? '')) }}">{{ $ss->name ?? 'Saved search' }}</a></li>
        @endforeach
      </ul>
    </div>
  @endif
@endauth
