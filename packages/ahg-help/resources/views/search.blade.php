@extends('theme::layouts.1col')

@section('title', 'Search Help')
@section('body-class', 'help search')

@section('content')
<div class="row">
  <div class="col-lg-3 col-md-4 mb-4">
    @include('ahg-help::_sidebar', ['categories' => $categories])
  </div>

  <div class="col-lg-9 col-md-8">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('help.index') }}">Help Center</a></li>
        <li class="breadcrumb-item active">Search Results</li>
      </ol>
    </nav>

    <form action="{{ route('help.search') }}" method="get" class="mb-4">
      <div class="input-group input-group-lg">
        <input type="text" name="q" class="form-control" value="{{ $query }}" placeholder="Search help articles..." autocomplete="off">
        <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-search me-1"></i> Search</button>
      </div>
    </form>

    @if(empty($query))
      <p class="text-muted">Enter a search term to find help articles.</p>
    @elseif(empty($articleResults) && empty($sectionResults))
      <div class="alert alert-info">
        <i class="fas fa-info-circle me-1"></i>
        No results found for "{{ $query }}". Try different keywords.
      </div>
    @else
      @if(!empty($articleResults))
        <h2 class="h5 mb-3">
          Articles
          <span class="badge bg-primary ms-1">{{ count($articleResults) }}</span>
        </h2>
        <div class="list-group mb-4">
          @foreach($articleResults as $result)
            <a href="{{ route('help.article', $result['slug']) }}" class="list-group-item list-group-item-action">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h6 class="mb-1">{{ $result['title'] }}</h6>
                  <span class="badge bg-info me-1">{{ $result['category'] }}</span>
                  @if(!empty($result['subcategory']))
                    <span class="badge bg-light text-dark">{{ $result['subcategory'] }}</span>
                  @endif
                  @if(!empty($result['snippet']))
                    <p class="mb-0 mt-1 small text-muted">{{ Str::limit($result['snippet'], 200) }}</p>
                  @endif
                </div>
              </div>
            </a>
          @endforeach
        </div>
      @endif

      @if(!empty($sectionResults))
        <h2 class="h5 mb-3">
          Sections
          <span class="badge bg-secondary ms-1">{{ count($sectionResults) }}</span>
        </h2>
        <div class="list-group mb-4">
          @foreach($sectionResults as $result)
            <a href="{{ route('help.article', $result['slug']) }}#{{ $result['anchor'] }}" class="list-group-item list-group-item-action">
              <div>
                <h6 class="mb-1">
                  {{ $result['heading'] }}
                  <small class="text-muted ms-2">in {{ $result['article_title'] }}</small>
                </h6>
                <span class="badge bg-info">{{ $result['category'] }}</span>
                @if(!empty($result['snippet']))
                  <p class="mb-0 mt-1 small text-muted">{{ Str::limit($result['snippet'], 200) }}</p>
                @endif
              </div>
            </a>
          @endforeach
        </div>
      @endif
    @endif
  </div>
</div>
@endsection
