@extends('theme::layouts.2col')

@section('title', 'Authority records')
@section('body-class', 'browse actor')

@section('sidebar')
  <h2 class="d-grid">
    <button class="btn btn-lg atom-btn-white collapsed text-wrap" type="button"
            data-bs-toggle="collapse" data-bs-target="#collapse-aggregations"
            aria-expanded="false" aria-controls="collapse-aggregations">
      Narrow your results by:
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
            Language
          </button>
        </h2>
        <div id="collapse-languages" class="accordion-collapse collapse list-group list-group-flush"
             aria-labelledby="heading-languages">
          @php
            $currentLang = request('languages', '');
            $langParams = request()->except(['languages', 'page']);
          @endphp
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentLang === '' ? 'active text-decoration-underline' : '' }}"
             href="{{ url('/actor/browse') }}?{{ http_build_query($langParams) }}" title="All">All</a>
          @foreach($languageFacets as $langCode => $facet)
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentLang == $langCode ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/actor/browse') }}?{{ http_build_query(array_merge($langParams, ['languages' => $langCode])) }}"
               title="{{ $facet['name'] }}, {{ $facet['count'] }} results">
              {{ $facet['name'] }}
              <span class="visually-hidden">, {{ $facet['count'] }} results</span>
              <span aria-hidden="true" class="ms-3 text-nowrap">{{ $facet['count'] }}</span>
            </a>
          @endforeach
        </div>
      </div>
    </div>
    @endif

    {{-- Entity Type facet --}}
    <div class="accordion mb-3">
      <div class="accordion-item aggregation">
        <h2 class="accordion-header" id="heading-entityType">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#collapse-entityType"
                  aria-expanded="false" aria-controls="collapse-entityType">
            Entity type
          </button>
        </h2>
        <div id="collapse-entityType" class="accordion-collapse collapse list-group list-group-flush"
             aria-labelledby="heading-entityType">
          @php
            $currentEntityType = request('entityType', '');
            $queryParams = request()->except(['entityType', 'page']);
          @endphp
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentEntityType === '' ? 'active text-decoration-underline' : '' }}"
             href="{{ url('/actor/browse') }}?{{ http_build_query($queryParams) }}" title="All">All</a>
          @foreach($entityTypeFacets as $typeId => $facet)
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentEntityType == $typeId ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/actor/browse') }}?{{ http_build_query(array_merge($queryParams, ['entityType' => $typeId])) }}"
               title="{{ $facet['name'] }}, {{ $facet['count'] }} results">
              {{ $facet['name'] }}
              <span class="visually-hidden">, {{ $facet['count'] }} results</span>
              <span aria-hidden="true" class="ms-3 text-nowrap">{{ $facet['count'] }}</span>
            </a>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Maintained by facet --}}
    @if(!empty($maintainedByFacets))
    <div class="accordion mb-3">
      <div class="accordion-item aggregation">
        <h2 class="accordion-header" id="heading-maintainedBy">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#collapse-maintainedBy"
                  aria-expanded="false" aria-controls="collapse-maintainedBy">
            Maintained by
          </button>
        </h2>
        <div id="collapse-maintainedBy" class="accordion-collapse collapse list-group list-group-flush"
             aria-labelledby="heading-maintainedBy">
          @php
            $currentMaintainedBy = request('maintainedBy', '');
            $mbParams = request()->except(['maintainedBy', 'page']);
          @endphp
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentMaintainedBy === '' ? 'active text-decoration-underline' : '' }}"
             href="{{ url('/actor/browse') }}?{{ http_build_query($mbParams) }}" title="All">All</a>
          @foreach($maintainedByFacets as $mbId => $facet)
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentMaintainedBy == $mbId ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/actor/browse') }}?{{ http_build_query(array_merge($mbParams, ['maintainedBy' => $mbId])) }}"
               title="{{ $facet['name'] }}, {{ $facet['count'] }} results">
              {{ $facet['name'] }}
              <span class="visually-hidden">, {{ $facet['count'] }} results</span>
              <span aria-hidden="true" class="ms-3 text-nowrap">{{ $facet['count'] }}</span>
            </a>
          @endforeach
        </div>
      </div>
    </div>
    @endif

    {{-- Occupation facet --}}
    @if(!empty($occupationFacets))
    <div class="accordion mb-3">
      <div class="accordion-item aggregation">
        <h2 class="accordion-header" id="heading-occupation">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#collapse-occupation"
                  aria-expanded="false" aria-controls="collapse-occupation">
            Occupation
          </button>
        </h2>
        <div id="collapse-occupation" class="accordion-collapse collapse list-group list-group-flush"
             aria-labelledby="heading-occupation">
          @php
            $currentOccupation = request('occupation', '');
            $occParams = request()->except(['occupation', 'page']);
          @endphp
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentOccupation === '' ? 'active text-decoration-underline' : '' }}"
             href="{{ url('/actor/browse') }}?{{ http_build_query($occParams) }}" title="All">All</a>
          @foreach($occupationFacets as $occId => $facet)
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentOccupation == $occId ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/actor/browse') }}?{{ http_build_query(array_merge($occParams, ['occupation' => $occId])) }}"
               title="{{ $facet['name'] }}, {{ $facet['count'] }} results">
              {{ $facet['name'] }}
              <span class="visually-hidden">, {{ $facet['count'] }} results</span>
              <span aria-hidden="true" class="ms-3 text-nowrap">{{ $facet['count'] }}</span>
            </a>
          @endforeach
        </div>
      </div>
    </div>
    @endif

    {{-- Place facet --}}
    @if(!empty($placeFacets))
    <div class="accordion mb-3">
      <div class="accordion-item aggregation">
        <h2 class="accordion-header" id="heading-place">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#collapse-place"
                  aria-expanded="false" aria-controls="collapse-place">
            Place
          </button>
        </h2>
        <div id="collapse-place" class="accordion-collapse collapse list-group list-group-flush"
             aria-labelledby="heading-place">
          @php
            $currentPlace = request('place', '');
            $placeParams = request()->except(['place', 'page']);
          @endphp
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentPlace === '' ? 'active text-decoration-underline' : '' }}"
             href="{{ url('/actor/browse') }}?{{ http_build_query($placeParams) }}" title="All">All</a>
          @foreach($placeFacets as $placeId => $facet)
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentPlace == $placeId ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/actor/browse') }}?{{ http_build_query(array_merge($placeParams, ['place' => $placeId])) }}"
               title="{{ $facet['name'] }}, {{ $facet['count'] }} results">
              {{ $facet['name'] }}
              <span class="visually-hidden">, {{ $facet['count'] }} results</span>
              <span aria-hidden="true" class="ms-3 text-nowrap">{{ $facet['count'] }}</span>
            </a>
          @endforeach
        </div>
      </div>
    </div>
    @endif

    {{-- Subject facet --}}
    @if(!empty($subjectFacets))
    <div class="accordion mb-3">
      <div class="accordion-item aggregation">
        <h2 class="accordion-header" id="heading-subject">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#collapse-subject"
                  aria-expanded="false" aria-controls="collapse-subject">
            Subject
          </button>
        </h2>
        <div id="collapse-subject" class="accordion-collapse collapse list-group list-group-flush"
             aria-labelledby="heading-subject">
          @php
            $currentSubject = request('subject', '');
            $subParams = request()->except(['subject', 'page']);
          @endphp
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentSubject === '' ? 'active text-decoration-underline' : '' }}"
             href="{{ url('/actor/browse') }}?{{ http_build_query($subParams) }}" title="All">All</a>
          @foreach($subjectFacets as $subId => $facet)
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentSubject == $subId ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/actor/browse') }}?{{ http_build_query(array_merge($subParams, ['subject' => $subId])) }}"
               title="{{ $facet['name'] }}, {{ $facet['count'] }} results">
              {{ $facet['name'] }}
              <span class="visually-hidden">, {{ $facet['count'] }} results</span>
              <span aria-hidden="true" class="ms-3 text-nowrap">{{ $facet['count'] }}</span>
            </a>
          @endforeach
        </div>
      </div>
    </div>
    @endif

    {{-- Media type facet --}}
    @if(!empty($mediaTypeFacets))
    <div class="accordion mb-3">
      <div class="accordion-item aggregation">
        <h2 class="accordion-header" id="heading-mediaType">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#collapse-mediaType"
                  aria-expanded="false" aria-controls="collapse-mediaType">
            Media type
          </button>
        </h2>
        <div id="collapse-mediaType" class="accordion-collapse collapse list-group list-group-flush"
             aria-labelledby="heading-mediaType">
          @php
            $currentMediaType = request('mediaType', '');
            $mtParams = request()->except(['mediaType', 'page']);
          @endphp
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentMediaType === '' ? 'active text-decoration-underline' : '' }}"
             href="{{ url('/actor/browse') }}?{{ http_build_query($mtParams) }}" title="All">All</a>
          @foreach($mediaTypeFacets as $mtId => $facet)
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentMediaType == $mtId ? 'active text-decoration-underline' : '' }}"
               href="{{ url('/actor/browse') }}?{{ http_build_query(array_merge($mtParams, ['mediaType' => $mtId])) }}"
               title="{{ $facet['name'] }}, {{ $facet['count'] }} results">
              {{ $facet['name'] }}
              <span class="visually-hidden">, {{ $facet['count'] }} results</span>
              <span aria-hidden="true" class="ms-3 text-nowrap">{{ $facet['count'] }}</span>
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
    <i class="fas fa-3x fa-user me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0" aria-describedby="heading-label">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small" id="heading-label">Authority record</span>
    </div>
  </div>
@endsection

@section('before-content')
  <div class="d-inline-block mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search authority record',
        'landmarkLabel' => 'Authority record',
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
          <form name="advanced-search-form" method="get" action="{{ url('/actor/browse') }}">
            @if(request('sort'))
              <input type="hidden" name="sort" value="{{ request('sort') }}">
            @endif
            @if(request('sortDir'))
              <input type="hidden" name="sortDir" value="{{ request('sortDir') }}">
            @endif

            <h5>Find results with:</h5>
            <div class="criteria mb-4">
              {{-- First criterion row --}}
              <div class="criterion row align-items-center" id="criterion-0">
                <div class="col-xl-auto mb-3 adv-search-boolean">
                  <select class="form-select" name="so0" aria-label="Boolean">
                    <option value="and" {{ ($params['so0'] ?? '') === 'and' ? 'selected' : '' }}>and</option>
                    <option value="or" {{ ($params['so0'] ?? '') === 'or' ? 'selected' : '' }}>or</option>
                    <option value="not" {{ ($params['so0'] ?? '') === 'not' ? 'selected' : '' }}>not</option>
                  </select>
                </div>
                <div class="col-xl-auto flex-grow-1 mb-3">
                  <input class="form-control" type="text" aria-label="Search" placeholder="Search" name="sq0" value="{{ $params['sq0'] ?? '' }}">
                </div>
                <div class="col-xl-auto mb-3 text-center">
                  <span class="form-text">in</span>
                </div>
                <div class="col-xl-auto mb-3">
                  @php
                    $fieldOptions = [
                      '' => 'Any field',
                      'authorizedFormOfName' => 'Authorized form of name',
                      'parallelNames' => 'Parallel form(s) of name',
                      'otherNames' => 'Other name(s)',
                      'datesOfExistence' => 'Dates of existence',
                      'history' => 'History',
                      'places' => 'Places',
                      'legalStatus' => 'Legal status',
                      'generalContext' => 'General context',
                      'descriptionIdentifier' => 'Description identifier',
                      'institutionResponsibleIdentifier' => 'Institution identifier',
                      'sources' => 'Sources',
                    ];
                  @endphp
                  <select class="form-select" name="sf0">
                    @foreach($fieldOptions as $val => $label)
                      <option value="{{ $val }}" {{ ($params['sf0'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-xl-auto mb-3">
                  <a href="#" class="delete-criterion" aria-label="Delete criterion">
                    <i aria-hidden="true" class="fas fa-times text-muted"></i>
                  </a>
                </div>
              </div>

              {{-- Template for additional criteria (cloned via JS) --}}
              <template id="criterion-template">
                <div class="criterion row align-items-center">
                  <div class="col-xl-auto mb-3 adv-search-boolean">
                    <select class="form-select" aria-label="Boolean">
                      <option value="and">and</option>
                      <option value="or">or</option>
                      <option value="not">not</option>
                    </select>
                  </div>
                  <div class="col-xl-auto flex-grow-1 mb-3">
                    <input class="form-control" type="text" aria-label="Search" placeholder="Search">
                  </div>
                  <div class="col-xl-auto mb-3 text-center">
                    <span class="form-text">in</span>
                  </div>
                  <div class="col-xl-auto mb-3">
                    <select class="form-select">
                      @foreach($fieldOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-xl-auto mb-3">
                    <a href="#" class="delete-criterion" aria-label="Delete criterion">
                      <i aria-hidden="true" class="fas fa-times text-muted"></i>
                    </a>
                  </div>
                </div>
              </template>

              <div class="add-new-criteria mb-3">
                <a id="add-criterion-dropdown-menu" class="btn atom-btn-white dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Add new criteria</a>
                <ul class="dropdown-menu mt-2" aria-labelledby="add-criterion-dropdown-menu">
                  <li><a class="dropdown-item add-criterion" href="#" data-bool="and">And</a></li>
                  <li><a class="dropdown-item add-criterion" href="#" data-bool="or">Or</a></li>
                  <li><a class="dropdown-item add-criterion" href="#" data-bool="not">Not</a></li>
                </ul>
              </div>
            </div>

            <h5>Limit results to:</h5>
            <div class="criteria mb-4">
              <div class="mb-3">
                <label class="form-label" for="repository">Repository <span class="badge bg-warning ms-1">Recommended</span></label>
                <select name="repository" class="form-select" id="repository">
                  <option value=""></option>
                  @foreach($repositories as $repo)
                    <option value="{{ $repo->id }}" {{ ($params['repository'] ?? '') == $repo->id ? 'selected' : '' }}>
                      {{ $repo->name }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>

            <h5>Filter results by:</h5>
            <div class="criteria row mb-2">
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label" for="hasDigitalObject">Digital object available <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select name="hasDigitalObject" class="form-select" id="hasDigitalObject">
                    <option value=""></option>
                    <option value="1" {{ ($params['hasDigitalObject'] ?? '') === '1' ? 'selected' : '' }}>Yes</option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label" for="entityTypeFilter">Entity type <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select name="entityType" class="form-select" id="entityTypeFilter">
                    <option value=""></option>
                    @foreach($entityTypeFacets as $typeId => $facet)
                      <option value="{{ $typeId }}" {{ ($params['entityType'] ?? '') == $typeId ? 'selected' : '' }}>
                        {{ $facet['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label" for="emptyField">Empty field <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select name="emptyField" class="form-select" id="emptyField">
                    <option value=""></option>
                    <option value="authorizedFormOfName" {{ ($params['emptyField'] ?? '') === 'authorizedFormOfName' ? 'selected' : '' }}>Name</option>
                    <option value="datesOfExistence" {{ ($params['emptyField'] ?? '') === 'datesOfExistence' ? 'selected' : '' }}>Dates of existence</option>
                    <option value="history" {{ ($params['emptyField'] ?? '') === 'history' ? 'selected' : '' }}>History</option>
                    <option value="places" {{ ($params['emptyField'] ?? '') === 'places' ? 'selected' : '' }}>Places</option>
                    <option value="legalStatus" {{ ($params['emptyField'] ?? '') === 'legalStatus' ? 'selected' : '' }}>Legal status</option>
                    <option value="generalContext" {{ ($params['emptyField'] ?? '') === 'generalContext' ? 'selected' : '' }}>General context</option>
                    <option value="descriptionIdentifier" {{ ($params['emptyField'] ?? '') === 'descriptionIdentifier' ? 'selected' : '' }}>Description identifier</option>
                  </select>
                </div>
              </div>
            </div>

            <h5>Find results where:</h5>
            <div class="criteria row mb-2">
              <div class="col-md-3">
                <div class="mb-3">
                  <label class="form-label" for="relatedType">Relationship <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select name="relatedType" class="form-select" id="relatedType">
                    <option value=""></option>
                    <option value="159" {{ ($params['relatedType'] ?? '') === '159' ? 'selected' : '' }}>Draft</option>
                    <option value="160" {{ ($params['relatedType'] ?? '') === '160' ? 'selected' : '' }}>Published</option>
                  </select>
                </div>
              </div>
              <div class="col-md-9">
                <div class="mb-3">
                  <label class="form-label" for="relatedAuthority">Related Authority record <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" class="form-control" name="relatedAuthority" id="relatedAuthority" value="{{ $params['relatedAuthority'] ?? '' }}" placeholder="Type to search authority records...">
                </div>
              </div>
            </div>

            <ul class="actions mb-1 nav gap-2 justify-content-center">
              <li><input type="button" class="btn atom-btn-outline-danger reset" value="Reset"></li>
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
      <a href="{{ url('/actor/browse') }}?{{ http_build_query(array_merge($baseQuery, ['displayMode' => 'grid'])) }}"
         class="btn btn-sm {{ $displayMode === 'grid' ? 'atom-btn-secondary' : 'atom-btn-white' }}"
         title="Thumbnail grid with cards">
        <i class="fas fa-th" aria-hidden="true"></i>
        <span class="visually-hidden">Grid</span>
      </a>
      <a href="{{ url('/actor/browse') }}?{{ http_build_query(array_merge($baseQuery, ['displayMode' => 'list'])) }}"
         class="btn btn-sm {{ $displayMode === 'list' ? 'atom-btn-secondary' : 'atom-btn-white' }}"
         title="Compact table/list view">
        <i class="fas fa-list" aria-hidden="true"></i>
        <span class="visually-hidden">List</span>
      </a>
    </div>

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'alphabetic',
      ])

      {{-- Sort direction --}}
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
          <li>
            <a href="{{ request()->url() }}?{{ http_build_query(array_merge($dirQuery, ['sortDir' => 'asc'])) }}"
               class="dropdown-item {{ $currentDir === 'asc' ? 'active' : '' }}">Ascending</a>
          </li>
          <li>
            <a href="{{ request()->url() }}?{{ http_build_query(array_merge($dirQuery, ['sortDir' => 'desc'])) }}"
               class="dropdown-item {{ $currentDir === 'desc' ? 'active' : '' }}">Descending</a>
          </li>
        </ul>
      </div>
    </div>
  </div>

  @if($pager->getNbResults())
    @if($displayMode === 'grid')
      {{-- Grid/card view --}}
      <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3 mb-3">
        @foreach($pager->getResults() as $doc)
          <div class="col">
            <article class="card h-100">
              <div class="card-body">
                <h5 class="card-title text-truncate">
                  <a href="{{ route('actor.show', $doc['slug']) }}">
                    {{ $doc['name'] ?: '[Untitled]' }}
                  </a>
                </h5>
                @if(!empty($doc['entity_type_id']) && isset($entityTypeNames[$doc['entity_type_id']]))
                  <span class="badge bg-secondary">{{ $entityTypeNames[$doc['entity_type_id']] }}</span>
                @endif
                @if(!empty($doc['identifier']))
                  <p class="card-text small text-muted mt-1 mb-0">{{ $doc['identifier'] }}</p>
                @endif
              </div>
            </article>
          </div>
        @endforeach
      </div>
    @else
      {{-- List/table view --}}
      <div id="content-results">
        @foreach($pager->getResults() as $doc)
          <article class="search-result row g-0 p-3 border-bottom">
            <div class="col-12 d-flex flex-column gap-1">
              <div class="d-flex align-items-center gap-2 mw-100">
                <a class="h5 mb-0 text-truncate" href="{{ route('actor.show', $doc['slug']) }}" title="{{ $doc['name'] ?: '[Untitled]' }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
                <button class="btn atom-btn-white ms-auto active-primary clipboard"
                        data-clipboard-slug="{{ $doc['slug'] }}" data-clipboard-type="actor"
                        data-tooltip="true" data-title="Add to clipboard" data-alt-title="Remove from clipboard">
                  <i class="fas fa-lg fa-paperclip" aria-hidden="true"></i>
                  <span class="visually-hidden">Add to clipboard</span>
                </button>
              </div>
              <div class="d-flex flex-column gap-2">
                <div class="d-flex flex-wrap">
                  @if(!empty($doc['identifier']))
                    <span class="text-primary me-2">{{ $doc['identifier'] }}</span>
                  @endif
                  @if(!empty($doc['identifier']) && !empty($doc['entity_type_id']) && isset($entityTypeNames[$doc['entity_type_id']]))
                    <span class="text-muted mx-2">&middot;</span>
                  @endif
                  @if(!empty($doc['entity_type_id']) && isset($entityTypeNames[$doc['entity_type_id']]))
                    <span class="text-muted">{{ $entityTypeNames[$doc['entity_type_id']] }}</span>
                  @endif
                  @if(request('sort') === 'lastUpdated' && !empty($doc['updated_at']))
                    <span class="text-muted ms-2">{{ \Carbon\Carbon::parse($doc['updated_at'])->format('Y-m-d') }}</span>
                  @endif
                </div>
              </div>
            </div>
          </article>
        @endforeach
      </div>
    @endif
  @endif
@endsection

@section('after-content')
  @include('ahg-core::components.pager', ['pager' => $pager])

  @auth
    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        <li><a class="btn atom-btn-outline-light" href="{{ route('actor.create') }}" title="Add new">Add new</a></li>
      </ul>
    </section>
  @endauth

  <script>
    (function() {
      // Advanced search: add/remove criteria
      var criteriaContainer = document.querySelector('.criteria');
      var template = document.getElementById('criterion-template');
      var criterionIdx = 1;

      document.querySelectorAll('.add-criterion').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          if (!template) return;
          var clone = template.content.cloneNode(true);
          var row = clone.querySelector('.criterion');
          var selects = row.querySelectorAll('select');
          var input = row.querySelector('input[type="text"]');
          if (selects[0]) { selects[0].name = 'so' + criterionIdx; selects[0].value = btn.dataset.bool || 'and'; }
          if (input) input.name = 'sq' + criterionIdx;
          if (selects[1]) selects[1].name = 'sf' + criterionIdx;
          criterionIdx++;
          var addBtn = document.querySelector('.add-new-criteria');
          if (addBtn) addBtn.parentNode.insertBefore(row, addBtn);
          bindDeleteButtons();
        });
      });

      function bindDeleteButtons() {
        document.querySelectorAll('.delete-criterion').forEach(function(btn) {
          btn.onclick = function(e) {
            e.preventDefault();
            var row = btn.closest('.criterion');
            if (row && document.querySelectorAll('.criterion').length > 1) {
              row.remove();
            } else if (row) {
              var input = row.querySelector('input[type="text"]');
              if (input) input.value = '';
            }
          };
        });
      }
      bindDeleteButtons();

      // Reset button
      var resetBtn = document.querySelector('.reset');
      if (resetBtn) {
        resetBtn.addEventListener('click', function() {
          var form = resetBtn.closest('form');
          if (form) {
            form.querySelectorAll('input[type="text"]').forEach(function(i) { i.value = ''; });
            form.querySelectorAll('select').forEach(function(s) { s.selectedIndex = 0; });
            var extra = form.querySelectorAll('.criterion');
            for (var i = extra.length - 1; i > 0; i--) extra[i].remove();
          }
        });
      }
    })();
  </script>
@endsection
