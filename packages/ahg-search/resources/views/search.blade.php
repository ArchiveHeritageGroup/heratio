@extends('theme::layouts.1col')

@section('title', $query ? "Search results for \"{$query}\"" : 'Search')
@section('body-class', 'search')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-search me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($query && $pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results for &ldquo;{{ $query }}&rdquo;
        @elseif($query)
          No results found for &ldquo;{{ $query }}&rdquo;
        @else
          Search
        @endif
      </h1>
      <span class="small text-muted">Search all records</span>
    </div>
  </div>

  {{-- Search box --}}
  <form action="{{ route('search') }}" method="get" class="mb-4">
    <div class="input-group">
      <input
        type="text"
        name="q"
        class="form-control form-control-lg"
        placeholder="Search descriptions, authority records, repositories, terms..."
        value="{{ $query }}"
        autocomplete="off"
        data-autocomplete-url="{{ route('search.autocomplete') }}"
      >
      <button class="btn btn-primary" type="submit">
        <i class="fas fa-search" aria-hidden="true"></i>
        Search
      </button>
    </div>
  </form>

  @if($query && $pager->getNbResults())
    <div class="list-group mb-4">
      @foreach($pager->getResults() as $result)
        @php
          $typeLabels = [
              'informationobject' => ['label' => 'Description', 'class' => 'bg-primary', 'route' => $result['slug']],
              'actor' => ['label' => 'Authority record', 'class' => 'bg-success', 'route' => 'actor/' . $result['slug']],
              'repository' => ['label' => 'Repository', 'class' => 'bg-info text-dark', 'route' => 'repository/' . $result['slug']],
              'term' => ['label' => 'Term', 'class' => 'bg-warning text-dark', 'route' => 'term/' . $result['slug']],
          ];
          $meta = $typeLabels[$result['type']] ?? ['label' => 'Record', 'class' => 'bg-secondary', 'route' => $result['slug']];
        @endphp

        <div class="list-group-item">
          <div class="d-flex align-items-start">
            <span class="badge {{ $meta['class'] }} me-2 mt-1">{{ $meta['label'] }}</span>
            <div class="flex-grow-1">
              <h5 class="mb-1">
                <a href="/{{ $meta['route'] }}">
                  {!! $result['highlighted_title'] !!}
                </a>
              </h5>

              @if($result['identifier'])
                <small class="text-muted me-3">
                  <i class="fas fa-barcode" aria-hidden="true"></i>
                  {{ $result['identifier'] }}
                </small>
              @endif

              @if($result['repository'])
                <small class="text-muted">
                  <i class="fas fa-institution" aria-hidden="true"></i>
                  {{ $result['repository'] }}
                </small>
              @endif

              @if($result['snippet'])
                <p class="mb-0 mt-1 text-muted small">{!! $result['snippet'] !!}</p>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>

    @include('ahg-core::components.pager', ['pager' => $pager])
  @elseif($query)
    <div class="alert alert-info">
      <i class="fas fa-info-circle" aria-hidden="true"></i>
      No results matched your search. Try different keywords or broaden your search terms.
    </div>
  @endif
@endsection
