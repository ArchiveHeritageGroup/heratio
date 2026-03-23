@extends('theme::layouts.1col')

@section('title', $query ? __('Showing %1% results', ['%1%' => $pager->getNbResults()]) : __('Search'))

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-search me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0" aria-describedby="heading-label">
        @if($query && $pager->getNbResults())
          {{ __('Showing %1% results', ['%1%' => $pager->getNbResults()]) }}
        @else
          {{ __('No results found') }}
        @endif
      </h1>
      <span class="small" id="heading-label">
        {{ __('Search') }}
      </span>
    </div>
  </div>

  {{-- Inline search --}}
  <form id="inline-search" method="get" action="{{ route('search') }}" role="search" aria-label="{{ __('Search') }}" class="mb-3">
    <div class="input-group flex-nowrap">
      <input
        class="form-control form-control-sm"
        type="search"
        name="q"
        value="{{ $query }}"
        placeholder="{{ __('Search') }}"
        aria-label="{{ __('Search') }}">

      @if(!empty($query))
        <a
          href="{{ route('search') }}"
          class="btn btn-sm atom-btn-white d-flex align-items-center"
          role="button">
          <i class="fas fa-undo" aria-hidden="true"></i>
          <span class="visually-hidden">{{ __('Reset search') }}</span>
        </a>
      @endif

      <button class="btn btn-sm atom-btn-white" type="submit">
        <i class="fas fa-search" aria-hidden="true"></i>
        <span class="visually-hidden">{{ __('Search') }}</span>
      </button>
    </div>
  </form>

  {{-- Results --}}
  @if($pager->getNbResults())
    @foreach($pager->getResults() as $result)
      <article class="search-result row g-0 p-3 border-bottom">
        <div class="col-12 d-flex flex-column gap-1">
          <div class="d-flex align-items-center gap-2">
            <a href="/{{ $result['slug'] ?? '' }}" class="h5 mb-0 text-truncate">
              {{ $result['title'] ?? __('[Untitled]') }}
            </a>
          </div>

          <div class="d-flex flex-wrap">
            @php $showDash = false; @endphp
            @if(!empty($result['identifier']))
              <span class="text-primary">{{ $result['identifier'] }}</span>
              @php $showDash = true; @endphp
            @endif

            @if(!empty($result['levelName']))
              @if($showDash)
                <span class="text-muted mx-2"> &middot; </span>
              @endif
              <span class="text-muted">{{ $result['levelName'] }}</span>
              @php $showDash = true; @endphp
            @endif

            @if(!empty($result['dates']))
              @if($showDash)
                <span class="text-muted mx-2"> &middot; </span>
              @endif
              <span class="text-muted">{{ $result['dates'] }}</span>
              @php $showDash = true; @endphp
            @endif
          </div>

          @if(!empty($result['repositoryName']))
            <span class="text-muted">
              {{ __('Part of') }} {{ $result['repositoryName'] }}
            </span>
          @endif

          @if(!empty($result['snippet']))
            <span class="text-block d-none">
              {!! $result['snippet'] !!}
            </span>
          @endif
        </div>
      </article>
    @endforeach

    @include('ahg-core::components.pager', ['pager' => $pager])

  @elseif($query)
    <div class="p-3">
      {{ __("We couldn't find any results matching your search.") }}
    </div>
  @endif
@endsection
