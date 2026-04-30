{{--
  Embedded GLAM Browse – browse-embedded.blade.php
  Migrated from AtoM browseEmbeddedSuccess.php (ahgDisplayPlugin)
  Returns facets + results without full page layout (for AJAX/landing page embedding)
--}}
@php
  $limit = (int) ($limit ?? request('limit', 10));
  if ($limit < 10) $limit = 10;
  if ($limit > 100) $limit = 100;

  $page       = (int) ($page ?? request('page', 1));
  $sort       = $sort ?? request('sort', 'date');
  $sortDir    = $sortDir ?? request('dir', 'desc');
  $viewMode   = $viewMode ?? request('view', 'card');
  $typeFilter = $typeFilter ?? request('type');
  $creatorFilter = $creatorFilter ?? request('creator');
  $placeFilter   = $placeFilter ?? request('place');
  $subjectFilter = $subjectFilter ?? request('subject');
  $genreFilter   = $genreFilter ?? request('genre');
  $levelFilter   = $levelFilter ?? request('level');
  $mediaFilter   = $mediaFilter ?? request('media');
  $repoFilter    = $repoFilter ?? request('repo');
  $hasDigital    = $hasDigital ?? request('hasDigital');
  $parentId      = $parentId ?? request('parent');

  $total      = $total ?? 0;
  $totalPages = $totalPages ?? 1;
  $parent     = $parent ?? null;
  $digitalObjectCount = $digitalObjectCount ?? 0;
  $objects    = $objects ?? [];
  $types      = $types ?? [];
  $creators   = $creators ?? [];
  $places     = $places ?? [];
  $subjects   = $subjects ?? [];
  $genres     = $genres ?? [];
  $levels     = $levels ?? [];
  $mediaTypes = $mediaTypes ?? [];
  $repositories = $repositories ?? [];
  $showSidebar  = $showSidebar ?? true;

  // Build filter params for URLs
  $fp = [
      'type'       => $typeFilter,
      'parent'     => $parentId,
      'creator'    => $creatorFilter,
      'subject'    => $subjectFilter,
      'place'      => $placeFilter,
      'genre'      => $genreFilter,
      'level'      => $levelFilter,
      'media'      => $mediaFilter,
      'repo'       => $repoFilter,
      'hasDigital' => $hasDigital,
      'view'       => $viewMode,
      'limit'      => $limit,
      'sort'       => $sort,
      'dir'        => $sortDir,
  ];

  $typeConfig = [
      'archive' => ['icon' => 'fa-archive',  'color' => 'success', 'label' => 'Archive'],
      'museum'  => ['icon' => 'fa-landmark', 'color' => 'warning', 'label' => 'Museum'],
      'gallery' => ['icon' => 'fa-palette',  'color' => 'info',    'label' => 'Gallery'],
      'library' => ['icon' => 'fa-book',     'color' => 'primary', 'label' => 'Library'],
      'dam'     => ['icon' => 'fa-images',   'color' => 'danger',  'label' => 'Photo/DAM'],
  ];
  $limitOptions = [10, 25, 50, 100];
  $sortLabels = [
      'date'       => 'Date modified',
      'title'      => 'Title',
      'identifier' => 'Identifier',
      'refcode'    => 'Reference code',
      'startdate'  => 'Start date',
      'enddate'    => 'End date',
  ];

  if (!function_exists('buildEmbeddedUrl')) {
      function buildEmbeddedUrl($fp, $add = [], $remove = []) {
          $params = array_merge(array_filter($fp, function($v) { return $v !== null && $v !== ''; }), $add);
          foreach ($remove as $key) { unset($params[$key]); }
          unset($params['page']);
          return route('glam.browse', $params);
      }
  }
@endphp

<div class="glam-browse-embedded">
  <div class="row">

    {{-- ========== FACETS SIDEBAR ========== --}}
    @if($showSidebar)
    <div class="col-lg-3 col-md-4">

      {{-- Filter header --}}
      <div class="card mb-3">
        <div class="card-body py-2 text-white text-center" style="background:var(--ahg-primary);">
          <i class="fas fa-filter"></i> {{ __('Filter by:') }}
        </div>
      </div>

      {{-- GLAM Type Facet --}}
      @if(!empty($types))
      <div class="card mb-2">
        <div class="card-header py-2 cursor-pointer" role="button" data-bs-toggle="collapse" data-bs-target="#embFacetType" style="background:var(--ahg-primary);color:#fff;cursor:pointer;">
          <strong>{{ __('GLAM Type') }}</strong> <i class="fas fa-chevron-down float-end"></i>
        </div>
        <div class="collapse show" id="embFacetType">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ !$typeFilter ? 'active' : '' }}">
              <a href="{{ buildEmbeddedUrl($fp, [], ['type']) }}" class="text-decoration-none small {{ !$typeFilter ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @foreach($types as $type)
              @php
                $tk = $type->object_type ?? '';
                $cfg = $typeConfig[$tk] ?? ['icon' => 'fa-question', 'color' => 'secondary', 'label' => ucfirst($tk)];
                $isActive = $typeFilter === $tk;
              @endphp
              <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ $isActive ? 'active' : '' }}">
                <a href="{{ $isActive ? buildEmbeddedUrl($fp, [], ['type']) : buildEmbeddedUrl($fp, ['type' => $tk]) }}" class="text-decoration-none small {{ $isActive ? 'text-white' : '' }}">
                  <i class="fas {{ $cfg['icon'] }} text-{{ $isActive ? 'white' : $cfg['color'] }}"></i>
                  {{ $cfg['label'] }}
                </a>
                <span class="badge bg-{{ $isActive ? 'light text-dark' : 'secondary' }} rounded-pill">{{ $type->count }}</span>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
      @endif

      {{-- Repository Facet --}}
      @if(!empty($repositories))
      <div class="card mb-2">
        <div class="card-header py-2 cursor-pointer" role="button" data-bs-toggle="collapse" data-bs-target="#embFacetRepo" style="background:var(--ahg-primary);color:#fff;cursor:pointer;">
          <strong>{{ __('Repository') }}</strong> <i class="fas fa-chevron-down float-end"></i>
        </div>
        <div class="collapse" id="embFacetRepo">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ !$repoFilter ? 'active' : '' }}">
              <a href="{{ buildEmbeddedUrl($fp, [], ['repo']) }}" class="text-decoration-none small {{ !$repoFilter ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @foreach($repositories as $repo)
              @php $isActive = $repoFilter == $repo->id; @endphp
              <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ $isActive ? 'active' : '' }}">
                <a href="{{ $isActive ? buildEmbeddedUrl($fp, [], ['repo']) : buildEmbeddedUrl($fp, ['repo' => $repo->id]) }}" class="text-decoration-none small text-truncate {{ $isActive ? 'text-white' : '' }}" style="max-width:180px">
                  {{ e($repo->name ?? '') }}
                </a>
                <span class="badge bg-{{ $isActive ? 'light text-dark' : 'secondary' }} rounded-pill">{{ $repo->count }}</span>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
      @endif

      {{-- Subject Facet --}}
      @if(!empty($subjects))
      <div class="card mb-2">
        <div class="card-header py-2 cursor-pointer" role="button" data-bs-toggle="collapse" data-bs-target="#embFacetSubject" style="background:var(--ahg-primary);color:#fff;cursor:pointer;">
          <strong>{{ __('Subject') }}</strong> <i class="fas fa-chevron-down float-end"></i>
        </div>
        <div class="collapse" id="embFacetSubject">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ !$subjectFilter ? 'active' : '' }}">
              <a href="{{ buildEmbeddedUrl($fp, [], ['subject']) }}" class="text-decoration-none small {{ !$subjectFilter ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @foreach($subjects as $subject)
              @php $isActive = $subjectFilter == $subject->id; @endphp
              <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ $isActive ? 'active' : '' }}">
                <a href="{{ $isActive ? buildEmbeddedUrl($fp, [], ['subject']) : buildEmbeddedUrl($fp, ['subject' => $subject->id]) }}" class="text-decoration-none small text-truncate {{ $isActive ? 'text-white' : '' }}" style="max-width:180px">
                  {{ e($subject->name ?? '') }}
                </a>
                <span class="badge bg-{{ $isActive ? 'light text-dark' : 'secondary' }} rounded-pill">{{ $subject->count }}</span>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
      @endif

      {{-- Level Facet --}}
      @if(!empty($levels))
      <div class="card mb-2">
        <div class="card-header py-2 cursor-pointer" role="button" data-bs-toggle="collapse" data-bs-target="#embFacetLevel" style="background:var(--ahg-primary);color:#fff;cursor:pointer;">
          <strong>{{ __('Level') }}</strong> <i class="fas fa-chevron-down float-end"></i>
        </div>
        <div class="collapse" id="embFacetLevel">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ !$levelFilter ? 'active' : '' }}">
              <a href="{{ buildEmbeddedUrl($fp, [], ['level']) }}" class="text-decoration-none small {{ !$levelFilter ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @foreach($levels as $level)
              @php $isActive = $levelFilter == $level->id; @endphp
              <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ $isActive ? 'active' : '' }}">
                <a href="{{ $isActive ? buildEmbeddedUrl($fp, [], ['level']) : buildEmbeddedUrl($fp, ['level' => $level->id]) }}" class="text-decoration-none small {{ $isActive ? 'text-white' : '' }}">
                  {{ e($level->name ?? '') }}
                </a>
                <span class="badge bg-{{ $isActive ? 'light text-dark' : 'secondary' }} rounded-pill">{{ $level->count }}</span>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
      @endif

      {{-- Creator Facet --}}
      @if(!empty($creators))
      <div class="card mb-2">
        <div class="card-header py-2 cursor-pointer" role="button" data-bs-toggle="collapse" data-bs-target="#embFacetCreator" style="background:var(--ahg-primary);color:#fff;cursor:pointer;">
          <strong>{{ __('Creator') }}</strong> <i class="fas fa-chevron-down float-end"></i>
        </div>
        <div class="collapse" id="embFacetCreator">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ !$creatorFilter ? 'active' : '' }}">
              <a href="{{ buildEmbeddedUrl($fp, [], ['creator']) }}" class="text-decoration-none small {{ !$creatorFilter ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @foreach($creators as $creator)
              @php $isActive = $creatorFilter == $creator->id; @endphp
              <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ $isActive ? 'active' : '' }}">
                <a href="{{ $isActive ? buildEmbeddedUrl($fp, [], ['creator']) : buildEmbeddedUrl($fp, ['creator' => $creator->id]) }}" class="text-decoration-none small text-truncate {{ $isActive ? 'text-white' : '' }}" style="max-width:180px">
                  {{ e($creator->name ?? '') }}
                </a>
                <span class="badge bg-{{ $isActive ? 'light text-dark' : 'secondary' }} rounded-pill">{{ $creator->count }}</span>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
      @endif

      {{-- Place Facet --}}
      @if(!empty($places))
      <div class="card mb-2">
        <div class="card-header py-2 cursor-pointer" role="button" data-bs-toggle="collapse" data-bs-target="#embFacetPlace" style="background:var(--ahg-primary);color:#fff;cursor:pointer;">
          <strong>{{ __('Place') }}</strong> <i class="fas fa-chevron-down float-end"></i>
        </div>
        <div class="collapse" id="embFacetPlace">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ !$placeFilter ? 'active' : '' }}">
              <a href="{{ buildEmbeddedUrl($fp, [], ['place']) }}" class="text-decoration-none small {{ !$placeFilter ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @foreach($places as $place)
              @php $isActive = $placeFilter == $place->id; @endphp
              <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ $isActive ? 'active' : '' }}">
                <a href="{{ $isActive ? buildEmbeddedUrl($fp, [], ['place']) : buildEmbeddedUrl($fp, ['place' => $place->id]) }}" class="text-decoration-none small text-truncate {{ $isActive ? 'text-white' : '' }}" style="max-width:180px">
                  {{ e($place->name ?? '') }}
                </a>
                <span class="badge bg-{{ $isActive ? 'light text-dark' : 'secondary' }} rounded-pill">{{ $place->count }}</span>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
      @endif

      {{-- Media Type Facet --}}
      @if(!empty($mediaTypes))
      <div class="card mb-2">
        <div class="card-header py-2 cursor-pointer" role="button" data-bs-toggle="collapse" data-bs-target="#embFacetMedia" style="background:var(--ahg-primary);color:#fff;cursor:pointer;">
          <strong>{{ __('Media type') }}</strong> <i class="fas fa-chevron-down float-end"></i>
        </div>
        <div class="collapse" id="embFacetMedia">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ !$mediaFilter ? 'active' : '' }}">
              <a href="{{ buildEmbeddedUrl($fp, [], ['media']) }}" class="text-decoration-none small {{ !$mediaFilter ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @foreach($mediaTypes as $media)
              @php
                $isActive = $mediaFilter === $media->media_type;
                $mediaIcon = match($media->media_type) { 'image' => 'fa-image', 'video' => 'fa-video', 'audio' => 'fa-music', 'application' => 'fa-file-alt', default => 'fa-file' };
              @endphp
              <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ $isActive ? 'active' : '' }}">
                <a href="{{ $isActive ? buildEmbeddedUrl($fp, [], ['media']) : buildEmbeddedUrl($fp, ['media' => $media->media_type]) }}" class="text-decoration-none small {{ $isActive ? 'text-white' : '' }}">
                  <i class="fas {{ $mediaIcon }}"></i>
                  {{ ucfirst($media->media_type) }}
                </a>
                <span class="badge bg-{{ $isActive ? 'light text-dark' : 'secondary' }} rounded-pill">{{ $media->count }}</span>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
      @endif

      {{-- Full Browse Page link --}}
      <a href="{{ route('glam.browse') }}" class="btn atom-btn-outline-success btn-sm w-100 mt-2">
        <i class="fas fa-expand-arrows-alt me-1"></i> {{ __('Full Browse Page') }}
      </a>

    </div>
    @endif

    {{-- ========== RESULTS COLUMN ========== --}}
    <div class="{{ $showSidebar ? 'col-lg-9 col-md-8' : 'col-12' }}">

      {{-- Header --}}
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0 text-success">
          <i class="fas fa-folder-open me-2"></i>
          Showing {{ number_format($total) }} results
        </h4>
        <a href="{{ route('glam.browse') }}" class="btn atom-btn-outline-success btn-sm">
          <i class="fas fa-search me-1"></i> {{ __('Advanced Search') }}
        </a>
      </div>

      {{-- Active Filters --}}
      @if($typeFilter || $repoFilter || $levelFilter || $creatorFilter || $subjectFilter || $hasDigital)
      <div class="d-flex flex-wrap gap-2 mb-3">
        @if($typeFilter)
          @php $cfg = $typeConfig[$typeFilter] ?? ['icon' => 'fa-tag', 'color' => 'secondary', 'label' => ucfirst($typeFilter)]; @endphp
          <a href="{{ buildEmbeddedUrl($fp, [], ['type']) }}" class="badge bg-{{ $cfg['color'] }} p-2 text-decoration-none text-white">
            <i class="fas {{ $cfg['icon'] }}"></i> {{ $cfg['label'] }} <i class="fas fa-times ms-1"></i>
          </a>
        @endif
        @if($repoFilter)
          @php $rname = ''; foreach(($repositories ?? []) as $r) if($r->id == $repoFilter) $rname = $r->name; @endphp
          <a href="{{ buildEmbeddedUrl($fp, [], ['repo']) }}" class="badge bg-dark p-2 text-decoration-none text-white">
            Repository: {{ e($rname) }} <i class="fas fa-times ms-1"></i>
          </a>
        @endif
        @if($levelFilter)
          @php $lname = ''; foreach(($levels ?? []) as $l) if($l->id == $levelFilter) $lname = $l->name; @endphp
          <a href="{{ buildEmbeddedUrl($fp, [], ['level']) }}" class="badge bg-secondary p-2 text-decoration-none text-white">
            Level: {{ e($lname) }} <i class="fas fa-times ms-1"></i>
          </a>
        @endif
        @if($hasDigital)
          <a href="{{ buildEmbeddedUrl($fp, [], ['hasDigital']) }}" class="badge bg-info p-2 text-decoration-none text-white">
            With digital objects <i class="fas fa-times ms-1"></i>
          </a>
        @endif
      </div>
      @endif

      {{-- Toolbar --}}
      <div class="d-flex flex-wrap gap-2 mb-3 small">
        <a href="{{ buildEmbeddedUrl($fp, ['view' => 'card']) }}" class="btn btn-sm {{ $viewMode === 'card' ? 'atom-btn-outline-success' : 'atom-btn-white' }}"><i class="fas fa-th-large"></i></a>
        <a href="{{ buildEmbeddedUrl($fp, ['view' => 'grid']) }}" class="btn btn-sm {{ $viewMode === 'grid' ? 'atom-btn-outline-success' : 'atom-btn-white' }}"><i class="fas fa-th"></i></a>
        <a href="{{ buildEmbeddedUrl($fp, ['view' => 'table']) }}" class="btn btn-sm {{ $viewMode === 'table' ? 'atom-btn-outline-success' : 'atom-btn-white' }}"><i class="fas fa-list"></i></a>

        <div class="dropdown">
          <button class="btn atom-btn-outline-success btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">{{ $limit }}/page</button>
          <ul class="dropdown-menu">
            @foreach($limitOptions as $opt)
              <li><a class="dropdown-item {{ $limit == $opt ? 'active' : '' }}" href="{{ buildEmbeddedUrl($fp, ['limit' => $opt]) }}">{{ $opt }}</a></li>
            @endforeach
          </ul>
        </div>

        <div class="dropdown ms-auto">
          <button class="btn atom-btn-outline-success btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Sort: {{ $sortLabels[$sort] ?? 'Title' }}</button>
          <ul class="dropdown-menu">
            @foreach($sortLabels as $sortKey => $sortLabel)
              <li><a class="dropdown-item {{ $sort === $sortKey ? 'active' : '' }}" href="{{ buildEmbeddedUrl($fp, ['sort' => $sortKey]) }}">{{ $sortLabel }}</a></li>
            @endforeach
          </ul>
        </div>

        <div class="dropdown">
          <button class="btn atom-btn-outline-success btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">{{ $sortDir === 'asc' ? 'Asc' : 'Desc' }}</button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item {{ $sortDir === 'asc' ? 'active' : '' }}" href="{{ buildEmbeddedUrl($fp, ['dir' => 'asc']) }}">Ascending</a></li>
            <li><a class="dropdown-item {{ $sortDir === 'desc' ? 'active' : '' }}" href="{{ buildEmbeddedUrl($fp, ['dir' => 'desc']) }}">Descending</a></li>
          </ul>
        </div>
      </div>

      {{-- Results Info --}}
      <div class="mb-3 text-muted small">
        Results {{ min((($page - 1) * $limit) + 1, $total) }} to {{ min($page * $limit, $total) }} of {{ $total }}
      </div>

      {{-- ===== GRID VIEW ===== --}}
      @if($viewMode === 'grid')
        @if(empty($objects))
          <div class="text-center text-muted py-5"><i class="fas fa-inbox fa-4x mb-3"></i><h4>{{ __('No results') }}</h4></div>
        @else
          <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
            @foreach($objects as $obj)
              @php
                $cfg = $typeConfig[$obj->object_type ?? ''] ?? ['icon' => 'fa-file', 'color' => 'secondary', 'label' => 'Unknown'];
                $objUrl = '/' . ($obj->slug ?? '');
              @endphp
              <div class="col">
                <div class="card h-100 shadow-sm">
                  <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:120px;overflow:hidden;">
                    @if(!empty($obj->thumbnail))
                      <a href="{{ $objUrl }}"><img src="{{ $obj->thumbnail }}" alt="" class="img-fluid" style="max-height:120px;object-fit:cover;"></a>
                    @else
                      <a href="{{ $objUrl }}"><i class="fas {{ $cfg['icon'] }} fa-3x text-{{ $cfg['color'] }}"></i></a>
                    @endif
                  </div>
                  <div class="card-body p-2">
                    <a href="{{ $objUrl }}" class="text-success text-decoration-none small d-block text-truncate">{{ e($obj->title ?? '[Untitled]') }}</a>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @endif

      {{-- ===== TABLE VIEW ===== --}}
      @elseif($viewMode === 'table')
        <div class="table-responsive">
          <table class="table table-hover table-sm">
            <thead class="table-light">
              <tr>
                <th style="width:60px"></th>
                <th>{{ __('Title') }}</th>
                <th style="width:100px">{{ __('Level') }}</th>
                <th style="width:100px">{{ __('Type') }}</th>
              </tr>
            </thead>
            <tbody>
              @if(empty($objects))
                <tr><td colspan="4" class="text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3"></i><br>No results</td></tr>
              @else
                @foreach($objects as $obj)
                  @php
                    $cfg = $typeConfig[$obj->object_type ?? ''] ?? ['icon' => 'fa-file', 'color' => 'secondary'];
                    $objUrl = '/' . ($obj->slug ?? '');
                  @endphp
                  <tr>
                    <td class="text-center">
                      @if(!empty($obj->thumbnail))
                        <img src="{{ $obj->thumbnail }}" alt="" class="rounded" style="width:50px;height:50px;object-fit:cover;">
                      @else
                        <i class="fas {{ $cfg['icon'] }} fa-2x text-{{ $cfg['color'] }}"></i>
                      @endif
                    </td>
                    <td>
                      <a href="{{ $objUrl }}" class="text-success text-decoration-none">{{ e($obj->title ?? '[Untitled]') }}</a>
                      @if(!empty($obj->identifier))<br><small class="text-muted">{{ e($obj->identifier) }}</small>@endif
                    </td>
                    <td><span class="badge bg-light text-dark">{{ e($obj->level_name ?? '-') }}</span></td>
                    <td><span class="badge bg-{{ $cfg['color'] }}">{{ ucfirst($obj->object_type ?? '?') }}</span></td>
                  </tr>
                @endforeach
              @endif
            </tbody>
          </table>
        </div>

      {{-- ===== CARD VIEW (default) ===== --}}
      @else
        @if(empty($objects))
          <div class="text-center text-muted py-5"><i class="fas fa-inbox fa-4x mb-3"></i><h4>{{ __('No results') }}</h4></div>
        @else
          @foreach($objects as $obj)
            @php
              $cfg = $typeConfig[$obj->object_type ?? ''] ?? ['icon' => 'fa-file', 'color' => 'secondary', 'label' => 'Unknown'];
              $objUrl = '/' . ($obj->slug ?? '');
            @endphp
            <div class="card mb-2 shadow-sm">
              <div class="row g-0">
                <div class="col-md-2 d-flex align-items-center justify-content-center p-2" style="background:#f8f9fa;">
                  @if(!empty($obj->thumbnail))
                    <a href="{{ $objUrl }}"><img src="{{ $obj->thumbnail }}" alt="" class="img-fluid rounded" style="max-height:100px;object-fit:contain;"></a>
                  @else
                    <a href="{{ $objUrl }}"><i class="fas {{ $cfg['icon'] }} fa-3x text-{{ $cfg['color'] }}"></i></a>
                  @endif
                </div>
                <div class="col-md-10">
                  <div class="card-body py-2">
                    <h6 class="card-title mb-1">
                      <a href="{{ $objUrl }}" class="text-success text-decoration-none">{{ e($obj->title ?? '[Untitled]') }}</a>
                    </h6>
                    <p class="card-text mb-1 small">
                      <span class="text-success">{{ e($obj->identifier ?? '') }}</span>
                      @if(!empty($obj->level_name))<span class="mx-1">&middot;</span>{{ e($obj->level_name) }}@endif
                      @if(($obj->child_count ?? 0) > 0)<span class="mx-1">&middot;</span><i class="fas fa-folder text-muted"></i> {{ $obj->child_count }}@endif
                    </p>
                    @if(!empty($obj->scope_and_content))
                      <p class="card-text text-muted small mb-1">{{ Str::limit(strip_tags($obj->scope_and_content), 120) }}</p>
                    @endif
                    <span class="badge bg-{{ $cfg['color'] }}">{{ $cfg['label'] }}</span>
                  </div>
                </div>
              </div>
            </div>
          @endforeach
        @endif
      @endif

      {{-- Pagination --}}
      @if($totalPages > 1)
        <nav class="mt-4">
          <ul class="pagination justify-content-center">
            <li class="page-item {{ $page <= 1 ? 'disabled' : '' }}">
              <a class="page-link" href="{{ buildEmbeddedUrl($fp, ['page' => $page - 1]) }}">Previous</a>
            </li>
            @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
              <li class="page-item {{ $i == $page ? 'active' : '' }}">
                <a class="page-link" href="{{ buildEmbeddedUrl($fp, ['page' => $i]) }}">{{ $i }}</a>
              </li>
            @endfor
            <li class="page-item {{ $page >= $totalPages ? 'disabled' : '' }}">
              <a class="page-link" href="{{ buildEmbeddedUrl($fp, ['page' => $page + 1]) }}">Next</a>
            </li>
          </ul>
        </nav>
      @endif

    </div>
  </div>
</div>

<style>
  .cursor-pointer { cursor: pointer; }
</style>
