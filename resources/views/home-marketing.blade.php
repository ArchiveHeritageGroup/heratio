@extends('theme::layouts.1col')

@section('title-block')
  @if($page)
    <h1>{{ $page->title }}</h1>
  @endif
@endsection

@push('css')
  <meta name="description" content="Heratio is the world's first fully integrated open-source platform for galleries, libraries, archives, and museums. Built by archives professionals, AGPL-licensed, available in 18 languages.">
@endpush

@section('content')
  @php
    $homeParts = $page ? preg_split('/<!--\s*HERATIO_MIDPAGE\s*-->/', (string) ($page->content ?? ''), 2) : [''];
    $heroPart = $homeParts[0] ?? '';
  @endphp

  @if($page)
    <div class="page p-3">
      @auth
        @if(auth()->user()->is_admin)
          <div class="mb-2 text-end">
            <a href="{{ route('staticpage.edit', 'home') }}" class="btn btn-sm atom-btn-white">
              <i class="fas fa-pencil-alt me-1" aria-hidden="true"></i>{{ __('Edit') }}
            </a>
          </div>
        @endif
      @endauth
      {!! $heroPart !!}
    </div>
  @endif

  {{-- Prominent Articles block (always visible on the demo landing) --}}
  <section class="container py-5" id="articles">
    <a href="{{ route('articles.index') }}" class="d-block text-decoration-none" aria-label="{{ __('Read our articles') }}">
      <div class="row align-items-center g-3 p-4 p-md-5 rounded-4 shadow"
           style="background:var(--ahg-primary, #1f4e5f);color:#fff;transition:transform .15s ease;"
           onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
        <div class="col-md-auto text-center">
          <i class="fas fa-newspaper" style="font-size:3.5rem;opacity:.9;"></i>
        </div>
        <div class="col">
          <h2 class="display-6 fw-bold mb-1 text-white">{{ __('Articles') }}</h2>
          <p class="mb-0 fs-5 text-white-50">{{ __('News, regulation and insight from The Archive and Heritage Group.') }}</p>
        </div>
        <div class="col-md-auto text-md-end">
          <span class="btn btn-light btn-lg fw-semibold px-4">{{ __('Read our articles') }} <i class="fas fa-arrow-right ms-2"></i></span>
        </div>
      </div>
    </a>

    @if(!empty($latestArticles))
      <div class="d-flex justify-content-between align-items-end mb-3 mt-5 flex-wrap gap-2">
        <h3 class="mb-0">{{ __('Latest Articles') }}</h3>
        <a href="{{ route('articles.index') }}" class="btn btn-outline-primary btn-sm">{{ __('View all') }} <i class="fas fa-arrow-right ms-1"></i></a>
      </div>
      <div class="row g-4">
        @foreach($latestArticles as $post)
          <div class="col-md-4">
            <div class="card h-100 shadow-sm">
              @if($post->cover_image)
                <a href="{{ route('articles.show', $post->slug) }}"><img src="{{ $post->cover_image }}" class="card-img-top" alt="{{ $post->title }}" style="height:170px;object-fit:cover;"></a>
              @endif
              <div class="card-body d-flex flex-column">
                @if($post->article_group)<span class="badge bg-primary align-self-start mb-2">{{ $post->article_group }}</span>@endif
                <h3 class="h5 card-title"><a href="{{ route('articles.show', $post->slug) }}" class="text-decoration-none">{{ $post->title }}</a></h3>
                @if($post->excerpt)<p class="card-text flex-grow-1">{{ \Illuminate\Support\Str::limit($post->excerpt, 120) }}</p>@endif
                <div class="text-muted small mt-auto">@if($post->published_at){{ \Carbon\Carbon::parse($post->published_at)->format('d M Y') }}@endif</div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </section>
@endsection
