@if(isset($pager) && $pager->getNbResults())
  @extends('theme::layouts.2col')
@else
  @extends('theme::layouts.1col')
@endif

@section('title', 'Archival descriptions')
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
      <span class="small" id="heading-label">Archival description</span>
    </div>
  </div>
@endsection

@if(isset($pager) && $pager->getNbResults())
@section('sidebar')
  <h2 class="d-grid">
    <button class="btn btn-lg atom-btn-white collapsed text-wrap" type="button"
            data-bs-toggle="collapse" data-bs-target="#collapse-aggregations"
            aria-expanded="false" aria-controls="collapse-aggregations">
      Narrow your results by:
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
  {{-- Filter tags --}}
  @if(isset($filterTags) && count($filterTags) > 0)
    <div class="d-flex flex-wrap gap-2">
      @foreach($filterTags as $tag)
        <a href="{{ $tag['removeUrl'] ?? '#' }}"
           class="btn btn-sm atom-btn-white align-self-start mw-100 filter-tag d-flex">
          <span class="visually-hidden">Remove filter:</span>
          <span class="text-truncate d-inline-block">{{ $tag['label'] ?? '' }}</span>
          <i aria-hidden="true" class="fas fa-times ms-2 align-self-center"></i>
        </a>
      @endforeach
    </div>
  @endif
@endsection

@section('content')
  @if(isset($pager) && $pager->getNbResults())
    <div class="d-flex flex-wrap gap-2 mb-3">
      @auth
        @if(Route::has('informationobject.export.csv'))
          <a class="btn btn-sm atom-btn-white"
             href="{{ route('informationobject.export.csv', request()->query()) }}">
            <i class="fas fa-upload me-1" aria-hidden="true"></i>Export CSV
          </a>
        @endif
      @endauth

      <div class="d-flex flex-wrap gap-2 ms-auto">
        @include('ahg-core::components.sort-pickers', [
            'options' => $sortOptions ?? [
                'lastUpdated' => 'Date modified',
                'alphabetic' => 'Title',
                'relevance' => 'Relevance',
                'identifier' => 'Identifier',
                'referenceCode' => 'Reference code',
                'startDate' => 'Start date',
                'endDate' => 'End date',
            ],
            'default' => 'alphabetic',
        ])
      </div>
    </div>

    <div id="content">
      @foreach($pager->getResults() as $doc)
        <article class="search-result row g-0 p-3 border-bottom">
          <div class="col-12 d-flex flex-column gap-1">
            <div class="d-flex align-items-center gap-2">
              <a href="{{ route('informationobject.show', $doc['slug']) }}" class="h5 mb-0 text-truncate">
                {{ $doc['name'] ?: '[Untitled]' }}
              </a>
            </div>

            <div class="d-flex flex-column gap-2">
              <div class="d-flex flex-wrap">
                @php $showDash = false; @endphp
                @if(!empty($doc['identifier']))
                  <span class="text-primary">{{ $doc['identifier'] }}</span>
                  @php $showDash = true; @endphp
                @endif

                @if(!empty($doc['level_of_description_id']) && isset($levelNames[$doc['level_of_description_id']]))
                  @if($showDash)<span class="text-muted mx-2"> &middot; </span>@endif
                  <span class="text-muted">{{ $levelNames[$doc['level_of_description_id']] }}</span>
                  @php $showDash = true; @endphp
                @endif

                @if(!empty($doc['date_display']))
                  @if($showDash)<span class="text-muted mx-2"> &middot; </span>@endif
                  <span class="text-muted">{{ $doc['date_display'] }}</span>
                @endif
              </div>

              @if(!empty($doc['part_of_title']))
                <span class="text-muted">
                  Part of
                  @if(!empty($doc['part_of_slug']))
                    <a href="{{ route('informationobject.show', $doc['part_of_slug']) }}">{{ $doc['part_of_title'] }}</a>
                  @else
                    {{ $doc['part_of_title'] }}
                  @endif
                </span>
              @endif

              @if(!empty($doc['scope_and_content']))
                <span class="text-block d-none">
                  {!! nl2br(e(\Illuminate\Support\Str::limit($doc['scope_and_content'], 250))) !!}
                </span>
              @endif

              @if(!empty($doc['creator']))
                <span class="text-muted">{{ $doc['creator'] }}</span>
              @endif
            </div>
          </div>
        </article>
      @endforeach
    </div>
  @endif
@endsection

@section('after-content')
  @if(isset($pager))
    @include('ahg-core::components.pager', ['pager' => $pager])
  @endif
@endsection
