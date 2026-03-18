@extends('theme::layouts.1col')

@section('title', $category . ' — Help Center')
@section('body-class', 'help category')

@section('content')
<div class="row">
  <div class="col-lg-3 col-md-4 mb-4">
    @include('ahg-help::_sidebar', ['categories' => $categories])
  </div>

  <div class="col-lg-9 col-md-8">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('help.index') }}">Help Center</a></li>
        <li class="breadcrumb-item active">{{ $category }}</li>
      </ol>
    </nav>

    <h1 class="mb-4">{{ $category }}</h1>

    @foreach($grouped as $subcategory => $articles)
      <h2 class="h5 text-muted mt-4 mb-3">
        <i class="fas fa-tag me-1"></i>{{ $subcategory }}
        <span class="badge bg-secondary ms-1">{{ count($articles) }}</span>
      </h2>

      <div class="list-group mb-3">
        @foreach($articles as $article)
          <a href="{{ route('help.article', $article['slug']) }}" class="list-group-item list-group-item-action">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <h6 class="mb-1">{{ $article['title'] }}</h6>
                @if(!empty($article['related_plugin']))
                  <small class="text-muted"><i class="fas fa-puzzle-piece me-1"></i>{{ $article['related_plugin'] }}</small>
                @endif
              </div>
              <div class="text-end text-nowrap ms-3">
                <small class="text-muted d-block">{{ number_format($article['word_count']) }} words</small>
                <small class="text-muted">{{ \Carbon\Carbon::parse($article['updated_at'])->format('M j, Y') }}</small>
              </div>
            </div>
          </a>
        @endforeach
      </div>
    @endforeach
  </div>
</div>
@endsection
