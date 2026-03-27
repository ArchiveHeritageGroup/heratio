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
    @if(isset($facets))
      @foreach($facets as $facetName => $facetData)
        @if(!empty($facetData['terms']))
          <section class="facet mb-3">
            <h3 class="h6 px-2 py-1 border-bottom">{{ $facetData['label'] ?? ucfirst($facetName) }}</h3>
            <ul class="list-unstyled px-2">
              @foreach($facetData['terms'] as $term)
                <li class="mb-1">
                  @php
                    $isActive = request($facetName) == ($term['value'] ?? $term['id'] ?? '');
                    $params = request()->except([$facetName, 'page']);
                    if (!$isActive) {
                        $params[$facetName] = $term['value'] ?? $term['id'] ?? '';
                    }
                  @endphp
                  <a href="{{ route('informationobject.browse', $params) }}" class="{{ $isActive ? 'fw-bold' : '' }}">
                    {{ $term['label'] ?? $term['name'] ?? $term['value'] ?? '' }}
                    <span class="badge bg-secondary rounded-pill float-end">{{ $term['count'] ?? 0 }}</span>
                  </a>
                </li>
              @endforeach
            </ul>
          </section>
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
      @auth
        @if(Route::has('export.csv'))
          <a class="btn btn-sm atom-btn-white"
             href="{{ route('export.csv', request()->query()) }}">
            <i class="fas fa-upload me-1" aria-hidden="true"></i>{{ __('Export CSV') }}
          </a>
        @endif
      @endauth

      <div class="d-flex flex-wrap gap-2 ms-auto">
        @include('ahg-core::components.sort-pickers', [
            'options' => $sortOptions,
            'default' => 'alphabetic',
        ])
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

    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>{{ __('Title') }}</th>
            <th>{{ __('Level of description') }}</th>
            <th>{{ __('Repository') }}</th>
            <th>{{ __('Identifier') }}</th>
            @if(request('sort') === 'lastUpdated')
              <th>{{ __('Updated') }}</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $doc)
            <tr>
              <td>
                <a href="{{ route('informationobject.show', $doc['slug']) }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
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
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection

@section('after-content')
  @include('ahg-core::components.pager', ['pager' => $pager])
@endsection
