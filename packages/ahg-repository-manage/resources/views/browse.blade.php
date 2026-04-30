@extends('theme::layouts.2col')

@section('title', config('app.ui_label_repository', 'Archival institution') . ' browse')
@section('body-class', 'repositoryManage browse')

@section('sidebar')
  <h2 class="d-grid">
    <button class="btn btn-lg atom-btn-white collapsed text-wrap" type="button"
            data-bs-toggle="collapse" data-bs-target="#collapse-aggregations"
            aria-expanded="false" aria-controls="collapse-aggregations">
      {{ __('Narrow your results by:') }}
    </button>
  </h2>

  <div class="collapse" id="collapse-aggregations">

    {{-- Language facet --}}
    @if(!empty($languageFacets))
      <div class="accordion mb-3">
        <div class="accordion-item aggregation">
          <h2 class="accordion-header" id="heading-languages">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#collapse-languages"
                    aria-expanded="false" aria-controls="collapse-languages">
              {{ __('Language') }}
            </button>
          </h2>
          <div id="collapse-languages" class="accordion-collapse collapse list-group list-group-flush"
               aria-labelledby="heading-languages">
            @php $currentLang = request('languages', ''); @endphp
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentLang === '' ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/repository/browse') }}?{{ http_build_query(request()->except(['languages', 'page'])) }}">All</a>
            @foreach($languageFacets as $langCode => $facet)
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentLang == $langCode ? 'active text-decoration-underline' : '' }}"
                 href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge(request()->except(['languages', 'page']), ['languages' => $langCode])) }}">
                {{ $facet['name'] }}
                <span class="ms-3 text-nowrap">{{ $facet['count'] }}</span>
              </a>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    {{-- Archive type facet --}}
    @if(!empty($archiveTypeFacets))
      <div class="accordion mb-3">
        <div class="accordion-item aggregation">
          <h2 class="accordion-header" id="heading-archiveType">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#collapse-archiveType"
                    aria-expanded="false" aria-controls="collapse-archiveType">
              {{ __('Archive type') }}
            </button>
          </h2>
          <div id="collapse-archiveType" class="accordion-collapse collapse list-group list-group-flush"
               aria-labelledby="heading-archiveType">
            @php $currentArchiveType = request('types', ''); @endphp
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentArchiveType === '' ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/repository/browse') }}?{{ http_build_query(request()->except(['types', 'page'])) }}">All</a>
            @foreach($archiveTypeFacets as $atId => $facet)
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentArchiveType == $atId ? 'active text-decoration-underline' : '' }}"
                 href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge(request()->except(['types', 'page']), ['types' => $atId])) }}">
                {{ $facet['name'] }}
                <span class="ms-3 text-nowrap">{{ $facet['count'] }}</span>
              </a>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    {{-- Geographic Region facet --}}
    @if(!empty($regions))
      <div class="accordion mb-3">
        <div class="accordion-item aggregation">
          <h2 class="accordion-header" id="heading-region">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#collapse-region"
                    aria-expanded="false" aria-controls="collapse-region">
              {{ __('Geographic Region') }}
            </button>
          </h2>
          <div id="collapse-region" class="accordion-collapse collapse list-group list-group-flush"
               aria-labelledby="heading-region">
            @php $currentRegion = request('regions', ''); @endphp
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentRegion === '' ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/repository/browse') }}?{{ http_build_query(request()->except(['regions', 'page'])) }}">All</a>
            @foreach($regions as $r)
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentRegion === $r->region ? 'active text-decoration-underline' : '' }}"
                 href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge(request()->except(['regions', 'page']), ['regions' => $r->region])) }}">
                {{ $r->region }}
                <span class="ms-3 text-nowrap">{{ $r->cnt }}</span>
              </a>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    {{-- Geographic Subregion facet --}}
    @if(!empty($subregionFacets))
      <div class="accordion mb-3">
        <div class="accordion-item aggregation">
          <h2 class="accordion-header" id="heading-subregion">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#collapse-subregion"
                    aria-expanded="false" aria-controls="collapse-subregion">
              {{ __('Geographic Subregion') }}
            </button>
          </h2>
          <div id="collapse-subregion" class="accordion-collapse collapse list-group list-group-flush"
               aria-labelledby="heading-subregion">
            @php $currentSubregion = request('geographicSubregions', ''); @endphp
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentSubregion === '' ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/repository/browse') }}?{{ http_build_query(request()->except(['geographicSubregions', 'page'])) }}">All</a>
            @foreach($subregionFacets as $srId => $facet)
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentSubregion == $srId ? 'active text-decoration-underline' : '' }}"
                 href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge(request()->except(['geographicSubregions', 'page']), ['geographicSubregions' => $srId])) }}">
                {{ $facet['name'] }}
                <span class="ms-3 text-nowrap">{{ $facet['count'] }}</span>
              </a>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    {{-- Locality facet --}}
    @if(!empty($localityFacets))
      <div class="accordion mb-3">
        <div class="accordion-item aggregation">
          <h2 class="accordion-header" id="heading-locality">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#collapse-locality"
                    aria-expanded="false" aria-controls="collapse-locality">
              {{ __('Locality') }}
            </button>
          </h2>
          <div id="collapse-locality" class="accordion-collapse collapse list-group list-group-flush"
               aria-labelledby="heading-locality">
            @php $currentLocality = request('locality', ''); @endphp
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentLocality === '' ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/repository/browse') }}?{{ http_build_query(request()->except(['locality', 'page'])) }}">All</a>
            @foreach($localityFacets as $loc => $facet)
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentLocality === $loc ? 'active text-decoration-underline' : '' }}"
                 href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge(request()->except(['locality', 'page']), ['locality' => $loc])) }}">
                {{ $facet['name'] }}
                <span class="ms-3 text-nowrap">{{ $facet['count'] }}</span>
              </a>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    {{-- Thematic Area facet --}}
    @if(!empty($thematicAreaFacets))
      <div class="accordion mb-3">
        <div class="accordion-item aggregation">
          <h2 class="accordion-header" id="heading-thematicArea">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#collapse-thematicArea"
                    aria-expanded="false" aria-controls="collapse-thematicArea">
              {{ __('Thematic Area') }}
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
      <span class="small" id="heading-label">{{ config('app.ui_label_repository', 'Archival institution') }}</span>
    </div>
  </div>
@endsection

@section('before-content')
  {{-- Filter tags --}}
  @if(isset($filterTags) && count($filterTags) > 0)
    <div class="d-flex flex-wrap gap-2 mb-2">
      @foreach($filterTags as $tag)
        <a href="{{ $tag['removeUrl'] ?? '#' }}" class="btn btn-sm atom-btn-white filter-tag d-flex">
          <span class="visually-hidden">{{ __('Remove filter:') }}</span>
          <span class="text-truncate d-inline-block">{{ $tag['label'] ?? '' }}</span>
          <i aria-hidden="true" class="fas fa-times ms-2 align-self-center"></i>
        </a>
      @endforeach
    </div>
  @endif

  <div class="d-inline-block mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search ' . mb_strtolower(config('app.ui_label_repository', 'Archival institution')),
        'landmarkLabel' => config('app.ui_label_repository', 'Archival institution'),
    ])
  </div>

  {{-- Advanced Search Accordion --}}
  <div class="accordion mb-3" role="search" data-default-closed>
    <div class="accordion-item">
      <h2 class="accordion-header" id="heading-adv-search">
        <button class="accordion-button collapsed" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapse-adv-search"
                aria-expanded="false" aria-controls="collapse-adv-search">
          {{ __('Advanced search options') }}
        </button>
      </h2>
      <div id="collapse-adv-search" class="accordion-collapse collapse" aria-labelledby="heading-adv-search">
        <div class="accordion-body">
          <form method="get">
            @foreach(request()->except(['thematicAreas', 'types', 'regions', 'page']) as $hk => $hv)
              @if($hv !== '' && $hv !== null)
                <input type="hidden" name="{{ $hk }}" value="{{ $hv }}">
              @endif
            @endforeach

            <div class="row mb-4">

              <div class="col-md-4">
                <label class="form-label" for="thematicAreas">{{ __('Thematic area') }}</label>
                <select class="form-select" name="thematicAreas" id="thematicAreas">
                  <option selected="selected"></option>
                  @foreach($thematicAreaOptions ?? [] as $ta)
                    <option value="{{ $ta->id }}" {{ request('thematicAreas') == $ta->id ? 'selected' : '' }}>{{ $ta->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label" for="types">{{ __('Archive type') }}</label>
                <select class="form-select" name="types" id="types">
                  <option selected="selected"></option>
                  @foreach($repositoryTypes ?? [] as $rt)
                    <option value="{{ $rt->id }}" {{ request('types') == $rt->id ? 'selected' : '' }}>{{ $rt->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label" for="regions">{{ __('Region') }}</label>
                <select class="form-select" name="regions" id="regions">
                  <option selected="selected"></option>
                  @foreach($regions ?? [] as $r)
                    <option value="{{ $r->region }}" {{ request('regions') === $r->region ? 'selected' : '' }}>{{ $r->region }}</option>
                  @endforeach
                </select>
              </div>

            </div>

            <ul class="actions mb-1 nav gap-2 justify-content-center">
              <li><input type="submit" class="btn atom-btn-outline-light" value="Set filters"></li>
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
    <div class="btn-group" role="group" aria-label="{{ __('Display mode') }}">
      <a href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge($baseQuery, ['displayMode' => 'list'])) }}"
         class="btn btn-sm {{ $displayMode === 'list' ? 'atom-btn-secondary' : 'atom-btn-white' }}" title="{{ __('Compact table/list view') }}">
        <i class="fas fa-list me-1" aria-hidden="true"></i> {{ __('Table view') }}
      </a>
      <a href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge($baseQuery, ['displayMode' => 'grid'])) }}"
         class="btn btn-sm {{ $displayMode === 'grid' ? 'atom-btn-secondary' : 'atom-btn-white' }}" title="{{ __('Thumbnail grid with cards') }}">
        <i class="fas fa-th me-1" aria-hidden="true"></i> {{ __('Card view') }}
      </a>
    </div>

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'lastUpdated',
      ])

      @php
        $currentSort = request('sort', 'lastUpdated');
        $currentDir = request('sortDir', 'asc');
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
      <div class="row g-3 mb-3">
        @foreach($pager->getResults() as $doc)
          <div class="col-sm-6 col-lg-4">
            <div class="card h-100">
              @if(!empty($doc['logo']))
                <a href="{{ route('repository.show', $doc['slug']) }}">
                  <img alt="{{ $doc['name'] ?: '' }}" class="card-img-top" src="{{ $doc['logo'] }}">
                </a>
              @else
                <a class="p-3" href="{{ route('repository.show', $doc['slug']) }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
              @endif
              <div class="card-body">
                <div class="card-text d-flex align-items-start gap-2">
                  <span>{{ $doc['name'] ?: '[Untitled]' }}</span>
                  <button class="btn atom-btn-white ms-auto active-primary clipboard"
                          data-clipboard-slug="{{ $doc['slug'] }}" data-clipboard-type="repository"
                          data-tooltip="true" data-title="{{ __('Add to clipboard') }}" data-alt-title="{{ __('Remove from clipboard') }}">
                    <i class="fas fa-lg fa-paperclip" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('Add to clipboard') }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @else
      <div class="table-responsive mb-3">
        <table class="table table-bordered mb-0">
          <thead>
            <tr>
              <th class="sortable w-40">
                <a title="{{ __('Sort') }}" class="sortable" href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge(request()->except(['sort', 'page']), ['sort' => request('sort') === 'nameUp' ? 'nameDown' : 'nameUp'])) }}">Name</a>
              </th>
              <th class="sortable w-20">
                <a title="{{ __('Sort') }}" class="sortable" href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge(request()->except(['sort', 'page']), ['sort' => request('sort') === 'regionUp' ? 'regionDown' : 'regionUp'])) }}">Region</a>
              </th>
              <th class="sortable w-20">
                <a title="{{ __('Sort') }}" class="sortable" href="{{ url('/repository/browse') }}?{{ http_build_query(array_merge(request()->except(['sort', 'page']), ['sort' => request('sort') === 'localityUp' ? 'localityDown' : 'localityUp'])) }}">Locality</a>
              </th>
              <th class="w-20">{{ __('Thematic area') }}</th>
              <th><span class="visually-hidden">{{ __('Clipboard') }}</span></th>
            </tr>
          </thead>
          <tbody>
            @foreach($pager->getResults() as $doc)
              <tr>
                <td>
                  @if(!empty($doc['logo']))
                    <p><img class="img-thumbnail" width="100" src="{{ $doc['logo'] }}" alt=""></p>
                  @endif
                  <a href="{{ route('repository.show', $doc['slug']) }}" title="{{ $doc['name'] ?: '[Untitled]' }}">{{ $doc['name'] ?: '[Untitled]' }}</a>
                </td>
                <td>{{ $doc['region'] ?? '' }}</td>
                <td>{{ $doc['locality'] ?? '' }}</td>
                <td>{{ $doc['thematic_area'] ?? '' }}</td>
                <td>
                  <button class="btn atom-btn-white ms-auto active-primary clipboard"
                          data-clipboard-slug="{{ $doc['slug'] }}" data-clipboard-type="repository"
                          data-tooltip="true" data-title="{{ __('Add to clipboard') }}" data-alt-title="{{ __('Remove from clipboard') }}">
                    <i class="fas fa-lg fa-paperclip" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('Add to clipboard') }}</span>
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  @endif
@endsection

@section('after-content')
  @include('ahg-core::components.pager', ['pager' => $pager])
@endsection
