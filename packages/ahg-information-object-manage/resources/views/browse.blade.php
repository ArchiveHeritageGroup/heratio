@if(isset($pager) && $pager->getNbResults())
  @extends('theme::layouts.2col')
@else
  @extends('theme::layouts.1col')
@endif

@section('title', config('app.ui_label_informationobject', 'Archival description') . 's')
@section('body-class', 'browse informationobject')

@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0" aria-describedby="heading-label">
        @if(isset($pager) && $pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small" id="heading-label">{{ config('app.ui_label_informationobject', 'Archival description') }}</span>
    </div>
  </div>
@endsection

@if(isset($pager) && $pager->getNbResults())
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
               href="{{ route('informationobject.browse', $langParams) }}" title="All">All</a>
            @foreach($languageFacets as $langCode => $facet)
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentLang == $langCode ? 'active text-decoration-underline' : '' }}"
                 href="{{ route('informationobject.browse', array_merge($langParams, ['languages' => $langCode])) }}"
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

    {{-- Collection ("Part of") facet --}}
    @if(isset($collectionFacets) && $collectionFacets->count())
      <div class="accordion mb-3">
        <div class="accordion-item aggregation">
          <h2 class="accordion-header" id="heading-collection">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#collapse-collection"
                    aria-expanded="false" aria-controls="collapse-collection">
              Part of
            </button>
          </h2>
          <div id="collapse-collection" class="accordion-collapse collapse list-group list-group-flush"
               aria-labelledby="heading-collection">
            @php
              $currentCollection = request('collection', '');
              $collParams = request()->except(['collection', 'page']);
            @endphp
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentCollection === '' ? 'active text-decoration-underline' : '' }}"
               href="{{ route('informationobject.browse', $collParams) }}" title="All">All</a>
            @foreach($collectionFacets as $coll)
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $currentCollection == $coll->id ? 'active text-decoration-underline' : '' }}"
                 href="{{ route('informationobject.browse', array_merge($collParams, ['collection' => $coll->id])) }}"
                 title="{{ $coll->label }}, {{ $coll->count }} results">
                {{ $coll->label }}
                <span class="visually-hidden">, {{ $coll->count }} results</span>
                <span aria-hidden="true" class="ms-3 text-nowrap">{{ $coll->count }}</span>
              </a>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    @if(isset($facets))
      @foreach($facets as $facetName => $facetData)
        @if(!empty($facetData['terms']))
          <div class="accordion mb-3">
            <div class="accordion-item aggregation">
              <h2 class="accordion-header" id="heading-{{ $facetName }}">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapse-{{ $facetName }}"
                        aria-expanded="false" aria-controls="collapse-{{ $facetName }}">
                  {{ $facetData['label'] ?? ucfirst($facetName) }}
                </button>
              </h2>
              <div id="collapse-{{ $facetName }}" class="accordion-collapse collapse list-group list-group-flush"
                   aria-labelledby="heading-{{ $facetName }}">
                @php
                  $currentVal = request($facetName, '');
                  $facetParams = request()->except([$facetName, 'page']);
                @endphp
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $currentVal === '' ? 'active text-decoration-underline' : '' }}"
                   href="{{ route('informationobject.browse', $facetParams) }}" title="All">All</a>
                @foreach($facetData['terms'] as $term)
                  @php
                    $termValue = $term['value'] ?? $term['id'] ?? '';
                    $isActive = $currentVal == $termValue;
                  @endphp
                  <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break {{ $isActive ? 'active text-decoration-underline' : '' }}"
                     href="{{ route('informationobject.browse', $isActive ? $facetParams : array_merge($facetParams, [$facetName => $termValue])) }}"
                     title="{{ $term['label'] ?? $term['name'] ?? $term['value'] ?? '' }}, {{ $term['count'] ?? 0 }} results">
                    {{ $term['label'] ?? $term['name'] ?? $term['value'] ?? '' }}
                    <span class="visually-hidden">, {{ $term['count'] ?? 0 }} results</span>
                    <span aria-hidden="true" class="ms-3 text-nowrap">{{ $term['count'] ?? 0 }}</span>
                  </a>
                @endforeach
              </div>
            </div>
          </div>
        @endif
      @endforeach
    @endif
  </div>
@endsection
@endif

@section('before-content')
  {{-- Top-level descriptions + active filter tags --}}
  <div class="d-flex flex-wrap gap-2">
    @if(!empty($isTopLevel))
      @php
        $topLodParams = request()->except(['page']);
        $topLodParams['topLevelDescription'] = '0';
      @endphp
      <a href="{{ route('informationobject.browse', $topLodParams) }}"
         class="btn btn-sm atom-btn-white align-self-start mw-100 filter-tag d-flex">
        <span class="visually-hidden">{{ __('Remove filter:') }}</span>
        <span class="text-truncate d-inline-block">{{ __('Only top-level descriptions') }}</span>
        <i aria-hidden="true" class="fas fa-times ms-2 align-self-center"></i>
      </a>
    @endif

    @if(isset($filterTags) && count($filterTags) > 0)
      @foreach($filterTags as $tag)
        <a href="{{ $tag['removeUrl'] ?? '#' }}" class="btn btn-sm atom-btn-white filter-tag d-flex">
          <span class="visually-hidden">{{ __('Remove filter:') }}</span>
          <span class="text-truncate d-inline-block">{{ $tag['label'] ?? '' }}</span>
          <i aria-hidden="true" class="fas fa-times ms-2 align-self-center"></i>
        </a>
      @endforeach
    @endif
  </div>
@endsection

@section('content')
  {{-- Advanced search options accordion --}}
  @include('ahg-io-manage::_advanced-search')

  @if(isset($pager) && $pager->getNbResults())

    <div class="d-flex flex-wrap gap-2 mb-3">
      {{-- View picker (Card/Table toggle) --}}
      @php
        $displayMode = request('displayMode', 'table');
        $baseQuery = request()->except(['displayMode', 'page']);
      @endphp
      <div class="btn-group" role="group" aria-label="Display mode">
        <a href="{{ route('informationobject.browse', array_merge($baseQuery, ['displayMode' => 'card'])) }}"
           class="btn btn-sm {{ $displayMode === 'card' ? 'atom-btn-secondary' : 'atom-btn-white' }}" title="Card view with thumbnails">
          <i class="fas fa-th" aria-hidden="true"></i>
          <span class="visually-hidden">Card</span>
        </a>
        <a href="{{ route('informationobject.browse', array_merge($baseQuery, ['displayMode' => 'table'])) }}"
           class="btn btn-sm {{ $displayMode === 'table' ? 'atom-btn-secondary' : 'atom-btn-white' }}" title="Table view">
          <i class="fas fa-list" aria-hidden="true"></i>
          <span class="visually-hidden">Table</span>
        </a>
      </div>

      @auth
        @if(Route::has('export.csv'))
          <a class="btn btn-sm atom-btn-white"
             href="{{ route('export.csv', request()->query()) }}">
            <i class="fas fa-upload me-1" aria-hidden="true"></i>{{ __('Export CSV') }}
          </a>
        @endif
      @endauth

      <a class="btn btn-sm atom-btn-white" href="javascript:window.print()">
        <i class="fas fa-print me-1" aria-hidden="true"></i>{{ __('Print') }}
      </a>

      <div class="d-flex flex-wrap gap-2 ms-auto">
        @include('ahg-core::components.sort-pickers', [
            'options' => $sortOptions,
            'default' => 'alphabetic',
        ])

        @php
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

    {{-- Digital objects banner --}}
    @if(!request('onlyMedia') && isset($digitalObjectsCount) && $digitalObjectsCount > 0)
      <div class="d-grid d-sm-flex gap-2 align-items-center p-3 border-bottom mb-3">
        {{ __(':count results with digital objects', ['count' => number_format($digitalObjectsCount)]) }}
        @php
          $doParams = request()->except(['page']);
          $doParams['onlyMedia'] = '1';
        @endphp
        <a class="btn btn-sm atom-btn-white ms-auto text-wrap"
           href="{{ route('informationobject.browse', $doParams) }}">
          <i class="fas fa-search me-1" aria-hidden="true"></i>
          {{ __('Show results with digital objects') }}
        </a>
      </div>
    @endif

    @if($displayMode === 'card')
      {{-- Card view with thumbnails --}}
      <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3 mb-3">
        @foreach($pager->getResults() as $doc)
          <div class="col">
            <div class="card h-100">
              @if(!empty($doc['thumbnail_path']))
                <a href="{{ route('informationobject.show', $doc['slug']) }}">
                  <img src="{{ url('/uploads/r/' . $doc['thumbnail_path']) }}" alt="{{ $doc['name'] ?: '[Untitled]' }}" class="card-img-top">
                </a>
              @else
                <a class="p-3 text-center" href="{{ route('informationobject.show', $doc['slug']) }}">
                  <i class="fas fa-file-alt fa-3x text-muted" aria-hidden="true"></i>
                </a>
              @endif
              <div class="card-body">
                <div class="card-text d-flex align-items-start gap-2">
                  <a href="{{ route('informationobject.show', $doc['slug']) }}" class="text-truncate" title="{{ $doc['name'] ?: '[Untitled]' }}">
                    {{ $doc['name'] ?: '[Untitled]' }}
                  </a>
                  @if(!empty($pubStatuses[$doc['id']]) && $pubStatuses[$doc['id']] == 159)
                    <span class="badge bg-warning">{{ __('Draft') }}</span>
                  @endif
                  <button class="btn atom-btn-white ms-auto active-primary clipboard"
                          data-clipboard-slug="{{ $doc['slug'] }}" data-clipboard-type="informationobject"
                          data-tooltip="true" data-title="Add to clipboard" data-alt-title="Remove from clipboard">
                    <i class="fas fa-lg fa-paperclip" aria-hidden="true"></i>
                    <span class="visually-hidden">Add to clipboard</span>
                  </button>
                </div>
                @if(!empty($doc['scope_and_content']))
                  <p class="card-text small text-muted mb-0">{{ $doc['scope_and_content'] }}</p>
                @endif
                @if(!empty($creators[$doc['id']]))
                  <p class="card-text small text-muted mb-0">{{ __('Creator') }}: {{ implode('; ', $creators[$doc['id']]) }}</p>
                @endif
                @if(!empty($dates[$doc['id']]))
                  <p class="card-text small text-muted mb-0">{{ __('Date(s)') }}: {{ implode('; ', $dates[$doc['id']]) }}</p>
                @endif
                @if(!empty($doc['level_of_description_id']) && isset($levelNames[$doc['level_of_description_id']]))
                  <p class="card-text small text-muted mb-0">
                    {{ $levelNames[$doc['level_of_description_id']] }}
                  </p>
                @endif
                @if(!empty($doc['parent_id']) && $doc['parent_id'] != 1 && isset($parentInfo[$doc['parent_id']]))
                  <p class="card-text small text-muted mb-0">
                    {{ __('Part of') }}
                    <a href="{{ route('informationobject.show', $parentInfo[$doc['parent_id']]['slug']) }}">{{ $parentInfo[$doc['parent_id']]['name'] ?: '[Untitled]' }}</a>
                  </p>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @else
      {{-- Table view --}}
      <div class="table-responsive mb-3">
        <table class="table table-bordered mb-0">
          <thead>
            <tr>
              <th>{{ __('Title') }}</th>
              <th>{{ __('Level of description') }}</th>
              <th>{{ __('Repository') }}</th>
              <th>{{ __('Reference code') }}</th>
              @if(request('sort') === 'lastUpdated')
                <th>{{ __('Updated') }}</th>
              @endif
              <th><span class="visually-hidden">{{ __('Clipboard') }}</span></th>
            </tr>
          </thead>
          <tbody>
            @foreach($pager->getResults() as $doc)
              <tr>
                <td>
                  <a href="{{ route('informationobject.show', $doc['slug']) }}">
                    {{ $doc['name'] ?: '[Untitled]' }}
                  </a>
                  @if(!empty($pubStatuses[$doc['id']]) && $pubStatuses[$doc['id']] == 159)
                    <span class="badge bg-warning">{{ __('Draft') }}</span>
                  @endif
                  @if(!empty($doc['scope_and_content']))
                    <p class="small text-muted mb-0">{{ $doc['scope_and_content'] }}</p>
                  @endif
                  @if(!empty($creators[$doc['id']]))
                    <p class="small text-muted mb-0">{{ __('Creator') }}: {{ implode('; ', $creators[$doc['id']]) }}</p>
                  @endif
                  @if(!empty($dates[$doc['id']]))
                    <p class="small text-muted mb-0">{{ __('Date(s)') }}: {{ implode('; ', $dates[$doc['id']]) }}</p>
                  @endif
                  @if(!empty($doc['parent_id']) && $doc['parent_id'] != 1 && isset($parentInfo[$doc['parent_id']]))
                    <p class="small text-muted mb-0">
                      {{ __('Part of') }}
                      <a href="{{ route('informationobject.show', $parentInfo[$doc['parent_id']]['slug']) }}">{{ $parentInfo[$doc['parent_id']]['name'] ?: '[Untitled]' }}</a>
                    </p>
                  @endif
                </td>
                <td>
                  @if(!empty($doc['level_of_description_id']) && isset($levelNames[$doc['level_of_description_id']]))
                    {{ $levelNames[$doc['level_of_description_id']] }}
                  @endif
                </td>
                <td>
                  @if(!empty($doc['repository_id']) && isset($repositoryNames[$doc['repository_id']]))
                    {{ $repositoryNames[$doc['repository_id']] }}
                  @endif
                </td>
                <td>{{ $doc['identifier'] ?? '' }}</td>
                @if(request('sort') === 'lastUpdated')
                  <td>{{ $doc['updated_at'] ? \Carbon\Carbon::parse($doc['updated_at'])->format('Y-m-d') : '' }}</td>
                @endif
                <td>
                  <button class="btn atom-btn-white ms-auto active-primary clipboard"
                          data-clipboard-slug="{{ $doc['slug'] }}" data-clipboard-type="informationobject"
                          data-tooltip="true" data-title="Add to clipboard" data-alt-title="Remove from clipboard">
                    <i class="fas fa-lg fa-paperclip" aria-hidden="true"></i>
                    <span class="visually-hidden">Add to clipboard</span>
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
