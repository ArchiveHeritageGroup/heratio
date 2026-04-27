@extends('theme::layouts.2col')

@section('title-block')
  @if($page)
    <h1>{{ $page->title }}</h1>
  @endif
@endsection

@section('sidebar')
  {{-- Static pages menu (DB-driven from menu table, matching AtoM) --}}
  @if($staticPages->isNotEmpty())
    <section class="card mb-3">
      <h2 class="h5 p-3 mb-0">Static pages</h2>
      <div class="list-group list-group-flush">
        @foreach($staticPages as $sp)
          <a class="list-group-item list-group-item-action" href="{{ url('/' . $sp->slug) }}">
            {{ $sp->title }}
          </a>
        @endforeach
      </div>
    </section>
  @endif

  {{-- Browse by --}}
  @if($browseItems->isNotEmpty())
    <section class="card mb-3">
      <h2 class="h5 p-3 mb-0">Browse by</h2>
      <div class="list-group list-group-flush">
        @foreach($browseItems as $item)
          <a class="list-group-item list-group-item-action" href="{{ url($item->path) }}">
            {{ $item->label }}
          </a>
        @endforeach
      </div>
    </section>
  @endif

  {{-- Popular this week --}}
  @if($popularThisWeek->isNotEmpty())
    <section id="popular-this-week" class="card mb-3">
      <h2 class="h5 p-3 mb-0">Popular this week</h2>
      <div class="list-group list-group-flush">
        @foreach($popularThisWeek as $item)
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break"
             href="{{ url('/' . $item->slug) }}">
            {{ $item->title }}
            <span class="ms-3 text-nowrap">
              {{ $item->visits }} visits
            </span>
          </a>
        @endforeach
      </div>
    </section>
  @endif

@endsection

@section('content')
  {{-- Marketplace hero CTA — only when ahgMarketplacePlugin is enabled --}}
  @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgMarketplacePlugin'))
  <section class="marketplace-hero rounded shadow-sm mb-4 p-4"
           style="background:linear-gradient(135deg, var(--ahg-primary, #1d6a52) 0%, var(--ahg-secondary, #134537) 100%); color:#fff;">
    <div class="row align-items-center g-3">
      <div class="col-md-7">
        <h2 class="h3 mb-2 d-flex align-items-center">
          <i class="fas fa-store me-2"></i>
          Heratio Marketplace
        </h2>
        <p class="mb-0 lh-base">
          Buy and sell GLAM objects, artworks and collectibles &mdash; from gallery pieces to
          archival material. Browse listings or open your own seller account to set your price.
        </p>
      </div>
      <div class="col-md-5">
        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
          <a href="{{ url('/marketplace/browse') }}"
             class="btn btn-warning btn-lg fw-semibold px-4 shadow-sm">
            <i class="fas fa-shopping-bag me-2"></i>Browse &amp; Buy
          </a>
          <a href="{{ url('/marketplace/register') }}"
             class="btn btn-light btn-lg fw-semibold px-4 shadow-sm">
            <i class="fas fa-tag me-2"></i>Sell or join
          </a>
        </div>
        <div class="text-end mt-2">
          <a href="{{ url('/marketplace/featured') }}" class="text-white-50 small text-decoration-none">
            <i class="fas fa-star me-1"></i>See featured listings &raquo;
          </a>
        </div>
      </div>
    </div>
  </section>
  @endif

  {{-- Featured collection carousel --}}
  @if(!empty($carousel) && !empty($carousel['slides']))
    @php
      $collection = $carousel['collection'];
      $slides = $carousel['slides'];
      $height = $carousel['height'];
      $autoplay = $carousel['autoplay'];
      $interval = $carousel['interval'];
      $showCaptions = $carousel['showCaptions'];
      $carouselId = 'featured-collection-' . $collection->id;
    @endphp

    <section class="featured-collection mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">
          <i class="fas fa-images me-2 text-primary"></i>
          {{ $collection->name }}
        </h2>
        <a href="{{ url('/manifest-collection/' . $collection->id . '/view') }}" class="btn btn-sm btn-primary">
          View All <i class="fas fa-arrow-right ms-1"></i>
        </a>
      </div>
      @if($collection->description)
        <p class="text-muted mb-3">{{ $collection->description }}</p>
      @endif

      <div id="{{ $carouselId }}" class="carousel slide"
           data-bs-ride="{{ $autoplay ? 'carousel' : 'false' }}"
           data-bs-interval="{{ $interval }}">

        @if(count($slides) > 1)
          <div class="carousel-indicators">
            @foreach($slides as $idx => $slide)
              <button type="button"
                      data-bs-target="#{{ $carouselId }}"
                      data-bs-slide-to="{{ $idx }}"
                      @if($idx === 0) class="active" aria-current="true" @endif
                      aria-label="{{ $slide['title'] }}">
              </button>
            @endforeach
          </div>
        @endif

        <div class="carousel-inner rounded shadow" style="height: {{ $height }}; background: #1a1a1a;">
          @foreach($slides as $idx => $slide)
            <div class="carousel-item {{ $idx === 0 ? 'active' : '' }}" style="height: 100%;">
              <a href="{{ $slide['link'] }}" class="d-block h-100">
                <div class="d-flex align-items-center justify-content-center h-100">
                  <img src="{{ $slide['image_large'] }}"
                       class="d-block"
                       style="max-width: 100%; max-height: 100%; object-fit: contain;"
                       alt="{{ $slide['title'] }}"
                       loading="{{ $idx < 3 ? 'eager' : 'lazy' }}"
                       onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'text-white-50 text-center\'><i class=\'fas fa-image fa-3x mb-2\'></i><br>Image unavailable</div>';">
                </div>
              </a>
              @if($showCaptions)
                <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-75 rounded p-2">
                  <h5 class="mb-1">
                    @if($slide['media_type'] === 'video')
                      <i class="fas fa-film me-1"></i>
                    @elseif($slide['media_type'] === 'audio')
                      <i class="fas fa-music me-1"></i>
                    @endif
                    {{ $slide['title'] }}
                  </h5>
                  @if($slide['identifier'])
                    <small class="text-white-50">{{ $slide['identifier'] }}</small>
                  @endif
                </div>
              @endif
            </div>
          @endforeach
        </div>

        @if(count($slides) > 1)
          <button class="carousel-control-prev" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="prev">
            <span class="carousel-control-prev-icon bg-dark bg-opacity-50 rounded-circle p-3" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="next">
            <span class="carousel-control-next-icon bg-dark bg-opacity-50 rounded-circle p-3" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
          </button>
        @endif
      </div>

      @if(count($slides) > 1)
        <div class="featured-thumbnails d-flex flex-wrap justify-content-center gap-2 mt-3">
          @foreach($slides as $idx => $slide)
            <div class="position-relative">
              <img src="{{ $slide['image_thumb'] }}"
                   class="featured-thumb rounded border {{ $idx === 0 ? 'border-primary border-2' : '' }}"
                   style="width: 70px; height: 50px; object-fit: cover; cursor: pointer; transition: all 0.2s;"
                   data-bs-target="#{{ $carouselId }}"
                   data-bs-slide-to="{{ $idx }}"
                   alt="{{ $slide['title'] }}"
                   title="{{ $slide['title'] }}"
                   onerror="this.style.display='none';">
              @if($slide['media_type'] === 'video')
                <span class="position-absolute bottom-0 end-0 badge bg-dark bg-opacity-75" style="font-size: 0.6rem;"><i class="fas fa-film"></i></span>
              @elseif($slide['media_type'] === 'audio')
                <span class="position-absolute bottom-0 end-0 badge bg-dark bg-opacity-75" style="font-size: 0.6rem;"><i class="fas fa-music"></i></span>
              @endif
            </div>
          @endforeach
        </div>
      @endif
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var carousel = document.getElementById('{{ $carouselId }}');
        if (!carousel) return;

        var thumbs = document.querySelectorAll('[data-bs-target="#{{ $carouselId }}"].featured-thumb');

        carousel.addEventListener('slid.bs.carousel', function(e) {
            thumbs.forEach(function(t, i) {
                t.classList.remove('border-primary', 'border-2');
                if (i === e.to) {
                    t.classList.add('border-primary', 'border-2');
                }
            });
        });

        thumbs.forEach(function(thumb) {
            thumb.addEventListener('click', function() {
                var bsCarousel = bootstrap.Carousel.getOrCreateInstance(carousel);
                bsCarousel.to(parseInt(this.dataset.bsSlideTo));
            });
        });
    });
    </script>

    <style>
    .featured-thumb:hover {
        transform: scale(1.1);
        border-color: var(--bs-primary) !important;
    }
    .featured-collection .carousel-caption {
        bottom: 0;
        left: 0;
        right: 0;
        border-radius: 0 0 0.375rem 0.375rem !important;
    }
    .featured-collection .carousel-indicators [data-bs-target] {
        background-color: #000;
    }
    .featured-collection .carousel-indicators .active {
        background-color: #000;
    }
    </style>
  @endif

  {{-- Static page content --}}
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
      {!! $page->content ?? '' !!}
    </div>
  @endif
@endsection
