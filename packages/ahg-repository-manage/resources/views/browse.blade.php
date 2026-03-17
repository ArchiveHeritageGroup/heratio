@extends('theme::layouts.2col')

@section('title', 'Archival institutions')
@section('body-class', 'browse repository')

@section('sidebar')
  <h2 class="d-grid">
    <button class="btn btn-lg atom-btn-white collapsed text-wrap" type="button"
            data-bs-toggle="collapse" data-bs-target="#collapse-aggregations"
            aria-expanded="false" aria-controls="collapse-aggregations">
      Narrow your results by:
    </button>
  </h2>

  <div class="collapse" id="collapse-aggregations">
    {{-- Thematic Area facet --}}
    @if(!empty($thematicAreaFacets))
      <div class="accordion mb-3">
        <div class="accordion-item aggregation">
          <h2 class="accordion-header" id="heading-thematicArea">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#collapse-thematicArea"
                    aria-expanded="false" aria-controls="collapse-thematicArea">
              Thematic area
            </button>
          </h2>
          <div id="collapse-thematicArea" class="accordion-collapse collapse list-group list-group-flush"
               aria-labelledby="heading-thematicArea">
            @php $currentThematic = request('thematicAreas', ''); @endphp
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentThematic === '' ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/repository/browse') }}?{{ http_build_query(request()->except(['thematicAreas', 'page'])) }}">All</a>
            @foreach($thematicAreaFacets as $taId => $facet)
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentThematic == $taId ? 'active text-decoration-underline' : '' }}"
                 href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge(request()->except(['thematicAreas', 'page']), ['thematicAreas' => $taId])) }}">
                {{ $facet['name'] }}
                <span class="ms-3 text-nowrap">{{ $facet['count'] }}</span>
              </a>
            @endforeach
          </div>
        </div>
      </div>
    @endif
  </div>
@endsection

@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-university me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0" aria-describedby="heading-label">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small" id="heading-label">Archival institution</span>
    </div>
  </div>
@endsection

@section('before-content')
  <div class="d-inline-block mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search archival institution',
        'landmarkLabel' => 'Archival institution',
    ])
  </div>

  {{-- Advanced Search Accordion --}}
  <div class="accordion mb-3 adv-search" role="search">
    <div class="accordion-item">
      <h2 class="accordion-header" id="heading-adv-search">
        <button class="accordion-button collapsed" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapse-adv-search"
                aria-expanded="false" aria-controls="collapse-adv-search">
          Advanced search options
        </button>
      </h2>
      <div id="collapse-adv-search" class="accordion-collapse collapse" aria-labelledby="heading-adv-search">
        <div class="accordion-body">
          <form method="get" action="{{ url('/repository/browse') }}">
            @if(request('sort'))
              <input type="hidden" name="sort" value="{{ request('sort') }}">
            @endif
            @if(request('sortDir'))
              <input type="hidden" name="sortDir" value="{{ request('sortDir') }}">
            @endif

            <div class="row mb-4">
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label" for="thematicAreas">Thematic area</label>
                  <select class="form-select" name="thematicAreas" id="thematicAreas">
                    <option value=""></option>
                    @foreach($thematicAreaFacets as $taId => $facet)
                      <option value="{{ $taId }}" {{ ($params['thematicArea'] ?? '') == $taId ? 'selected' : '' }}>{{ $facet['name'] }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label" for="region">Region/Province</label>
                  <select class="form-select" name="region" id="region">
                    <option value=""></option>
                    @foreach($regions as $r)
                      <option value="{{ $r->region }}" {{ ($params['region'] ?? '') === $r->region ? 'selected' : '' }}>{{ $r->region }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label" for="locality">Locality</label>
                  <input type="text" class="form-control" name="locality" id="locality" value="{{ $params['locality'] ?? '' }}" placeholder="City or town">
                </div>
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label" for="hasDigitalObject">Digital object available</label>
                  <select class="form-select" name="hasDigitalObject" id="hasDigitalObject">
                    <option value=""></option>
                    <option value="1" {{ ($params['hasDigitalObject'] ?? '') === '1' ? 'selected' : '' }}>Yes</option>
                  </select>
                </div>
              </div>
            </div>

            <ul class="actions mb-1 nav gap-2 justify-content-center">
              <li><input type="button" class="btn atom-btn-outline-danger reset" value="Reset" onclick="this.closest('form').querySelectorAll('select').forEach(s=>s.selectedIndex=0); this.closest('form').querySelectorAll('input[type=text]').forEach(i=>i.value='');"></li>
              <li><input type="submit" class="btn atom-btn-outline-light" value="Search"></li>
            </ul>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('content')
  {{-- Display mode toggle + Sort controls --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    @php
      $displayMode = request('displayMode', 'list');
      $baseQuery = request()->except(['displayMode', 'page']);
    @endphp
    <div class="btn-group" role="group" aria-label="Display mode">
      <a href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge($baseQuery, ['displayMode' => 'grid'])) }}"
         class="btn btn-sm {{ $displayMode === 'grid' ? 'atom-btn-secondary' : 'atom-btn-white' }}" title="Thumbnail grid with cards">
        <i class="fas fa-th" aria-hidden="true"></i><span class="visually-hidden">Grid</span>
      </a>
      <a href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge($baseQuery, ['displayMode' => 'list'])) }}"
         class="btn btn-sm {{ $displayMode === 'list' ? 'atom-btn-secondary' : 'atom-btn-white' }}" title="Compact table/list view">
        <i class="fas fa-list" aria-hidden="true"></i><span class="visually-hidden">List</span>
      </a>
    </div>

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'alphabetic',
      ])

      @php
        $currentSort = request('sort', 'alphabetic');
        $currentDir = request('sortDir', ($currentSort === 'lastUpdated' ? 'desc' : 'asc'));
        $dirQuery = request()->except(['sortDir', 'page']);
      @endphp
      <div class="dropdown d-inline-block">
        <button class="btn btn-sm atom-btn-white dropdown-toggle text-wrap" type="button" id="sortDir-button" data-bs-toggle="dropdown" aria-expanded="false">
          Direction: {{ $currentDir === 'desc' ? 'Descending' : 'Ascending' }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="sortDir-button">
          <li><a href="{{ request()->url() }}?{{ http_build_query(array_merge($dirQuery, ['sortDir' => 'asc'])) }}" class="dropdown-item {{ $currentDir === 'asc' ? 'active' : '' }}">Ascending</a></li>
          <li><a href="{{ request()->url() }}?{{ http_build_query(array_merge($dirQuery, ['sortDir' => 'desc'])) }}" class="dropdown-item {{ $currentDir === 'desc' ? 'active' : '' }}">Descending</a></li>
        </ul>
      </div>
    </div>
  </div>

  @if($pager->getNbResults())
    @if($displayMode === 'grid')
      <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3 mb-3">
        @foreach($pager->getResults() as $doc)
          <div class="col">
            <article class="card h-100">
              <div class="card-body">
                <h5 class="card-title text-truncate">
                  <a href="{{ route('repository.show', $doc['slug']) }}">{{ $doc['name'] ?: '[Untitled]' }}</a>
                </h5>
                @if(!empty($doc['identifier']))
                  <p class="card-text small text-muted mb-0">{{ $doc['identifier'] }}</p>
                @endif
              </div>
            </article>
          </div>
        @endforeach
      </div>
    @else
      @foreach($pager->getResults() as $doc)
        <article class="search-result row g-0 p-3 border-bottom">
          <div class="col-12 d-flex flex-column gap-1">
            <div class="d-flex align-items-center gap-2 mw-100">
              <a class="h5 mb-0 text-truncate" href="{{ route('repository.show', $doc['slug']) }}" title="{{ $doc['name'] ?: '[Untitled]' }}">
                {{ $doc['name'] ?: '[Untitled]' }}
              </a>
              <button class="btn atom-btn-white ms-auto clipboard"
                      data-clipboard-slug="{{ $doc['slug'] }}" data-clipboard-type="repository"
                      title="Add to clipboard">
                <i class="fas fa-lg fa-paperclip" aria-hidden="true"></i>
                <span class="visually-hidden">Add to clipboard</span>
              </button>
            </div>
            <div class="d-flex flex-wrap">
              @if(!empty($doc['identifier']))
                <span class="text-primary me-2">{{ $doc['identifier'] }}</span>
              @endif
              @if(request('sort') === 'lastUpdated' && !empty($doc['updated_at']))
                <span class="text-muted">{{ \Carbon\Carbon::parse($doc['updated_at'])->format('Y-m-d') }}</span>
              @endif
            </div>
          </div>
        </article>
      @endforeach
    @endif
  @endif
@endsection

@section('after-content')
  @include('ahg-core::components.pager', ['pager' => $pager])
@endsection
