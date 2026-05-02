@extends('theme::layouts.1col')

@section('title', $query ? "Search results for \"{$query}\"" : 'Search')
@section('body-class', 'search')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-search me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($query && $pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
          @if($query) for &ldquo;{{ $query }}&rdquo;@endif
        @elseif($query || !empty($activeFilters))
          No results found
          @if($query) for &ldquo;{{ $query }}&rdquo;@endif
        @else
          Search
        @endif
      </h1>
      <span class="small text-muted">{{ __('Search archival descriptions') }}</span>
    </div>
  </div>

  {{-- Search box --}}
  <form action="{{ route('search') }}" method="get" class="mb-3">
    {{-- Preserve existing filter params --}}
    @if(request('repository'))
      <input type="hidden" name="repository" value="{{ request('repository') }}">
    @endif
    @if(request('level'))
      <input type="hidden" name="level" value="{{ request('level') }}">
    @endif
    @if(request('dateFrom'))
      <input type="hidden" name="dateFrom" value="{{ request('dateFrom') }}">
    @endif
    @if(request('dateTo'))
      <input type="hidden" name="dateTo" value="{{ request('dateTo') }}">
    @endif
    @if(request()->has('hasDigitalObject'))
      <input type="hidden" name="hasDigitalObject" value="{{ request('hasDigitalObject') }}">
    @endif
    @if(request('mediaType'))
      <input type="hidden" name="mediaType" value="{{ request('mediaType') }}">
    @endif
    @if(request('sort') && request('sort') !== 'relevance')
      <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif

    <div class="input-group">
      <input
        type="text"
        name="q"
        class="form-control form-control-lg"
        placeholder="{{ __('Search descriptions, authority records, repositories, terms...') }}"
        value="{{ $query }}"
        autocomplete="off"
        data-autocomplete-url="{{ route('search.autocomplete') }}"
      >
      <button class="btn atom-btn-outline-success" type="submit">
        <i class="fas fa-search" aria-hidden="true"></i>
        {{ __('Search') }}
      </button>
      <a href="{{ route('search.advanced') }}{{ $query ? '?q=' . urlencode($query) : '' }}" class="btn atom-btn-white" title="{{ __('Advanced search') }}">
        <i class="fas fa-sliders-h" aria-hidden="true"></i>
        {{ __('Advanced') }}
      </a>
    </div>
  </form>

  {{-- Active filter tags --}}
  @if(!empty($activeFilters))
    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
      <span class="text-muted small me-1">{{ __('Filters:') }}</span>
      @foreach($activeFilters as $filter)
        @php
          $removeParams = request()->except([$filter['param'], 'page']);
        @endphp
        <a href="{{ route('search', $removeParams) }}" class="badge bg-secondary text-decoration-none" title="{{ __('Remove filter') }}">
          {{ $filter['label'] }}
          <i class="fas fa-times ms-1" aria-hidden="true"></i>
        </a>
      @endforeach
      <a href="{{ route('search', ['q' => $query]) }}" class="btn btn-sm atom-btn-outline-danger">
        Clear all filters
      </a>
    </div>
  @endif

  <div class="row">
    {{-- Sidebar facets --}}
    @if(!empty($aggregations) && ($query || !empty($activeFilters)))
      <div class="col-lg-3 mb-4">

        {{-- Sort picker --}}
        <div class="card mb-3">
          <div class="card-header py-2" style="background:var(--ahg-primary);color:#fff"><strong>{{ __('Sort by') }}</strong></div>
          <div class="card-body py-2">
            @php
              $sortOptions = [
                  'relevance'     => 'Relevance',
                  'titleAsc'      => 'Title (A-Z)',
                  'titleDesc'     => 'Title (Z-A)',
                  'dateDesc'      => 'Date (newest)',
                  'dateAsc'       => 'Date (oldest)',
                  'identifierAsc' => 'Identifier (A-Z)',
                  'lastUpdated'   => 'Last updated',
              ];
            @endphp
            <select class="form-select form-select-sm" data-csp-go aria-label="{{ __('Sort results') }}">
              @foreach($sortOptions as $val => $label)
                @php $sortUrl = request()->fullUrlWithQuery(['sort' => $val, 'page' => null]); @endphp
                <option value="{{ $sortUrl }}" {{ $sort === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
        </div>

        {{-- Repository facet --}}
        @if(!empty($aggregations['repositories']))
          <div class="card mb-3">
            <div class="card-header py-2" style="background:var(--ahg-primary);color:#fff"><strong>{{ __('Repository') }}</strong></div>
            <ul class="list-group list-group-flush">
              @foreach($aggregations['repositories'] as $bucket)
                @php
                  $isActive = (int) request('repository') === (int) $bucket['id'];
                  $params = $isActive
                      ? request()->except(['repository', 'page'])
                      : array_merge(request()->except('page'), ['repository' => $bucket['id']]);
                @endphp
                <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ $isActive ? 'list-group-item-primary' : '' }}">
                  <a href="{{ route('search', $params) }}" class="text-decoration-none text-truncate {{ $isActive ? 'fw-bold' : '' }}" style="max-width:80%">
                    @if($isActive)<i class="fas fa-check-circle me-1" aria-hidden="true"></i>@endif
                    {{ $bucket['label'] }}
                  </a>
                  <span class="badge bg-secondary rounded-pill">{{ number_format($bucket['count']) }}</span>
                </li>
              @endforeach
            </ul>
          </div>
        @endif

        {{-- Level of description facet --}}
        @if(!empty($aggregations['levels']))
          <div class="card mb-3">
            <div class="card-header py-2" style="background:var(--ahg-primary);color:#fff"><strong>{{ __('Level of description') }}</strong></div>
            <ul class="list-group list-group-flush">
              @foreach($aggregations['levels'] as $bucket)
                @php
                  $isActive = (int) request('level') === (int) $bucket['id'];
                  $params = $isActive
                      ? request()->except(['level', 'page'])
                      : array_merge(request()->except('page'), ['level' => $bucket['id']]);
                @endphp
                <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ $isActive ? 'list-group-item-primary' : '' }}">
                  <a href="{{ route('search', $params) }}" class="text-decoration-none {{ $isActive ? 'fw-bold' : '' }}">
                    @if($isActive)<i class="fas fa-check-circle me-1" aria-hidden="true"></i>@endif
                    {{ $bucket['label'] }}
                  </a>
                  <span class="badge bg-secondary rounded-pill">{{ number_format($bucket['count']) }}</span>
                </li>
              @endforeach
            </ul>
          </div>
        @endif

        {{-- Media type facet --}}
        @if(!empty($aggregations['mediaTypes']))
          <div class="card mb-3">
            <div class="card-header py-2" style="background:var(--ahg-primary);color:#fff"><strong>{{ __('Media type') }}</strong></div>
            <ul class="list-group list-group-flush">
              @foreach($aggregations['mediaTypes'] as $bucket)
                @php
                  $isActive = (int) request('mediaType') === (int) $bucket['id'];
                  $params = $isActive
                      ? request()->except(['mediaType', 'page'])
                      : array_merge(request()->except('page'), ['mediaType' => $bucket['id']]);
                @endphp
                <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ $isActive ? 'list-group-item-primary' : '' }}">
                  <a href="{{ route('search', $params) }}" class="text-decoration-none {{ $isActive ? 'fw-bold' : '' }}">
                    @if($isActive)<i class="fas fa-check-circle me-1" aria-hidden="true"></i>@endif
                    {{ $bucket['label'] }}
                  </a>
                  <span class="badge bg-secondary rounded-pill">{{ number_format($bucket['count']) }}</span>
                </li>
              @endforeach
            </ul>
          </div>
        @endif

        {{-- Digital object presence facet --}}
        @if(!empty($aggregations['hasDigitalObject']))
          <div class="card mb-3">
            <div class="card-header py-2" style="background:var(--ahg-primary);color:#fff"><strong>{{ __('Digital object') }}</strong></div>
            <ul class="list-group list-group-flush">
              @foreach($aggregations['hasDigitalObject'] as $bucket)
                @php
                  $doVal = $bucket['id'] ? '1' : '0';
                  $isActive = request()->has('hasDigitalObject') && request('hasDigitalObject') === $doVal;
                  $params = $isActive
                      ? request()->except(['hasDigitalObject', 'page'])
                      : array_merge(request()->except('page'), ['hasDigitalObject' => $doVal]);
                @endphp
                <li class="list-group-item d-flex justify-content-between align-items-center py-1 {{ $isActive ? 'list-group-item-primary' : '' }}">
                  <a href="{{ route('search', $params) }}" class="text-decoration-none {{ $isActive ? 'fw-bold' : '' }}">
                    @if($isActive)<i class="fas fa-check-circle me-1" aria-hidden="true"></i>@endif
                    {{ $bucket['label'] }}
                  </a>
                  <span class="badge bg-secondary rounded-pill">{{ number_format($bucket['count']) }}</span>
                </li>
              @endforeach
            </ul>
          </div>
        @endif

      </div>
    @endif

    {{-- Results --}}
    <div class="{{ (!empty($aggregations) && ($query || !empty($activeFilters))) ? 'col-lg-9' : 'col-12' }}">
      @if(($query || !empty($activeFilters)) && $pager->getNbResults())
        <div class="list-group mb-4">
          @foreach($pager->getResults() as $result)
            <div class="list-group-item">
              <div class="d-flex align-items-start">
                {{-- Digital object icon --}}
                @if(!empty($result['hasDigitalObject']))
                  <span class="text-primary me-2 mt-1" title="{{ __('Has digital object') }}">
                    <i class="fas fa-file-image" aria-hidden="true"></i>
                  </span>
                @endif

                <div class="flex-grow-1">
                  <h5 class="mb-1">
                    <a href="/{{ $result['slug'] ?? '' }}">
                      {!! $result['highlighted_title'] ?? e($result['title'] ?? '[Untitled]') !!}
                    </a>
                  </h5>

                  <div class="d-flex flex-wrap gap-2 mb-1">
                    @if(!empty($result['identifier']))
                      <small class="text-muted">
                        <i class="fas fa-barcode" aria-hidden="true"></i>
                        {{ $result['identifier'] }}
                      </small>
                    @endif

                    @if(!empty($result['levelName']))
                      <small class="text-muted">
                        <i class="fas fa-layer-group" aria-hidden="true"></i>
                        {{ $result['levelName'] }}
                      </small>
                    @endif

                    @if(!empty($result['repositoryName']))
                      <small class="text-muted">
                        <i class="fas fa-institution" aria-hidden="true"></i>
                        {{ $result['repositoryName'] }}
                      </small>
                    @endif

                    @if(!empty($result['dates']))
                      <small class="text-muted">
                        <i class="fas fa-calendar" aria-hidden="true"></i>
                        {{ $result['dates'] }}
                      </small>
                    @endif
                  </div>

                  @if(!empty($result['snippet']))
                    <p class="mb-0 mt-1 text-muted small">{!! $result['snippet'] !!}</p>
                  @endif
                </div>
              </div>
            </div>
          @endforeach
        </div>

        @include('ahg-core::components.pager', ['pager' => $pager])

      @elseif($query || !empty($activeFilters))
        <div class="alert alert-info">
          <i class="fas fa-info-circle" aria-hidden="true"></i>
          No results matched your search. Try different keywords or broaden your search terms.
          @if(!empty($activeFilters))
            <a href="{{ route('search', ['q' => $query]) }}">Clear all filters</a> to see more results.
          @endif
        </div>
      @endif
    </div>
  </div>
@endsection
