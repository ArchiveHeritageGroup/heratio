@extends('theme::layouts.1col')

@section('title', $article['title'] . ' — Help Center')
@section('body-class', 'help article')

@section('content')
<div class="row">
  {{-- TOC Sidebar --}}
  <div class="col-lg-3 col-md-4 mb-4">
    <div class="sticky-top" style="top: 1rem;">
      <div class="mb-3">
        <form action="{{ route('help.search') }}" method="get" class="input-group input-group-sm">
          <input type="text" name="q" class="form-control" placeholder="Search help..." autocomplete="off">
          <button type="submit" class="btn atom-btn-white"><i class="fas fa-search"></i></button>
        </form>
      </div>

      <a href="{{ route('help.category', urlencode($article['category'])) }}" class="d-block mb-3 small">
        <i class="fas fa-arrow-left me-1"></i>Back to {{ $article['category'] }}
      </a>

      @if(!empty($toc))
        <h6 class="text-uppercase text-muted mb-2">Contents</h6>
        <nav>
          <ul class="nav flex-column">
            @foreach($toc as $entry)
              <li class="nav-item">
                <a class="nav-link py-1 {{ ($entry['level'] ?? 2) === 3 ? 'ms-3 small' : '' }}"
                  href="#{{ $entry['anchor'] }}">
                  {{ $entry['text'] }}
                </a>
              </li>
            @endforeach
          </ul>
        </nav>
      @endif

      <div class="mt-3 pt-3 border-top small text-muted">
        <div class="mb-1">
          <i class="fas fa-tag me-1"></i>
          <a href="{{ route('help.category', urlencode($article['category'])) }}">{{ $article['category'] }}</a>
          @if(!empty($article['subcategory']))
            / {{ $article['subcategory'] }}
          @endif
        </div>
        <div class="mb-1"><i class="fas fa-align-left me-1"></i>{{ number_format($article['word_count']) }} words</div>
        <div><i class="fas fa-clock me-1"></i>{{ \Carbon\Carbon::parse($article['updated_at'])->format('M j, Y') }}</div>
      </div>
    </div>
  </div>

  {{-- Main Content --}}
  <div class="col-lg-9 col-md-8">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('help.index') }}">Help Center</a></li>
        <li class="breadcrumb-item"><a href="{{ route('help.category', urlencode($article['category'])) }}">{{ $article['category'] }}</a></li>
        <li class="breadcrumb-item active">{{ $article['title'] }}</li>
      </ol>
    </nav>

    <article class="help-article-content">
      {!! $article['body_html'] !!}
    </article>

    <nav class="d-flex justify-content-between mt-5 pt-4 border-top">
      @if($prevArticle)
        <a href="{{ route('help.article', $prevArticle['slug']) }}" class="btn atom-btn-white">
          <i class="fas fa-chevron-left me-1"></i>{{ $prevArticle['title'] }}
        </a>
      @else
        <span></span>
      @endif

      @if($nextArticle)
        <a href="{{ route('help.article', $nextArticle['slug']) }}" class="btn atom-btn-white">
          {{ $nextArticle['title'] }}<i class="fas fa-chevron-right ms-1"></i>
        </a>
      @else
        <span></span>
      @endif
    </nav>
  </div>
</div>
@endsection
