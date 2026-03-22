@extends('theme::layouts.1col')

@section('title', 'Help Center')
@section('body-class', 'help index')

@section('content')
<div class="row">
  <div class="col-lg-3 col-md-4 mb-4">
    @include('ahg-help::_sidebar', ['categories' => $categories])
  </div>

  <div class="col-lg-9 col-md-8">
    {{-- Hero Search --}}
    <div class="card bg-light mb-4">
      <div class="card-body text-center py-5">
        <h1 class="mb-3"><i class="fas fa-question-circle me-2"></i>Help Center</h1>
        <p class="lead mb-4">Search the documentation or browse by category</p>
        <div class="row justify-content-center">
          <div class="col-lg-8 col-md-10">
            <form action="{{ route('help.search') }}" method="get" class="input-group input-group-lg">
              <input type="text" name="q" class="form-control" placeholder="Search help articles..." autocomplete="off">
              <button type="submit" class="btn atom-btn-white"><i class="fas fa-search"></i></button>
            </form>
          </div>
        </div>
      </div>
    </div>

    {{-- Documentation Portal --}}
    <div class="card border-primary mb-4">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-md-8">
            <h5 class="card-title mb-1"><i class="fas fa-book me-2"></i>Documentation Portal</h5>
            <p class="card-text text-muted mb-0">Full user guides, technical reference, plugin documentation, and API reference.</p>
          </div>
          <div class="col-md-4 text-md-end mt-2 mt-md-0">
            <a href="https://docs.theahg.co.za" target="_blank" rel="noopener" class="btn atom-btn-white">
              <i class="fas fa-external-link-alt me-1"></i>Open Documentation
            </a>
          </div>
        </div>
      </div>
    </div>

    {{-- Category Cards --}}
    <h2 class="h4 mb-3">Browse by Category</h2>
    <div class="row g-3 mb-4">
      @foreach($categories as $cat)
        @php $catName = $cat['category']; @endphp
        <div class="col-lg-4 col-md-6">
          <a href="{{ route('help.category', urlencode($catName)) }}" class="text-decoration-none">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                  <i class="{{ $categoryIcons[$catName] ?? 'fas fa-folder' }} fs-3 me-2 text-primary"></i>
                  <h5 class="card-title mb-0">{{ $catName }}</h5>
                </div>
                <p class="card-text text-muted small">{{ $categoryDescriptions[$catName] ?? '' }}</p>
                <span class="badge bg-secondary">{{ $cat['article_count'] }} articles</span>
              </div>
            </div>
          </a>
        </div>
      @endforeach
    </div>

    {{-- Recently Updated --}}
    @if(!empty($recentArticles))
      <h2 class="h4 mb-3">Recently Updated</h2>
      <div class="list-group mb-4">
        @foreach($recentArticles as $article)
          <a href="{{ route('help.article', $article['slug']) }}"
            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <div>
              <strong>{{ $article['title'] }}</strong>
              <span class="badge bg-info ms-2">{{ $article['category'] }}</span>
              @if(!empty($article['subcategory']))
                <span class="badge bg-light text-dark ms-1">{{ $article['subcategory'] }}</span>
              @endif
            </div>
            <small class="text-muted">{{ \Carbon\Carbon::parse($article['updated_at'])->format('M j, Y') }}</small>
          </a>
        @endforeach
      </div>
    @endif
  </div>
</div>
@endsection
