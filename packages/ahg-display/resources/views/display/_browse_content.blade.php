{{--
  GLAM Browser – _browse_content.blade.php
  Shared content partial for browse (used by both 2-col and full-width layouts)
--}}
@php
  // Re-establish helpers if not already set (when included from browse.blade.php they are available)
  $typeConfig = $typeConfig ?? [
      'archive' => ['icon' => 'fa-archive',  'color' => 'success', 'label' => 'Archive'],
      'museum'  => ['icon' => 'fa-landmark', 'color' => 'warning', 'label' => 'Museum'],
      'gallery' => ['icon' => 'fa-palette',  'color' => 'info',    'label' => 'Gallery'],
      'library' => ['icon' => 'fa-book',     'color' => 'primary', 'label' => 'Library'],
      'dam'     => ['icon' => 'fa-images',   'color' => 'danger',  'label' => 'Photo/DAM'],
  ];
  $sortLabels = $sortLabels ?? [
      'date'       => 'Date modified',
      'title'      => 'Title',
      'identifier' => 'Identifier',
      'refcode'    => 'Reference code',
      'startdate'  => 'Start date',
      'enddate'    => 'End date',
  ];
  $fp   = $filterParams ?? [];
  $from = (($page ?? 1) - 1) * ($limit ?? 30) + 1;
  $to   = min(($page ?? 1) * ($limit ?? 30), $total ?? 0);
@endphp

{{-- ========== TITLE SECTION ========== --}}
<div class="d-flex align-items-center justify-content-between mb-3">
  <div class="d-flex align-items-center">
    <i class="fas fa-3x fa-layer-group me-3 text-success" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0 h3">
        @if(($total ?? 0) > 0)
          Showing {{ number_format($total) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">GLAM Browser</span>
    </div>
  </div>
  <div>
    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#semanticSearchModal"
            title="Semantic Search">
      <i class="fas fa-brain me-1"></i> Semantic Search
    </button>
  </div>
</div>

{{-- ========== BREADCRUMB (if browsing within a parent) ========== --}}
@if(!empty($breadcrumb) && count($breadcrumb) > 0)
  <nav aria-label="Hierarchy breadcrumb" class="mb-2">
    <ol class="breadcrumb mb-0 small">
      <li class="breadcrumb-item">
        <a href="{{ route('glam.browse') }}"><i class="fas fa-home"></i></a>
      </li>
      @foreach($breadcrumb as $crumb)
        @if(!$loop->last)
          <li class="breadcrumb-item">
            <a href="{{ glamBrowseUrl($fp, ['parent' => $crumb['id'] ?? '']) }}">{{ $crumb['title'] ?? '[Untitled]' }}</a>
          </li>
        @else
          <li class="breadcrumb-item active" aria-current="page">{{ $crumb['title'] ?? '[Untitled]' }}</li>
        @endif
      @endforeach
    </ol>
  </nav>
@endif

{{-- ========== ACTIVE FILTERS BAR ========== --}}
@php
  $hasActiveFilters = !empty($typeFilter) || !empty($creatorFilter) || !empty($levelFilter) || !empty($repoFilter) || !empty($hasDigital) || !empty($parentId) || !empty($subjectFilter) || !empty($placeFilter) || !empty($genreFilter) || !empty($mediaFilter) || !empty($queryFilter);
@endphp
@if($hasActiveFilters)
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3 p-2 bg-light border rounded">
    <span class="small fw-bold text-muted me-1"><i class="fas fa-filter me-1"></i> Active:</span>

    @if(!empty($queryFilter))
      <a href="{{ glamBrowseUrl($fp, [], ['query', 'semantic']) }}" class="badge bg-dark text-decoration-none">
        <i class="fas fa-search me-1"></i> "{{ e($queryFilter) }}" <i class="fas fa-times ms-1"></i>
      </a>
    @endif

    @if(!empty($typeFilter))
      @php $tcActive = $typeConfig[$typeFilter] ?? ['icon' => 'fa-question', 'color' => 'secondary', 'label' => ucfirst($typeFilter)]; @endphp
      <a href="{{ glamBrowseUrl($fp, [], ['type']) }}" class="badge bg-{{ $tcActive['color'] }} text-decoration-none">
        <i class="fas {{ $tcActive['icon'] }} me-1"></i> {{ $tcActive['label'] }} <i class="fas fa-times ms-1"></i>
      </a>
    @endif

    @if(!empty($creatorFilter))
      <a href="{{ glamBrowseUrl($fp, [], ['creator']) }}" class="badge bg-info text-decoration-none">
        <i class="fas fa-user me-1"></i> Creator <i class="fas fa-times ms-1"></i>
      </a>
    @endif

    @if(!empty($subjectFilter))
      <a href="{{ glamBrowseUrl($fp, [], ['subject']) }}" class="badge bg-primary text-decoration-none">
        <i class="fas fa-tag me-1"></i> Subject <i class="fas fa-times ms-1"></i>
      </a>
    @endif

    @if(!empty($placeFilter))
      <a href="{{ glamBrowseUrl($fp, [], ['place']) }}" class="badge bg-secondary text-decoration-none">
        <i class="fas fa-map-marker-alt me-1"></i> Place <i class="fas fa-times ms-1"></i>
      </a>
    @endif

    @if(!empty($genreFilter))
      <a href="{{ glamBrowseUrl($fp, [], ['genre']) }}" class="badge bg-warning text-dark text-decoration-none">
        <i class="fas fa-theater-masks me-1"></i> Genre <i class="fas fa-times ms-1"></i>
      </a>
    @endif

    @if(!empty($levelFilter))
      <a href="{{ glamBrowseUrl($fp, [], ['level']) }}" class="badge bg-secondary text-decoration-none">
        <i class="fas fa-sitemap me-1"></i> Level <i class="fas fa-times ms-1"></i>
      </a>
    @endif

    @if(!empty($mediaFilter))
      <a href="{{ glamBrowseUrl($fp, [], ['media']) }}" class="badge bg-danger text-decoration-none">
        <i class="fas fa-photo-video me-1"></i> Media <i class="fas fa-times ms-1"></i>
      </a>
    @endif

    @if(!empty($repoFilter))
      <a href="{{ glamBrowseUrl($fp, [], ['repo']) }}" class="badge bg-success text-decoration-none">
        <i class="fas fa-building me-1"></i> Repository <i class="fas fa-times ms-1"></i>
      </a>
    @endif

    @if(!empty($hasDigital))
      <a href="{{ glamBrowseUrl($fp, [], ['hasDigital']) }}" class="badge bg-purple text-decoration-none" style="background-color:#6f42c1;">
        <i class="fas fa-image me-1"></i> Digital objects <i class="fas fa-times ms-1"></i>
      </a>
    @endif

    @if(!empty($parentId))
      <a href="{{ glamBrowseUrl($fp, [], ['parent', 'topLevelOnly']) }}" class="badge bg-dark text-decoration-none">
        <i class="fas fa-folder-open me-1"></i> In: {{ e($parent['title'] ?? 'Parent #'.$parentId) }} <i class="fas fa-times ms-1"></i>
      </a>
    @endif

    <a href="{{ route('glam.browse') }}" class="badge bg-outline-secondary border text-dark text-decoration-none ms-1" title="Clear all filters">
      <i class="fas fa-times-circle me-1"></i> Clear all
    </a>
  </div>
@endif

{{-- ========== FUZZY SEARCH ALERTS ========== --}}
{{-- Auto-corrected query --}}
@if(!empty($correctedQuery))
  <div class="alert alert-info alert-dismissible fade show mb-2" role="alert">
    <i class="fas fa-spell-check me-1"></i>
    Showing results for <strong>"{{ e($correctedQuery) }}"</strong>.
    @if(!empty($originalQuery))
      <a href="{{ glamBrowseUrl($fp, ['query' => $originalQuery, 'noCorrect' => 1]) }}" class="alert-link">
        Search instead for "{{ e($originalQuery) }}"
      </a>
    @endif
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

{{-- "Did you mean" suggestion --}}
@if(!empty($didYouMean))
  <div class="alert alert-warning alert-dismissible fade show mb-2" role="alert">
    <i class="fas fa-lightbulb me-1"></i>
    Did you mean:
    <a href="{{ glamBrowseUrl($fp, ['query' => $didYouMean]) }}" class="alert-link fw-bold">
      "{{ e($didYouMean) }}"
    </a>?
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

{{-- ES-assisted search warning --}}
@if(!empty($esAssistedSearch))
  <div class="alert alert-secondary alert-dismissible fade show mb-2" role="alert">
    <i class="fas fa-info-circle me-1"></i>
    Results enhanced by Elasticsearch full-text search.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

{{-- ========== DISCOVERY MODE ALERT ========== --}}
@if(!empty($discoveryMode) && !empty($discoveryMeta))
  <div class="alert alert-success alert-dismissible fade show mb-2" role="alert">
    <div class="d-flex align-items-start">
      <i class="fas fa-brain fa-lg me-2 mt-1"></i>
      <div class="flex-grow-1">
        <strong>AI-powered discovery mode</strong>
        @if(!empty($discoveryMeta['synonyms']))
          <div class="mt-1 small">
            <i class="fas fa-exchange-alt me-1"></i> <strong>Synonyms:</strong>
            @foreach($discoveryMeta['synonyms'] as $syn)
              <span class="badge bg-light text-dark border me-1">{{ $syn }}</span>
            @endforeach
          </div>
        @endif
        @if(!empty($discoveryMeta['entities']))
          <div class="mt-1 small">
            <i class="fas fa-tag me-1"></i> <strong>Entities:</strong>
            @foreach($discoveryMeta['entities'] as $ent)
              <span class="badge bg-light text-dark border me-1">{{ $ent }}</span>
            @endforeach
          </div>
        @endif
        @if(!empty($discoveryMeta['expanded_query']))
          <div class="mt-1 small">
            <i class="fas fa-expand-arrows-alt me-1"></i> <strong>Expanded:</strong>
            <em>{{ e($discoveryMeta['expanded_query']) }}</em>
          </div>
        @endif
        @if(!empty($discoveryExpanded))
          <div class="mt-1">
            <a class="small" data-bs-toggle="collapse" href="#discoveryDetails" role="button" aria-expanded="false">
              Show details <i class="fas fa-caret-down"></i>
            </a>
            <div class="collapse mt-1" id="discoveryDetails">
              <pre class="bg-light p-2 rounded small mb-0">{{ json_encode($discoveryExpanded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
          </div>
        @endif
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

{{-- ========== TOOLBAR ========== --}}
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">

  {{-- Print --}}
  <a href="{{ route('glam.print', array_filter($fp)) }}" class="btn btn-sm btn-outline-secondary" title="Print" target="_blank">
    <i class="fas fa-print"></i>
  </a>

  {{-- CSV Export --}}
  <a href="{{ route('glam.export.csv', array_filter($fp)) }}" class="btn btn-sm btn-outline-secondary" title="Export CSV">
    <i class="fas fa-file-csv"></i>
  </a>

  {{-- View mode buttons --}}
  <div class="btn-group btn-group-sm" role="group" aria-label="View mode">
    <a href="{{ glamBrowseUrl($fp, ['view' => 'card']) }}"
       class="btn btn-outline-secondary {{ ($viewMode ?? 'card') === 'card' ? 'active' : '' }}" title="Card view">
      <i class="fas fa-th-list"></i>
    </a>
    <a href="{{ glamBrowseUrl($fp, ['view' => 'grid']) }}"
       class="btn btn-outline-secondary {{ ($viewMode ?? 'card') === 'grid' ? 'active' : '' }}" title="Grid view">
      <i class="fas fa-th"></i>
    </a>
    <a href="{{ glamBrowseUrl($fp, ['view' => 'table']) }}"
       class="btn btn-outline-secondary {{ ($viewMode ?? 'card') === 'table' ? 'active' : '' }}" title="Table view">
      <i class="fas fa-table"></i>
    </a>
    <a href="{{ glamBrowseUrl($fp, ['view' => 'full']) }}"
       class="btn btn-outline-secondary {{ ($viewMode ?? 'card') === 'full' ? 'active' : '' }}" title="Full width view">
      <i class="fas fa-expand"></i>
    </a>
  </div>

  {{-- Spacer --}}
  <div class="ms-auto"></div>

  {{-- Limit dropdown --}}
  <div class="dropdown">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      {{ $limit ?? 30 }} per page
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
      @foreach([10, 20, 30, 50, 100] as $lim)
        <li>
          <a class="dropdown-item {{ ($limit ?? 30) == $lim ? 'active' : '' }}"
             href="{{ glamBrowseUrl($fp, ['limit' => $lim]) }}">
            {{ $lim }} per page
          </a>
        </li>
      @endforeach
    </ul>
  </div>

  {{-- Sort dropdown --}}
  <div class="dropdown">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-sort me-1"></i> {{ $sortLabels[$sort ?? 'title'] ?? 'Title' }}
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
      @foreach($sortLabels as $sKey => $sLabel)
        <li>
          <a class="dropdown-item {{ ($sort ?? 'title') === $sKey ? 'active' : '' }}"
             href="{{ glamBrowseUrl($fp, ['sort' => $sKey]) }}">
            {{ $sLabel }}
          </a>
        </li>
      @endforeach
    </ul>
  </div>

  {{-- Sort direction dropdown --}}
  <div class="dropdown">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      @if(($sortDir ?? 'asc') === 'asc')
        <i class="fas fa-sort-amount-up-alt"></i> Ascending
      @else
        <i class="fas fa-sort-amount-down"></i> Descending
      @endif
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
      <li>
        <a class="dropdown-item {{ ($sortDir ?? 'asc') === 'asc' ? 'active' : '' }}"
           href="{{ glamBrowseUrl($fp, ['sortDir' => 'asc', 'dir' => 'asc']) }}">
          <i class="fas fa-sort-amount-up-alt me-1"></i> Ascending
        </a>
      </li>
      <li>
        <a class="dropdown-item {{ ($sortDir ?? 'asc') === 'desc' ? 'active' : '' }}"
           href="{{ glamBrowseUrl($fp, ['sortDir' => 'desc', 'dir' => 'desc']) }}">
          <i class="fas fa-sort-amount-down me-1"></i> Descending
        </a>
      </li>
    </ul>
  </div>
</div>

{{-- ========== DIGITAL OBJECTS INFO ========== --}}
@if(($digitalObjectCount ?? 0) > 0)
  <div class="d-flex align-items-center gap-2 mb-2">
    <span class="small text-muted">
      <i class="fas fa-image me-1"></i> {{ number_format($digitalObjectCount) }} with digital objects
    </span>
    @if(empty($hasDigital))
      <a href="{{ glamBrowseUrl($fp, ['hasDigital' => 1]) }}" class="btn btn-xs btn-outline-info" style="font-size:0.75rem; padding: 0.1rem 0.4rem;">
        <i class="fas fa-filter me-1"></i> With digital objects
      </a>
    @else
      <a href="{{ glamBrowseUrl($fp, [], ['hasDigital']) }}" class="btn btn-xs btn-info text-white" style="font-size:0.75rem; padding: 0.1rem 0.4rem;">
        <i class="fas fa-times me-1"></i> Showing digital only
      </a>
    @endif
  </div>
@endif

{{-- ========== RESULTS INFO ========== --}}
@if(($total ?? 0) > 0)
  <div class="small text-muted mb-2">
    Results {{ number_format($from) }} to {{ number_format($to) }} of {{ number_format($total) }}
  </div>
@endif

{{-- ========== RESULTS ========== --}}
@if(($total ?? 0) > 0)

  {{-- ===== TABLE VIEW ===== --}}
  @if(($viewMode ?? 'card') === 'table')
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped table-hover mb-0 browse-table" id="glam-browse-table">
        <thead class="table-light">
          <tr>
            <th class="col-thumb">
              <span class="d-none d-md-inline">Image</span>
              <div class="resize-handle"></div>
            </th>
            <th>
              Title
              <div class="resize-handle"></div>
            </th>
            <th class="col-identifier">
              Identifier
              <div class="resize-handle"></div>
            </th>
            <th class="col-level">
              Level
              <div class="resize-handle"></div>
            </th>
            <th>
              Type
              <div class="resize-handle"></div>
            </th>
            <th class="col-actions">
              Actions
              <div class="resize-handle"></div>
            </th>
          </tr>
        </thead>
        <tbody>
          @foreach($objects as $obj)
            @php
              $objType = $obj->glam_type ?? $obj->type ?? 'archive';
              $otc = $typeConfig[$objType] ?? ['icon' => 'fa-question', 'color' => 'secondary', 'label' => ucfirst($objType)];
              $objTitle = $obj->title ?? $obj->name ?? '[Untitled]';
              $objSlug = $obj->slug ?? '';
              $objThumb = $obj->thumbnail_path ?? $obj->thumbnail ?? null;
              $objIdentifier = $obj->identifier ?? '';
              $objLevel = $obj->level_of_description ?? $obj->level ?? '';
              $objUrl = '/' . $objSlug;
            @endphp
            <tr>
              {{-- Thumbnail --}}
              <td class="col-thumb text-center">
                @if($objThumb)
                  <a href="{{ $objUrl }}">
                    <img src="{{ $objThumb }}" alt="" class="browse-thumb-lg" loading="lazy">
                  </a>
                @else
                  <div class="browse-placeholder-lg mx-auto">
                    <i class="fas {{ $otc['icon'] }}"></i>
                  </div>
                @endif
              </td>
              {{-- Title --}}
              <td>
                <a href="{{ $objUrl }}" class="text-decoration-none fw-semibold">
                  {{ e($objTitle) }}
                </a>
                @if(!empty($obj->scope_and_content))
                  <br><small class="text-muted">{{ Str::limit(strip_tags($obj->scope_and_content), 120) }}</small>
                @endif
              </td>
              {{-- Identifier --}}
              <td class="col-identifier">
                <small>{{ e($objIdentifier) }}</small>
              </td>
              {{-- Level --}}
              <td class="col-level">
                @if($objLevel)
                  <span class="badge bg-light text-dark border">{{ e($objLevel) }}</span>
                @endif
              </td>
              {{-- Type --}}
              <td>
                <span class="badge bg-{{ $otc['color'] }}">
                  <i class="fas {{ $otc['icon'] }} me-1"></i> {{ $otc['label'] }}
                </span>
              </td>
              {{-- Actions --}}
              <td class="col-actions text-center">
                <a href="{{ $objUrl }}" class="btn btn-sm btn-outline-primary" title="View">
                  <i class="fas fa-eye"></i>
                </a>
              </td>
            </tr>
            {{-- Discovery metadata footer (per result) --}}
            @if(!empty($discoveryMode) && !empty($obj->_discovery))
              <tr class="table-light">
                <td colspan="6" class="py-1 px-3">
                  @include('ahg-display::display._discovery_meta', ['discovery' => $obj->_discovery])
                </td>
              </tr>
            @endif
          @endforeach
        </tbody>
      </table>
    </div>

  {{-- ===== GRID VIEW ===== --}}
  @elseif(($viewMode ?? 'card') === 'grid')
    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-3 mb-3">
      @foreach($objects as $obj)
        @php
          $objType = $obj->glam_type ?? $obj->type ?? 'archive';
          $otc = $typeConfig[$objType] ?? ['icon' => 'fa-question', 'color' => 'secondary', 'label' => ucfirst($objType)];
          $objTitle = $obj->title ?? $obj->name ?? '[Untitled]';
          $objSlug = $obj->slug ?? '';
          $objThumb = $obj->thumbnail_path ?? $obj->thumbnail ?? null;
          $objIdentifier = $obj->identifier ?? '';
          $objLevel = $obj->level_of_description ?? $obj->level ?? '';
          $objUrl = '/' . $objSlug;
        @endphp
        <div class="col">
          <div class="card h-100 shadow-sm">
            <a href="{{ $objUrl }}" class="text-decoration-none">
              <div class="grid-img-wrapper">
                @if($objThumb)
                  <img src="{{ $objThumb }}" alt="{{ e($objTitle) }}" class="grid-img" loading="lazy">
                @else
                  <div class="text-center text-muted py-4">
                    <i class="fas {{ $otc['icon'] }} fa-3x"></i>
                  </div>
                @endif
              </div>
            </a>
            <div class="card-body p-2">
              <a href="{{ $objUrl }}" class="text-decoration-none">
                <h6 class="card-title mb-1 small" title="{{ e($objTitle) }}">
                  {{ Str::limit($objTitle, 50) }}
                </h6>
              </a>
              @if($objIdentifier)
                <div class="small text-muted">{{ e($objIdentifier) }}</div>
              @endif
              @if($objLevel)
                <span class="badge bg-light text-dark border mt-1" style="font-size:0.65rem;">{{ e($objLevel) }}</span>
              @endif
              <span class="badge bg-{{ $otc['color'] }} mt-1" style="font-size:0.65rem;">
                <i class="fas {{ $otc['icon'] }}"></i> {{ $otc['label'] }}
              </span>
            </div>
            {{-- Discovery metadata footer --}}
            @if(!empty($discoveryMode) && !empty($obj->_discovery))
              <div class="card-footer p-1">
                @include('ahg-display::display._discovery_meta', ['discovery' => $obj->_discovery])
              </div>
            @endif
          </div>
        </div>
      @endforeach
    </div>

  {{-- ===== FULL WIDTH VIEW ===== --}}
  @elseif(($viewMode ?? 'card') === 'full')
    <div class="mb-3">
      @foreach($objects as $obj)
        @php
          $objType = $obj->glam_type ?? $obj->type ?? 'archive';
          $otc = $typeConfig[$objType] ?? ['icon' => 'fa-question', 'color' => 'secondary', 'label' => ucfirst($objType)];
          $objTitle = $obj->title ?? $obj->name ?? '[Untitled]';
          $objSlug = $obj->slug ?? '';
          $objThumb = $obj->thumbnail_path ?? $obj->thumbnail ?? null;
          $objIdentifier = $obj->identifier ?? '';
          $objLevel = $obj->level_of_description ?? $obj->level ?? '';
          $objUrl = '/' . $objSlug;
          $objRefCode = $obj->reference_code ?? $obj->refcode ?? '';
          $objDates = $obj->dates ?? $obj->date ?? '';
          $objScope = $obj->scope_and_content ?? '';
          $objCreator = $obj->creator ?? $obj->creator_name ?? '';
          $objRepo = $obj->repository_name ?? $obj->repository ?? '';
        @endphp
        <div class="card mb-3 shadow-sm">
          <div class="card-body">
            <div class="d-flex flex-column flex-md-row gap-3">
              {{-- Large image --}}
              <div class="full-img-bg flex-shrink-0">
                @if($objThumb)
                  <a href="{{ $objUrl }}">
                    <img src="{{ $objThumb }}" alt="{{ e($objTitle) }}" class="full-img" loading="lazy">
                  </a>
                @else
                  <div class="text-center text-muted">
                    <i class="fas {{ $otc['icon'] }} fa-5x"></i>
                  </div>
                @endif
              </div>
              {{-- Details --}}
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div>
                    <h4 class="mb-1">
                      <a href="{{ $objUrl }}" class="text-decoration-none">{{ e($objTitle) }}</a>
                    </h4>
                    <span class="badge bg-{{ $otc['color'] }} me-1">
                      <i class="fas {{ $otc['icon'] }} me-1"></i> {{ $otc['label'] }}
                    </span>
                    @if($objLevel)
                      <span class="badge bg-light text-dark border">{{ e($objLevel) }}</span>
                    @endif
                  </div>
                  <a href="{{ $objUrl }}" class="btn btn-sm btn-outline-primary" title="View">
                    <i class="fas fa-eye me-1"></i> View
                  </a>
                </div>

                <dl class="row mb-0 small">
                  @if($objIdentifier)
                    <dt class="col-sm-3 col-md-2">Identifier</dt>
                    <dd class="col-sm-9 col-md-10">{{ e($objIdentifier) }}</dd>
                  @endif
                  @if($objRefCode)
                    <dt class="col-sm-3 col-md-2">Reference code</dt>
                    <dd class="col-sm-9 col-md-10">{{ e($objRefCode) }}</dd>
                  @endif
                  @if($objDates)
                    <dt class="col-sm-3 col-md-2">Date(s)</dt>
                    <dd class="col-sm-9 col-md-10">{{ e($objDates) }}</dd>
                  @endif
                  @if($objCreator)
                    <dt class="col-sm-3 col-md-2">Creator</dt>
                    <dd class="col-sm-9 col-md-10">{{ e($objCreator) }}</dd>
                  @endif
                  @if($objRepo)
                    <dt class="col-sm-3 col-md-2">Repository</dt>
                    <dd class="col-sm-9 col-md-10">{{ e($objRepo) }}</dd>
                  @endif
                  @if($objScope)
                    <dt class="col-sm-3 col-md-2">Scope &amp; content</dt>
                    <dd class="col-sm-9 col-md-10">{{ Str::limit(strip_tags($objScope), 400) }}</dd>
                  @endif
                </dl>
              </div>
            </div>
          </div>
          {{-- Discovery metadata footer --}}
          @if(!empty($discoveryMode) && !empty($obj->_discovery))
            <div class="card-footer py-1 px-3">
              @include('ahg-display::display._discovery_meta', ['discovery' => $obj->_discovery])
            </div>
          @endif
        </div>
      @endforeach
    </div>

  {{-- ===== CARD VIEW (DEFAULT) ===== --}}
  @else
    <div class="mb-3">
      @foreach($objects as $obj)
        @php
          $objType = $obj->glam_type ?? $obj->type ?? 'archive';
          $otc = $typeConfig[$objType] ?? ['icon' => 'fa-question', 'color' => 'secondary', 'label' => ucfirst($objType)];
          $objTitle = $obj->title ?? $obj->name ?? '[Untitled]';
          $objSlug = $obj->slug ?? '';
          $objThumb = $obj->thumbnail_path ?? $obj->thumbnail ?? null;
          $objIdentifier = $obj->identifier ?? '';
          $objLevel = $obj->level_of_description ?? $obj->level ?? '';
          $objUrl = '/' . $objSlug;
          $objDates = $obj->dates ?? $obj->date ?? '';
          $objRepo = $obj->repository_name ?? $obj->repository ?? '';
        @endphp
        <div class="card mb-2 shadow-sm">
          <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center gap-3">
              {{-- Thumbnail --}}
              <div class="flex-shrink-0">
                @if($objThumb)
                  <a href="{{ $objUrl }}">
                    <img src="{{ $objThumb }}" alt="" class="card-img-browse" loading="lazy">
                  </a>
                @else
                  <div class="browse-placeholder-lg" style="width:80px;height:80px;font-size:1.6rem;">
                    <i class="fas {{ $otc['icon'] }}"></i>
                  </div>
                @endif
              </div>
              {{-- Details --}}
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-items-start justify-content-between">
                  <div class="min-width-0">
                    <a href="{{ $objUrl }}" class="text-decoration-none fw-semibold">
                      {{ e($objTitle) }}
                    </a>
                    <div class="small text-muted mt-1">
                      @if($objIdentifier)
                        <span class="me-2"><i class="fas fa-barcode me-1"></i>{{ e($objIdentifier) }}</span>
                      @endif
                      @if($objDates)
                        <span class="me-2"><i class="fas fa-calendar me-1"></i>{{ e($objDates) }}</span>
                      @endif
                      @if($objRepo)
                        <span class="me-2"><i class="fas fa-building me-1"></i>{{ e($objRepo) }}</span>
                      @endif
                    </div>
                    <div class="mt-1">
                      <span class="badge bg-{{ $otc['color'] }}" style="font-size:0.7rem;">
                        <i class="fas {{ $otc['icon'] }} me-1"></i> {{ $otc['label'] }}
                      </span>
                      @if($objLevel)
                        <span class="badge bg-light text-dark border" style="font-size:0.7rem;">{{ e($objLevel) }}</span>
                      @endif
                    </div>
                  </div>
                  {{-- Actions --}}
                  <div class="flex-shrink-0 ms-2">
                    <a href="{{ $objUrl }}" class="btn btn-sm btn-outline-primary" title="View">
                      <i class="fas fa-eye"></i>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
          {{-- Discovery metadata footer --}}
          @if(!empty($discoveryMode) && !empty($obj->_discovery))
            <div class="card-footer py-1 px-3">
              @include('ahg-display::display._discovery_meta', ['discovery' => $obj->_discovery])
            </div>
          @endif
        </div>
      @endforeach
    </div>
  @endif

  {{-- ========== PAGINATION ========== --}}
  @if(($totalPages ?? 1) > 1)
    <nav aria-label="Browse pagination">
      <ul class="pagination justify-content-center flex-wrap">
        {{-- Previous --}}
        @if(($page ?? 1) > 1)
          <li class="page-item">
            <a class="page-link" href="{{ glamBrowseUrl($fp, ['page' => $page - 1]) }}" aria-label="Previous">
              <span aria-hidden="true">&laquo;</span> Previous
            </a>
          </li>
        @else
          <li class="page-item disabled">
            <span class="page-link"><span aria-hidden="true">&laquo;</span> Previous</span>
          </li>
        @endif

        {{-- Page numbers --}}
        @php
          $window = 3;
          $startPage = max(1, $page - $window);
          $endPage = min($totalPages, $page + $window);
        @endphp

        @if($startPage > 1)
          <li class="page-item">
            <a class="page-link" href="{{ glamBrowseUrl($fp, ['page' => 1]) }}">1</a>
          </li>
          @if($startPage > 2)
            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
          @endif
        @endif

        @for($p = $startPage; $p <= $endPage; $p++)
          <li class="page-item {{ $p == $page ? 'active' : '' }}">
            @if($p == $page)
              <span class="page-link">{{ $p }}</span>
            @else
              <a class="page-link" href="{{ glamBrowseUrl($fp, ['page' => $p]) }}">{{ $p }}</a>
            @endif
          </li>
        @endfor

        @if($endPage < $totalPages)
          @if($endPage < $totalPages - 1)
            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
          @endif
          <li class="page-item">
            <a class="page-link" href="{{ glamBrowseUrl($fp, ['page' => $totalPages]) }}">{{ $totalPages }}</a>
          </li>
        @endif

        {{-- Next --}}
        @if(($page ?? 1) < ($totalPages ?? 1))
          <li class="page-item">
            <a class="page-link" href="{{ glamBrowseUrl($fp, ['page' => $page + 1]) }}" aria-label="Next">
              Next <span aria-hidden="true">&raquo;</span>
            </a>
          </li>
        @else
          <li class="page-item disabled">
            <span class="page-link">Next <span aria-hidden="true">&raquo;</span></span>
          </li>
        @endif
      </ul>
    </nav>
  @endif

@else
  {{-- No results --}}
  <div class="text-center py-5">
    <i class="fas fa-search fa-4x text-muted mb-3"></i>
    <h4 class="text-muted">No results found</h4>
    <p class="text-muted">
      Try adjusting your filters or
      <a href="{{ route('glam.browse') }}">clear all filters</a>
      to start over.
    </p>
    @if(!empty($queryFilter))
      <p class="text-muted">
        Your search for <strong>"{{ e($queryFilter) }}"</strong> did not match any records.
      </p>
    @endif
  </div>
@endif
